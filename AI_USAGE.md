# AI Usage & Development Workflow Disclosure

This document provides a transparent, honest disclosure of how AI tooling was utilized in the architecture, implementation, debugging, and verification of the **PolicyFlow: Fault-Tolerant Travel Insurance Issuance & Reconciliation Engine**.

---

## 🤖 AI Tools & Models Used

| Tool / Environment | Model(s) | Primary Purpose |
|---|---|---|
| **Google Antigravity IDE** | Claude Sonnet 4.6 & Gemini 3.8 Flash | Agentic pair programming, system architecture, test suite authoring, full-stack code implementation |

---

## 💡 Concrete Ways AI Assisted

### 1. Test-Driven Development (TDD) & Edge-Case Matrix
AI accelerated the creation of a comprehensive automated test suite in PHPUnit / Laravel. It derived test matrices for:
* **Inclusive Day Calculations**: Verifying that trips starting and ending on the same calendar day count as 1 full day ($End - Start + 1$), and that multi-day boundaries are inclusive.
* **Age Calculation Anchoring**: Ensuring traveler age is calculated strictly as of the `trip_start` date rather than the intake quote date.
* **Pricing Multipliers**: Verifying all permutations of 3 plan codes, 4 age tiers, 5 geographic regions, and 4 sum-insured tiers using decimal-safe rounding.
* **Concurrency & Race Conditions**: Designing automated tests asserting that duplicate intake submissions return the original policy and immutable snapshot with HTTP 200 rather than creating duplicate records or throwing 500 errors.

### 2. Full-Stack Scaffolding & Architecture Implementation
AI assisted in rapidly structuring:
* **Database Relational Schema**: Crafting Laravel migrations with foreign key constraints, composite unique indexes (`(agent_id, client_request_id)`, `policy_number`, `provider_event_id`), and dedicated audit tables (`policy_state_history`, `issuance_attempts`, `provider_events`).
* **Clean Domain Services**: Separating concerns into distinct, testable classes: `PricingService`, `PolicyStateService`, `IssuanceService`, and `MockInsurerService`.
* **React Operations Dashboard**: Building a complete, responsive React 18 + Vite frontend styled with Tailwind CSS, including real-time operational metric cards, an interactive intake form with live quote calculations, a filterable policy repository, and a modal for inspecting issuance attempts and state transition audit timelines.

### 3. Finite State Machine Formalization
AI helped formalize the directed transition graph for policy lifecycles, ensuring explicit state transition enforcement and database audit logging at every step.

---

## ⚠️ Real Incorrect / Incomplete AI Suggestions Encountered

During development, several real errors and architectural gaps occurred in the AI's initial suggestions that required human diagnosis, debugging, and correction:

### 1. State Transition Deadlock on Synchronous Mock Callback
* **What Happened**: AI originally drafted `PolicyStateService::ALLOWED_TRANSITIONS` with:
  ```php
  self::SUBMITTED => [self::PENDING_CONFIRMATION, self::FAILED],
  ```
  However, in the synchronous `SUCCESS` scenario, the mock insurer returns `CALLBACK_DELIVERED` immediately. When the controller processed the callback, it attempted to transition `$policy` from `SUBMITTED` directly to `ISSUED`. Because `ISSUED` was not in the allowed edges for `SUBMITTED`, the state machine threw a `RuntimeException: Invalid state transition: 'SUBMITTED' -> 'ISSUED' is not permitted`, causing 4 automated tests to fail with HTTP 422.
* **How It Was Caught**: Running `php artisan test` surfaced the 4 failing assertions in `PolicyIssuanceTest`.
* **Correction Made**: Updated `ALLOWED_TRANSITIONS` to explicitly permit `self::SUBMITTED => [self::PENDING_CONFIRMATION, self::ISSUED, self::FAILED]`, enabling immediate synchronous issuance while preserving all non-regression guards.

### 2. Unhandled Domain Exceptions in Controller (HTTP 500 vs. 422)
* **What Happened**: AI wrote `PricingService` to throw custom `PricingException` subclasses (e.g. `InvalidTripDatesException`, `UnsupportedPlanException`). However, the AI failed to catch these exceptions in `PolicyRequestController::store()`, allowing them to bubble up as unhandled server crashes (HTTP 500) rather than standard validation errors.
* **How It Was Caught**: Feature tests expecting HTTP 422 received HTTP 500 instead.
* **Correction Made**: Introduced a unified `PricingException` base class and added explicit `try/catch (PricingException $e)` blocks in `PolicyRequestController` to return structured HTTP 422 JSON validation payloads with clear error messages.

