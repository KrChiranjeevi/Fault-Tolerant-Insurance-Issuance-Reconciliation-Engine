# PolicyFlow — Backend (Laravel 11 API)

Enterprise policy lifecycle management and workflow automation API service.

## 🚀 Tech Stack

- **Framework**: Laravel 11.x (PHP 8.2+)
- **Authentication**: Laravel Sanctum (Token-based API authentication)
- **Database**: MySQL 8.0+ (InnoDB, UTF-8 Multilingual `utf8mb4_unicode_ci`)
- **Arithmetic Engine**: BCMath Decimal-Safe Precision
- **Testing**: PHPUnit 10.x with SQLite in-memory and MySQL integration support

---

## 📐 Travel Insurance Pricing Engine & Formula

PolicyFlow calculates premiums deterministically using **decimal-safe arithmetic (BCMath)** to avoid binary floating-point rounding errors.

### 1. The Core Pricing Formula

$$\text{Trip Days (inclusive)} = \text{DateDiff}(\text{trip\_end}, \text{trip\_start}) + 1$$

$$\text{Traveler Age} = \text{CompletedYears}(\text{date\_of\_birth}, \text{trip\_start})$$

$$\text{Daily Rate} = \text{Base Daily Rate} \times \text{Destination Multiplier} \times \text{Coverage Multiplier} \times \text{Age Multiplier}$$

$$\text{Total Premium} = \text{RoundHalfUp}(\text{Daily Rate} \times \text{Trip Days}, 2)$$

- **Inclusive Trip Days**: Both departure and return dates count. A trip from `2026-06-01` to `2026-06-01` counts as **1 day**. A trip from `2026-06-01` to `2026-06-05` counts as **5 days**.
- **Age Calculated at Trip Start**: Traveler age is calculated strictly as completed years on the date the trip begins (`trip_start`), not the quotation creation date.
- **Decimal Safety**: All intermediate multiplication uses BCMath with 4 decimal places before being rounded to 2 decimal places using half-up rounding.

### 2. Rates & Multipliers Table

#### Plans & Base Daily Rates
| Plan Code | Plan Name | Base Daily Rate |
| :--- | :--- | :--- |
| `BASIC` | Basic Protection | **$10.00** |
| `STANDARD` | Standard Protection | **$15.00** |
| `PREMIUM` | Premium Protection | **$25.00** |

#### Destination Multipliers
| Destination Code | Region Name | Multiplier |
| :--- | :--- | :--- |
| `DOMESTIC` | Domestic Travel | **1.00** |
| `ASIA` | Asia Pacific | **1.20** |
| `EUROPE` | Europe / Schengen Area | **1.50** |
| `WORLDWIDE` | Worldwide (incl. US & Canada) | **2.00** |

#### Coverage Multipliers
| Coverage Code | Scope | Multiplier |
| :--- | :--- | :--- |
| `MEDICAL_ONLY` | Medical Expenses Only | **1.00** |
| `STANDARD_COVER` | Medical + Baggage Delay + Trip Cancellation | **1.25** |
| `COMPREHENSIVE` | All Inclusive Comprehensive Cover | **1.50** |

#### Age Bands & Multipliers (Age at `trip_start`)
| Age Band Code | Age Range | Multiplier |
| :--- | :--- | :--- |
| `0_17` | 0 – 17 (Child) | **0.80** |
| `18_35` | 18 – 35 (Young Adult) | **1.00** |
| `36_50` | 36 – 50 (Adult) | **1.20** |
| `51_65` | 51 – 65 (Senior) | **1.50** |
| `66_75` | 66 – 75 (Elderly) | **2.00** |
| `76_plus` | 76+ (Super Senior) | **2.50** |

