<?php

namespace App\Services\Pricing;

use App\Services\Pricing\Exceptions\InvalidDateOfBirthException;
use App\Services\Pricing\Exceptions\InvalidDateRangeException;
use App\Services\Pricing\Exceptions\UnsupportedCoverageException;
use App\Services\Pricing\Exceptions\UnsupportedDestinationException;
use App\Services\Pricing\Exceptions\UnsupportedPlanException;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PricingService
{
    /**
     * Calculate premium and produce an immutable pricing snapshot using decimal-safe arithmetic.
     *
     * @param array $params
     * @return array
     * @throws InvalidDateRangeException
     * @throws InvalidDateOfBirthException
     * @throws UnsupportedPlanException
     * @throws UnsupportedDestinationException
     * @throws UnsupportedCoverageException
     */
    public function calculatePremium(array $params): array
    {
        // 1. Validate & Normalize Dates
        $tripStart = Carbon::parse($params['trip_start'])->startOfDay();
        $tripEnd = Carbon::parse($params['trip_end'])->startOfDay();
        $dob = Carbon::parse($params['date_of_birth'])->startOfDay();

        if ($tripEnd->lt($tripStart)) {
            throw new InvalidDateRangeException(
                sprintf("Trip end date (%s) cannot be earlier than trip start date (%s).", 
                    $tripEnd->toDateString(), 
                    $tripStart->toDateString()
                )
            );
        }

        if ($dob->gt($tripStart)) {
            throw new InvalidDateOfBirthException(
                sprintf("Date of birth (%s) cannot be after the trip start date (%s).", 
                    $dob->toDateString(), 
                    $tripStart->toDateString()
                )
            );
        }

        // 2. Inclusive Trip Days calculation (e.g. 2026-11-01 to 2026-11-01 is 1 day)
        $tripDays = (int) $tripStart->diffInDays($tripEnd) + 1;

        // 3. Age calculation strictly at trip start date
        $travelerAge = (int) $dob->diffInYears($tripStart);

        // 4. Validate & fetch plan configuration
        $planKey = strtoupper(trim((string) $params['plan']));
        $plans = config('pricing.plans', []);
        if (!isset($plans[$planKey])) {
            throw new UnsupportedPlanException(
                sprintf("Unsupported plan '%s'. Supported plans: %s", 
                    $params['plan'], 
                    implode(', ', array_keys($plans))
                )
            );
        }
        $baseDailyRate = (string) $plans[$planKey]['base_daily_rate'];

        // 5. Validate & fetch destination configuration
        $destKey = strtoupper(trim((string) $params['destination']));
        $destinations = config('pricing.destinations', []);
        if (!isset($destinations[$destKey])) {
            throw new UnsupportedDestinationException(
                sprintf("Unsupported destination '%s'. Supported destinations: %s", 
                    $params['destination'], 
                    implode(', ', array_keys($destinations))
                )
            );
        }
        $destMultiplier = (string) $destinations[$destKey]['multiplier'];

        // 6. Validate & fetch coverage configuration
        $coverageKey = strtoupper(trim((string) ($params['coverage_type'] ?? $params['coverage'] ?? '')));
        $coverages = config('pricing.coverages', []);
        if (!isset($coverages[$coverageKey])) {
            throw new UnsupportedCoverageException(
                sprintf("Unsupported coverage '%s'. Supported coverages: %s", 
                    $params['coverage_type'] ?? $params['coverage'] ?? '', 
                    implode(', ', array_keys($coverages))
                )
            );
        }
        $coverageMultiplier = (string) $coverages[$coverageKey]['multiplier'];

        // 7. Determine Age Band and Multiplier
        $ageBands = config('pricing.age_bands', []);
        $matchedBandKey = null;
        $matchedBand = null;

        foreach ($ageBands as $bandKey => $bandConfig) {
            if ($travelerAge >= $bandConfig['min_age'] && $travelerAge <= $bandConfig['max_age']) {
                $matchedBandKey = $bandKey;
                $matchedBand = $bandConfig;
                break;
            }
        }

        if (!$matchedBand) {
            // Default fallback if age exceeds 120
            $matchedBandKey = '71_plus';
            $matchedBand = $ageBands['71_plus'] ?? ['label' => '71+', 'multiplier' => '2.00'];
        }

        $ageMultiplier = (string) $matchedBand['multiplier'];

        // 8. Decimal-safe arithmetic using BCMath (4 decimal intermediate precision)
        // Daily Rate = Base Rate * Destination Multiplier * Coverage Multiplier * Age Multiplier
        $dailyRate = bcmul($baseDailyRate, $destMultiplier, 4);
        $dailyRate = bcmul($dailyRate, $coverageMultiplier, 4);
        $dailyRate = bcmul($dailyRate, $ageMultiplier, 4);

        // Total Premium = Daily Rate * Trip Days
        $totalRaw = bcmul($dailyRate, (string) $tripDays, 4);

        // Half-up rounding to standard 2 currency decimals
        $totalAmount = $this->roundToCents($totalRaw);
        $dailyRateRounded = $this->roundToCents($dailyRate);

        $currency = $params['currency'] ?? config('pricing.default_currency', 'USD');

        // 9. Build Immutable Snapshot
        $snapshot = [
            'version' => config('pricing.version', '1.0.0'),
            'calculation_id' => 'calc_' . Str::uuid()->toString(),
            'calculated_at' => now()->toIso8601String(),
            'currency' => $currency,
            'traveler_age' => $travelerAge,
            'age_band' => $matchedBand['label'],
            'trip_days' => $tripDays,
            'pricing_inputs' => [
                'plan' => $planKey,
                'destination' => $destKey,
                'coverage_type' => $coverageKey,
                'date_of_birth' => $dob->toDateString(),
                'trip_start' => $tripStart->toDateString(),
                'trip_end' => $tripEnd->toDateString(),
            ],
            'rates_and_multipliers' => [
                'base_daily_rate' => $baseDailyRate,
                'destination_multiplier' => $destMultiplier,
                'coverage_multiplier' => $coverageMultiplier,
                'age_multiplier' => $ageMultiplier,
            ],
            'breakdown' => [
                'daily_premium' => $dailyRateRounded,
                'total_amount' => $totalAmount,
            ],
            'amount' => $totalAmount,
        ];

        return [
            'amount' => $totalAmount,
            'currency' => $currency,
            'trip_days' => $tripDays,
            'traveler_age' => $travelerAge,
            'plan' => $planKey,
            'destination' => $destKey,
            'coverage_type' => $coverageKey,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * Decimal-safe half-up rounding to 2 decimal places.
     */
    protected function roundToCents(string $amount): string
    {
        $parts = explode('.', $amount);
        $integerPart = $parts[0] ?? '0';
        $decimalPart = str_pad($parts[1] ?? '0', 4, '0');

        $cents = substr($decimalPart, 0, 2);
        $thirdDigit = (int) $decimalPart[2];

        if ($thirdDigit >= 5) {
            return bcadd($integerPart . '.' . $cents, '0.01', 2);
        }

        return $integerPart . '.' . $cents;
    }
}
