<?php

namespace Tests\Unit;

use App\Services\Pricing\Exceptions\InvalidDateOfBirthException;
use App\Services\Pricing\Exceptions\InvalidDateRangeException;
use App\Services\Pricing\Exceptions\UnsupportedCoverageException;
use App\Services\Pricing\Exceptions\UnsupportedDestinationException;
use App\Services\Pricing\Exceptions\UnsupportedPlanException;
use App\Services\Pricing\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService();
    }

    /**
     * Test valid premium calculation with exact rates, multipliers, and snapshot metadata.
     */
    public function test_valid_premium_calculation_with_accurate_snapshot(): void
    {
        // 5 inclusive days: Nov 1 to Nov 5
        // Standard Plan ($15.00) * Europe (1.50) * Comprehensive (1.50) * Age 30 (1.00)
        // Daily: 15 * 1.5 * 1.5 * 1.0 = 33.75
        // Total: 33.75 * 5 = 168.75
        $result = $this->pricingService->calculatePremium([
            'plan' => 'STANDARD',
            'destination' => 'EUROPE',
            'coverage_type' => 'COMPREHENSIVE',
            'date_of_birth' => '1996-01-01',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'currency' => 'USD',
        ]);

        $this->assertSame('168.75', $result['amount']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame(5, $result['trip_days']);
        $this->assertSame(30, $result['traveler_age']);

        $snapshot = $result['snapshot'];
        $this->assertNotEmpty($snapshot['calculation_id']);
        $this->assertSame('1.0.0', $snapshot['version']);
        $this->assertSame('15.00', $snapshot['rates_and_multipliers']['base_daily_rate']);
        $this->assertSame('1.50', $snapshot['rates_and_multipliers']['destination_multiplier']);
        $this->assertSame('1.50', $snapshot['rates_and_multipliers']['coverage_multiplier']);
        $this->assertSame('1.00', $snapshot['rates_and_multipliers']['age_multiplier']);
        $this->assertSame('33.75', $snapshot['breakdown']['daily_premium']);
        $this->assertSame('168.75', $snapshot['breakdown']['total_amount']);
    }

    /**
     * Test all defined age bands produce the exact multipliers (Docx 4 tiers).
     */
    public function test_each_age_band_calculates_correct_multiplier(): void
    {
        // Reference trip: 1 day, Basic Plan (₹12), Domestic (1.0), Medical Only (1.0)
        // Expected total = ₹12 * 1.0 * 1.0 * age_multiplier * 1 day
        $tripStart = '2026-06-01';
        $tripEnd = '2026-06-01';

        $ageCases = [
            ['dob' => '2016-06-01', 'expected_age' => 10, 'expected_mult' => '1.10', 'expected_amount' => '13.20'],
            ['dob' => '2001-06-01', 'expected_age' => 25, 'expected_mult' => '1.00', 'expected_amount' => '12.00'],
            ['dob' => '1961-06-01', 'expected_age' => 65, 'expected_mult' => '1.50', 'expected_amount' => '18.00'],
            ['dob' => '1951-06-01', 'expected_age' => 75, 'expected_mult' => '2.00', 'expected_amount' => '24.00'],
        ];

        foreach ($ageCases as $case) {
            $result = $this->pricingService->calculatePremium([
                'plan' => 'BASIC',
                'destination' => 'DOMESTIC',
                'coverage_type' => 'MEDICAL_ONLY',
                'date_of_birth' => $case['dob'],
                'trip_start' => $tripStart,
                'trip_end' => $tripEnd,
                'currency' => 'INR',
            ]);

            $this->assertSame($case['expected_age'], $result['traveler_age'], "Age mismatch for DOB {$case['dob']}");
            $this->assertSame($case['expected_mult'], $result['snapshot']['rates_and_multipliers']['age_multiplier'], "Multiplier mismatch for age {$case['expected_age']}");
            $this->assertSame($case['expected_amount'], $result['amount'], "Amount mismatch for age {$case['expected_age']}");
        }
    }

    /**
     * Test one-day trips calculate exactly 1 day.
     */
    public function test_one_day_trips(): void
    {
        $result = $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-08-15',
            'trip_end' => '2026-08-15',
            'currency' => 'INR',
        ]);

        $this->assertSame(1, $result['trip_days']);
        $this->assertSame('12.00', $result['amount']);
    }

    /**
     * Test inclusive day count (start and end days both count).
     */
    public function test_inclusive_day_count(): void
    {
        // June 1 to June 10 is 10 inclusive calendar days
        $result = $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-10',
            'currency' => 'INR',
        ]);

        $this->assertSame(10, $result['trip_days']);
        $this->assertSame('120.00', $result['amount']);
    }

    /**
     * Test age is calculated strictly at trip start date, not current date.
     */
    public function test_age_calculated_at_trip_start(): void
    {
        // Traveler born on 1965-06-15:
        // Trip starts 2026-06-14 -> Age 60 (Band 18-60, multiplier 1.00)
        // Trip starts 2026-06-15 -> Age 61 (Band 61-70, multiplier 1.50)
        $beforeBirthday = $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '1965-06-15',
            'trip_start' => '2026-06-14',
            'trip_end' => '2026-06-14',
            'currency' => 'INR',
        ]);

        $this->assertSame(60, $beforeBirthday['traveler_age']);
        $this->assertSame('1.00', $beforeBirthday['snapshot']['rates_and_multipliers']['age_multiplier']);

        $onBirthday = $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '1965-06-15',
            'trip_start' => '2026-06-15',
            'trip_end' => '2026-06-15',
            'currency' => 'INR',
        ]);

        $this->assertSame(61, $onBirthday['traveler_age']);
        $this->assertSame('1.50', $onBirthday['snapshot']['rates_and_multipliers']['age_multiplier']);
    }

    /**
     * Test unsupported plan throws UnsupportedPlanException.
     */
    public function test_unsupported_plan_throws_exception(): void
    {
        $this->expectException(UnsupportedPlanException::class);

        $this->pricingService->calculatePremium([
            'plan' => 'NON_EXISTENT_PLAN',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
        ]);
    }

    /**
     * Test unsupported destination throws UnsupportedDestinationException.
     */
    public function test_unsupported_destination_throws_exception(): void
    {
        $this->expectException(UnsupportedDestinationException::class);

        $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'MOON_BASE',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
        ]);
    }

    /**
     * Test unsupported coverage throws UnsupportedCoverageException.
     */
    public function test_unsupported_coverage_throws_exception(): void
    {
        $this->expectException(UnsupportedCoverageException::class);

        $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'EXTREME_DANGER_COVER',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
        ]);
    }

    /**
     * Test trip_end before trip_start throws InvalidDateRangeException.
     */
    public function test_trip_end_before_trip_start_throws_exception(): void
    {
        $this->expectException(InvalidDateRangeException::class);

        $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2000-01-01',
            'trip_start' => '2026-06-10',
            'trip_end' => '2026-06-05',
        ]);
    }

    /**
     * Test date of birth after trip start throws InvalidDateOfBirthException.
     */
    public function test_date_of_birth_after_trip_start_throws_exception(): void
    {
        $this->expectException(InvalidDateOfBirthException::class);

        $this->pricingService->calculatePremium([
            'plan' => 'BASIC',
            'destination' => 'DOMESTIC',
            'coverage_type' => 'MEDICAL_ONLY',
            'date_of_birth' => '2026-07-01',
            'trip_start' => '2026-06-01',
            'trip_end' => '2026-06-05',
        ]);
    }

    /**
     * Test decimal-safe arithmetic prevents IEEE-754 floating point imprecision.
     */
    public function test_decimal_safe_arithmetic_precision(): void
    {
        // 3 days, Premium (₹30.00) * International (1.80) * 300,000 Cover (1.25) * Senior age 65 (1.50)
        // 30.00 * 1.80 = 54.00
        // 54.00 * 1.25 = 67.50
        // 67.50 * 1.50 = 101.25
        // 101.25 * 3 days = 303.75
        $result = $this->pricingService->calculatePremium([
            'plan' => 'PREMIUM',
            'destination' => 'INTERNATIONAL',
            'coverage_type' => '300000',
            'date_of_birth' => '1961-01-01',
            'trip_start' => '2026-09-01',
            'trip_end' => '2026-09-03',
            'currency' => 'INR',
        ]);

        $this->assertSame('303.75', $result['amount']);
        $this->assertSame('101.25', $result['snapshot']['breakdown']['daily_premium']);
    }
}
