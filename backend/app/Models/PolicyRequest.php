<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $table = 'policy_requests';

    protected $fillable = [
        'policy_number',
        'agent_id',
        'client_request_id',
        'status',
        'insured_name',
        'insured_email',
        'date_of_birth',
        'traveler_age',
        'destination',
        'trip_start',
        'trip_end',
        'trip_days',
        'plan',
        'coverage_type',
        'currency',
        'premium_amount',
        'premium_snapshot',
        'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'traveler_age' => 'integer',
        'trip_days' => 'integer',
        'premium_amount' => 'decimal:2',
        'premium_snapshot' => 'array',
        'metadata' => 'array',
    ];

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class);
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class);
    }

    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class);
    }

    /**
     * Transition state and log to history table within an atomic audit trail.
     */
    public function transitionTo(string $newStatus, ?string $triggerEvent = null, string $actorType = 'SYSTEM', ?string $actorId = null, array $metadata = []): self
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        $this->stateHistories()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'trigger_event' => $triggerEvent,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata,
        ]);

        return $this;
    }
}