### 3. Calculation Example
- **Plan**: `STANDARD` ($15.00)
- **Destination**: `EUROPE` (1.50)
- **Coverage**: `COMPREHENSIVE` (1.50)
- **Age**: 30 years old on `trip_start` (`18_35` band $\rightarrow$ 1.00)
- **Dates**: `2026-11-01` to `2026-11-05` (5 inclusive days)

$$\text{Daily Rate} = 15.00 \times 1.50 \times 1.50 \times 1.00 = \$33.75$$
$$\text{Total Premium} = 33.75 \times 5 = \$168.75$$

### 4. Immutable Premium Snapshot
Every policy request permanently records an immutable snapshot of all inputs, rate multipliers, and calculation metadata in `premium_snapshot` (JSON):

```json
{
  "version": "1.0.0",
  "calculation_id": "calc_8a6bfbb9-c07a-463d-8e99-4dbe66f49631",
  "calculated_at": "2026-10-23T17:25:35+00:00",
  "currency": "USD",
  "traveler_age": 30,
  "age_band": "18-35 (Young Adult)",
  "trip_days": 5,
  "pricing_inputs": {
    "plan": "STANDARD",
    "destination": "EUROPE",
    "coverage_type": "COMPREHENSIVE",
    "date_of_birth": "1996-01-01",
    "trip_start": "2026-11-01",
    "trip_end": "2026-11-05"
  },
  "rates_and_multipliers": {
    "base_daily_rate": "15.00",
    "destination_multiplier": "1.50",
    "coverage_multiplier": "1.50",
    "age_multiplier": "1.00"
  },
  "breakdown": {
    "daily_premium": "33.75",
    "total_amount": "168.75"
  },
  "amount": "168.75"
}
```

---

## 🗃️ Database Schema & Architecture

The database model is architected for auditability, idempotency, and transactional consistency:

1. **`policy_requests`**:
   - `id`: Primary key
   - `policy_number`: Unique string (`POL-YYYYMMDD-XXXXXX`)
   - `agent_id`, `client_request_id`: Compound unique index `['agent_id', 'client_request_id']` enforcing idempotent submission.
   - `status`: Current lifecycle state (`QUOTED`, `PENDING_ISSUANCE`, `ISSUED`, `FAILED`, `CANCELLED`).
   - `insured_name`, `insured_email`, `date_of_birth`, `traveler_age`.
   - `destination`, `trip_start`, `trip_end`, `trip_days`.
   - `plan`, `coverage_type`, `currency`, `premium_amount`, `premium_snapshot`.

2. **`issuance_attempts`**:
   - Tracks each integration attempt with underwriter/provider APIs.
   - `policy_request_id`: Foreign key to `policy_requests.id`.
   - `attempt_number`, `provider_code`, `status`, `request_payload`, `response_payload`, `error_code`, `error_message`, `attempted_at`.

3. **`provider_events`**:
   - Webhook callback audit log with idempotency guarantee.
   - `provider_event_id`: Unique external event ID ensuring each webhook is processed at most once.
   - `policy_request_id`, `provider_code`, `event_type`, `status`, `payload`, `processed_at`.

4. **`policy_state_history`**:
   - Complete append-only audit trail of all state transitions.
   - `policy_request_id`, `from_status`, `to_status`, `trigger_event`, `actor_type`, `actor_id`, `metadata`, `created_at`.

---

## 🗄️ Database Setup (XAMPP MySQL)

### 1. Start MySQL in XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **MySQL** (running on port `3306`).

### 2. Create the Database
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS policyflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Run Migrations
```bash
php artisan migrate
```

---

## 🧪 Testing

Run all 22 tests across Unit and Feature suites:

```bash
php artisan test
```

Tests run in-memory using SQLite and verify:
- Pricing engine with all age bands, inclusive trip days, and 1-day trips.
- Strict input validation (unsupported plans/destinations/coverages, dates).
- Database foreign keys, unique policy numbers, and `(agent_id, client_request_id)` idempotency.
- Immutable premium snapshot persistence.
- CORS headers and API health/status endpoints.