### 3. Re-entry Exception on Repeated Status Reconciliation
* **What Happened**: The AI implemented `IssuanceService::reconcile()` with a strict precondition:
  ```php
  if ($policy->status !== PolicyStateService::NEEDS_RECONCILIATION) {
      throw new RuntimeException("Cannot reconcile policy in status '{$policy->status}'.");
  }
  ```
  If an operator or automated cron job polled the `/reconcile` endpoint a second time after a policy had already transitioned to `ISSUED`, this check threw an unhandled exception, violating idempotency.
* **How It Was Caught**: Added an automated reliability test asserting reconciliation idempotency.
* **Correction Made**: Added an early idempotent return:
  ```php
  if ($policy->status === PolicyStateService::ISSUED) {
      return $policy;
  }
  ```
  This ensures subsequent reconciliation requests safely return the existing `ISSUED` policy with HTTP 200 without duplicate provider events or state mutations.

### 4. Windows IPv6 Loopback Resolution Hang
* **What Happened**: The AI configured the frontend with `VITE_API_BASE_URL=http://localhost:8000/api`. On Windows 11, Chromium browsers resolve `localhost` to IPv6 `::1` first, whereas `php artisan serve` binds strictly to IPv4 `127.0.0.1:8000`. This caused browser fetches to hang in "Checking..." / "Loading policies from server..." state until TCP connection timeouts occurred.
* **How It Was Caught**: Browser subagent inspection revealed requests to `http://localhost:8000/api` stalling while direct curl requests to `http://127.0.0.1:8000/api` succeeded immediately.
* **Correction Made**: Added a reverse proxy in `vite.config.ts` mapping `/api` to `http://127.0.0.1:8000`, set `VITE_API_BASE_URL=/api`, and refactored `api.ts` to build relative URLs safely against `window.location.origin` with 15-second abort timeouts.

### 5. Placeholder Options vs. Assignment Docx Specifications
* **What Happened**: The AI initially scaffolded the UI with placeholder options (`SILVER`, `GOLD`, `PLATINUM`, `SCHENGEN`, and USD currency `$`) rather than the exact assignment specifications from `TrippersShield.docx`.
* **How It Was Caught**: Comparing the running frontend dropdowns against the assignment requirements document revealed discrepancies in plan codes, destinations, sum-insured tiers, and currency symbols.
* **Correction Made**: Updated `config/pricing.php` and the React frontend components (`PolicyForm.tsx`, `PolicyTable.tsx`, `PolicyDetailsModal.tsx`) to strictly use:
  * Plan Codes: `BASIC` (₹12/day), `PLUS` (₹20/day), `PREMIUM` (₹30/day)
  * Destinations: `DOMESTIC` (1.00x), `INTERNATIONAL` (1.80x)
  * Sum Insured: ₹100,000 (1.00x), ₹300,000 (1.25x), ₹500,000 (1.50x)
  * Currency: Indian Rupee (`₹` / `INR`)
  * Age Tiers: 0–17 (1.10x), 18–60 (1.00x), 61–70 (1.50x), 71+ (2.00x)

---

## 🔍 How Work Was Verified

All AI-generated and refactored code was rigorously verified using a combination of automated test suites, static type checking, and manual review:

1. **Automated Backend Testing**:
   * Executed `php artisan test` across all 44 tests (167 assertions) verifying decimal-safe pricing, state machine transitions, unique constraint integrity, mock insurer scenarios, and edge cases. 100% test pass achieved.
   * Executed `PolicyModelAndDatabaseTest` directly against MySQL (`policyflow_test` on port 3307) verifying real MySQL uniqueness constraints and transaction rollbacks.
2. **Frontend Type Checking & Bundle Compilation**:
   * Executed `npm run build` (`tsc -b && vite build`) verifying TypeScript strict typing across all interfaces, components, and API service functions with zero compilation errors.
3. **Database Integrity & Security Review**:
   * Verified that no credentials, `.env` files, `vendor/`, or `node_modules/` were committed to git.
   * Inspected database migrations to ensure foreign key cascades, proper column types (`decimal(10,2)` for money, `json` for immutable snapshots), and composite unique indexes.
4. **Manual End-to-End Walkthrough**:
   * Tested policy intake, live quote calculation, insurer submission, retry after temporary failure, timeout reconciliation, and duplicate callback idempotency through both the REST API and the React Operations Dashboard.
