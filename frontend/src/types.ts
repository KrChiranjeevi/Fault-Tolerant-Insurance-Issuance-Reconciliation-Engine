export type PolicyStatus =
  | 'RECEIVED'
  | 'VALIDATED'
  | 'SUBMITTED'
  | 'PENDING_CONFIRMATION'
  | 'ISSUED'
  | 'FAILED'
  | 'NEEDS_RECONCILIATION';

export interface StateHistory {
  id: number;
  from_status: PolicyStatus | null;
  to_status: PolicyStatus;
  trigger_event: string;
  actor_type: string;
  actor_id?: string | null;
  metadata?: Record<string, any>;
  transitioned_at: string;
}

export interface IssuanceAttempt {
  id: number;
  attempt_number: number;
  provider_code: string;
  status: string;
  request_payload?: Record<string, any>;
  response_payload?: Record<string, any>;
  error_code?: string | null;
  error_message?: string | null;
  attempted_at: string;
}

export interface ProviderEvent {
  id: number;
  provider_event_id: string;
  provider_code: string;
  event_type: string;
  status: string;
  payload?: Record<string, any>;
  processed_at: string;
}

export interface PremiumSnapshot {
  calculated_at?: string;
  trip_days?: number;
  age_at_trip_start?: number;
  base_daily_rate?: string | number;
  age_multiplier?: string | number;
  destination_multiplier?: string | number;
  coverage_multiplier?: string | number;
  currency?: string;
  calculated_premium?: string | number;
  inputs?: Record<string, any>;
}

export interface PolicyRequest {
  id: number;
  client_request_id: string;
  agent_id: string;
  policy_number?: string | null;
  status: PolicyStatus;
  customer_name: string;
  date_of_birth: string;
  destination: string;
  trip_start: string;
  trip_end: string;
  plan_code: string;
  sum_insured: number;
  premium_amount: string | number;
  currency: string;
  premium_snapshot?: PremiumSnapshot | null;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
  state_histories?: StateHistory[];
  issuance_attempts?: IssuanceAttempt[];
  provider_events?: ProviderEvent[];
}

export interface PolicyFormData {
  client_request_id: string;
  agent_id: string;
  customer_name: string;
  date_of_birth: string;
  destination: string;
  trip_start: string;
  trip_end: string;
  plan_code: string;
  sum_insured: number;
}
