# PolicyFlow — Fault-Tolerant Insurance Issuance & Reconciliation Engine

PolicyFlow is an enterprise-grade travel insurance issuance and reconciliation engine built with **Laravel 11 (PHP 8.2+)** and **React + Vite (TypeScript)**. It guarantees safe concurrent policy intake, immutable decimal-safe premium calculations, strict finite state machine lifecycles, and automated reconciliation for dropped or delayed provider callbacks.

---

## 🌟 Core Architecture & Reliability Features

1. **Idempotent Intake Engine**:
   - Unique database constraint on `(agent_id, client_request_id)`.
   - Repeated intake attempts return HTTP 200 with the existing record and immutable premium snapshot without creating duplicate rows.
2. **Immutable Premium Calculation Snapshot**:
   - Exact decimal-safe arithmetic (avoiding IEEE 754 floating-point drift).
   - Inclusive day count ($End - Start + 1$) and traveler age calculated strictly at trip start date.
   - Fixed matrix multipliers for age bands, geographic risk zones (Asia, Europe, Schengen, USA/Canada, Worldwide), and coverage tiers ($50k - $500k).
   - Stores an immutable JSON pricing snapshot with all inputs and calculation metadata upon quote generation.
3. **Finite State Machine & State History Audit**:
   - Validated state lifecycle: `RECEIVED` &rarr; `VALIDATED` &rarr; `SUBMITTED` &rarr; `PENDING_CONFIRMATION` &rarr; `ISSUED` | `FAILED` | `NEEDS_RECONCILIATION`.
   - **Terminal State Invariant**: Once `ISSUED`, policy state can **never** regress to an earlier state or be modified by late failure callbacks.
   - Comprehensive audit logging in `policy_state_history` with trigger events, timestamps, and actor tags.
4. **Deterministic Mock Insurer & Edge Case Simulator**:
   - `SUCCESS`: Instant synchronous issuance.
   - `TEMPORARY_FAILURE`: First attempt fails with retryable error; subsequent attempts succeed and transition to `ISSUED`.
   - `TIMEOUT_AFTER_ISSUE`: Insurer issues policy but callback is lost; system detects timeout, transitions to `NEEDS_RECONCILIATION`, and polling the reconciliation endpoint recovers the issued policy without re-issuing.
   - `DELAYED_CALLBACK`: Provider accepts request asynchronously; callback delivered later.
   - `DUPLICATE_CALLBACK`: Idempotent webhook handling guarded by unique `provider_event_id` and database locks.
5. **Polished Operations Dashboard (React + Vite + TypeScript)**:
   - Real-time operational metrics (Total Policies, Active Issued, Pending, Needs Reconciliation, Failed).
   - Interactive intake form with live date/age calculators.
   - Status-filtered policy repository table with distinct animated status badges.
   - Modal inspection with complete attempt history, state transition timeline, and insurer simulation controls.

---

## 📁 Repository Structure

```text
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/       # PolicyRequestController, MockInsurerCallbackController
│   │   ├── Http/Requests/          # StorePolicyRequest with validation rules
│   │   ├── Models/                 # PolicyRequest, IssuanceAttempt, ProviderEvent, PolicyStateHistory
│   │   └── Services/
│   │       ├── Pricing/            # PricingService, decimal-safe rate engine, exceptions
│   │       ├── Policy/             # PolicyService, PolicyStateService, IssuanceService
│   │       └── Insurer/            # MockInsurerService deterministic simulator
│   ├── database/migrations/        # Relational schema with indexes and foreign keys
│   ├── routes/api.php              # RESTful API routes with CORS configuration
│   └── tests/                      # 43 automated unit & feature tests (100% pass)
├── frontend/
│   ├── src/
│   │   ├── components/             # OverviewCards, PolicyForm, PolicyTable, PolicyDetailsModal, StatusBadge
│   │   ├── services/api.ts         # Type-safe API client
│   │   ├── types.ts                # TypeScript interfaces
│   │   ├── App.tsx                 # Operations dashboard root
│   │   └── index.css               # Tailwind CSS theme
│   ├── package.json
│   └── vite.config.ts
├── AI_USAGE.md                     # Engineering methodology & AI collaboration log
├── .gitignore                      # Standard exclusions (node_modules, vendor, .env, build artifacts)
└── README.md                       # System documentation
```

---

## 🚀 Getting Started

### Prerequisites
- **PHP**: 8.2+ with `pdo`, `mbstring`, `bcmath`, `curl`
- **Composer**: 2.x
- **Node.js**: 20+ & **npm** 10+
- **MySQL / SQLite**: MySQL (XAMPP or local) or SQLite for in-memory testing

### 1. Backend Setup
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
php artisan serve
```
The API will run on `http://localhost:8000`.

### 2. Frontend Setup
```bash
cd frontend
npm install
npm run build
npm run dev
```
The operations dashboard will run on `http://localhost:5173`.

---

## 🧪 Automated Test Suite

Run the full backend test suite:
```bash
cd backend
php artisan test
```
* **43 passed** (164 assertions)
* Verifies pricing precision, state transitions, unique constraint integrity, mock insurer scenarios, and edge cases.
