# AI Usage & Development Workflow

This document outlines the collaborative AI-assisted engineering methodology used to design, build, test, and harden the **PolicyFlow: Fault-Tolerant Travel Insurance Issuance & Reconciliation Engine**.

---

## 🛠️ AI Tooling & Roles

| Tool / Model | Primary Engineering Roles |
|---|---|
| **Antigravity IDE (Agentic AI)** | End-to-end full-stack pair programmer, system architect, refactoring agent, test author |
| **Claude Sonnet / Gemini 3.8 Flash** | Architecture design, high-precision financial arithmetic formulation, finite state machine specification, React dashboard design |

---

## 🎯 Division of Labor: Delegated vs. Human-Directed

### Delegated to AI
1. **Boilerplate & Configuration Scaffolding**:
   - Laravel 11 API routing (`routes/api.php`), CORS headers for `http://localhost:5173`.
   - Migration schemas for `policy_requests`, `issuance_attempts`, `provider_events`, and `policy_state_history`.
   - React + Vite + TypeScript frontend scaffolding with Tailwind CSS tokens and layout components.
2. **Pricing Engine Calculation Matrix**:
   - Implementation of exact multiplier tables (Age bands, Destinations, Coverage tiers) with high-precision decimal-safe arithmetic (`round(..., 2)`).
   - Inclusive day count calculation ($End - Start + 1$) and age calculation anchored strictly at trip start.
3. **Comprehensive Test Suite Generation**:
   - Generation of 43 automated unit and feature test cases asserting edge cases, validation boundaries, and race conditions.

### Human-Directed Architecture & Critical Interventions
1. **Finite State Machine Non-Regression Invariant**:
   - *Requirement*: A policy in the `ISSUED` state must be terminal and cannot transition backwards or regress, even if out-of-order, delayed, or error callbacks arrive later.
   - *Implementation*: Enforced at both the service layer (`PolicyStateService`) and the database level with explicit audit history logging.
2. **Deterministic Idempotency Key Design**:
   - Enforced database uniqueness on `(agent_id, client_request_id)` with transactional `firstOrCreate` / unique constraint catch handlers to prevent duplicate policies under concurrent client intake.
3. **Reconciliation vs. Re-issuance**:
   - Specifically architected the `TIMEOUT_AFTER_ISSUE` recovery flow: when callbacks are lost or timeout occurs, the system marks the policy `NEEDS_RECONCILIATION` and polls the provider status rather than creating a duplicate policy.

---

## 🔍 Specific Failure Modes Caught & Corrected During Development

### 1. State Transition Deadlock on Synchronous Mock Callback
- **Issue**: The initial state graph defined `SUBMITTED -> PENDING_CONFIRMATION` only. When `MockInsurerService` responded synchronously with `CALLBACK_DELIVERED` for instant issuance, attempting to transition `SUBMITTED -> ISSUED` threw an `Illegal state transition` exception.
- **Correction**: Updated `PolicyStateService::ALLOWED_TRANSITIONS` to explicitly permit `SUBMITTED -> ISSUED` for instant confirmations while preserving all invariant checks and terminal restrictions.

### 2. Missing Pricing Exception Catch Handling in Controller
- **Issue**: Validation errors raised by `PricingService` (such as unsupported destinations, inverted trip dates, or under-age DOBs) were bubbling up as unhandled exceptions (HTTP 500) rather than HTTP 422 Unprocessable Entity.
- **Correction**: Introduced `PricingException` hierarchy extending domain exceptions and added explicit catch blocks in `PolicyRequestController` to return clean, actionable HTTP 422 JSON validation payloads.

### 3. Re-entry Idempotency on Repeated Status Reconciliation
- **Issue**: Polling `/api/policy-requests/{id}/reconcile` on a policy that had already been successfully reconciled threw an exception because its status was already `ISSUED` instead of `NEEDS_RECONCILIATION`.
- **Correction**: Added an idempotent fast-path in `IssuanceService::reconcile`: if the policy is already `ISSUED`, it safely returns the existing policy without throwing or creating duplicate provider events.

---

## 📊 Automated Verification Metrics
- **Total Backend Tests**: 43 passed (164 assertions) in ~1.88s
- **Frontend Production Bundle**: Built with Vite 5 + TypeScript + Tailwind (0 lint or type errors)
- **Zero Secrets Committed**: Verified `.gitignore` prevents `.env`, `vendor/`, `node_modules/`, and SQLite/logs from entering source control.
