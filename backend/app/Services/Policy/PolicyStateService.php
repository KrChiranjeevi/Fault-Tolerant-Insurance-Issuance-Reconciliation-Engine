<?php

namespace App\Services\Policy;

use App\Models\PolicyRequest;
use RuntimeException;

/**
 * PolicyStateService
 *
 * Enforces explicit, validated state transitions for the policy lifecycle.
 * ISSUED is a terminal state — no transition out of ISSUED is ever permitted.
 *
 * Valid Transition Graph:
 *   null              → RECEIVED
 *   RECEIVED          → VALIDATED | FAILED
 *   VALIDATED         → SUBMITTED
 *   SUBMITTED         → PENDING_CONFIRMATION | FAILED
 *   PENDING_CONFIRMATION → ISSUED | FAILED | NEEDS_RECONCILIATION
 *   FAILED            → SUBMITTED  (safe retry)
 *   NEEDS_RECONCILIATION → ISSUED | FAILED
 *   ISSUED            → (terminal — no transitions allowed)
 */
class PolicyStateService
{
    public const RECEIVED              = 'RECEIVED';
    public const VALIDATED             = 'VALIDATED';
    public const SUBMITTED             = 'SUBMITTED';
    public const PENDING_CONFIRMATION  = 'PENDING_CONFIRMATION';
    public const ISSUED                = 'ISSUED';
    public const FAILED                = 'FAILED';
    public const NEEDS_RECONCILIATION  = 'NEEDS_RECONCILIATION';

    /** All valid forward (and retry) transitions. ISSUED has no outbound edges. */
    private const ALLOWED_TRANSITIONS = [
        null                     => [self::RECEIVED],
        self::RECEIVED           => [self::VALIDATED, self::FAILED],
        self::VALIDATED          => [self::SUBMITTED],
        self::SUBMITTED          => [self::PENDING_CONFIRMATION, self::ISSUED, self::FAILED],
        self::PENDING_CONFIRMATION => [self::ISSUED, self::FAILED, self::NEEDS_RECONCILIATION],
        self::FAILED             => [self::SUBMITTED],
        self::NEEDS_RECONCILIATION => [self::ISSUED, self::FAILED],
        self::ISSUED             => [],   // terminal – empty on purpose
    ];

    /**
     * Attempt a state transition.  Throws if the transition is illegal.
     * Records an entry in policy_state_history.
     *
     * @throws RuntimeException when the transition is forbidden
     */
    public function transition(
        PolicyRequest $policy,
        string        $toState,
        string        $triggerEvent,
        string        $actorType   = 'SYSTEM',
        ?string       $actorId     = null,
        array         $metadata    = []
    ): PolicyRequest {
        $fromState = $policy->status;

        $this->assertAllowed($fromState, $toState);

        // Persist using the model's atomic audit method
        return $policy->transitionTo($toState, $triggerEvent, $actorType, $actorId, $metadata);
    }

    /**
     * Check whether a transition is allowed without performing it.
     */
    public function canTransition(?string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @throws RuntimeException
     */
    public function assertAllowed(?string $from, string $to): void
    {
        if ($from === self::ISSUED) {
            throw new RuntimeException(
                "State regression denied: ISSUED is terminal and cannot transition to '{$to}'."
            );
        }

        if (!$this->canTransition($from, $to)) {
            throw new RuntimeException(
                "Invalid state transition: '{$from}' → '{$to}' is not permitted."
            );
        }
    }
}
