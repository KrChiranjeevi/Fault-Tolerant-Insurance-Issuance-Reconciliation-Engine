import React, { useState } from 'react';
import { PolicyRequest } from '../types';
import { StatusBadge } from './StatusBadge';
import { submitPolicy, reconcilePolicy, sendMockCallback } from '../services/api';
import { X, Send, RotateCw, History, CheckCircle, ShieldAlert, Cpu } from 'lucide-react';

interface PolicyDetailsModalProps {
  policy: PolicyRequest;
  onClose: () => void;
  onRefresh: () => void;
}

export const PolicyDetailsModal: React.FC<PolicyDetailsModalProps> = ({
  policy,
  onClose,
  onRefresh,
}) => {
  const [mockScenario, setMockScenario] = useState('SUCCESS');
  const [submitting, setSubmitting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const handleAction = async (actionFn: () => Promise<any>) => {
    setSubmitting(true);
    setActionError(null);
    setActionSuccess(null);
    try {
      await actionFn();
      setActionSuccess('Operation executed successfully!');
      onRefresh();
    } catch (err: any) {
      setActionError(err.message || 'Operation failed');
    } finally {
      setSubmitting(false);
    }
  };

  const handleSendDuplicateCallback = async () => {
    if (!policy.policy_number) {
      setActionError('Cannot send callback: policy has no policy number.');
      return;
    }
    const eventId = 'evt_' + Math.random().toString(36).substring(2, 10);
    const callbackPayload = {
      event_id: eventId,
      policy_number: policy.policy_number,
      status: 'ISSUED',
      provider_policy_id: 'PRV-' + Math.random().toString(36).substring(2, 8).toUpperCase(),
      issued_at: new Date().toISOString(),
    };

    handleAction(async () => {
      // Send once
      await sendMockCallback(callbackPayload);
      // Send duplicate
      await sendMockCallback(callbackPayload);
    });
  };

  const canSubmit = policy.status === 'RECEIVED' || policy.status === 'VALIDATED' || policy.status === 'FAILED';
  const needsReconcile = policy.status === 'NEEDS_RECONCILIATION';

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl">
        {/* Modal Header */}
        <div className="flex items-center justify-between p-4 bg-slate-800/80 border-b border-slate-700">
          <div className="flex items-center gap-3">
            <h3 className="text-lg font-bold text-slate-100">
              Policy #{policy.id} Details
            </h3>
            <StatusBadge status={policy.status} />
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 overflow-y-auto space-y-6 text-xs text-slate-300">
          {actionError && (
            <div className="p-3 bg-rose-950/70 border border-rose-500/50 rounded-lg text-rose-300 flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 text-rose-400 shrink-0" />
              <span>{actionError}</span>
            </div>
          )}

          {actionSuccess && (
            <div className="p-3 bg-emerald-950/70 border border-emerald-500/50 rounded-lg text-emerald-300 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-400 shrink-0" />
              <span>{actionSuccess}</span>
            </div>
          )}

          {/* Quick Info Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-800/50 p-4 rounded-xl border border-slate-700/60">
            <div>
              <span className="text-slate-400 block text-[11px]">Policy Number</span>
              <span className="font-mono font-bold text-slate-100 text-sm">
                {policy.policy_number || <span className="text-slate-500 italic">Not yet issued</span>}
              </span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Client Request ID</span>
              <span className="font-mono text-slate-200">{policy.client_request_id}</span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Agent ID</span>
              <span className="font-mono text-slate-200">{policy.agent_id}</span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Customer Name</span>
              <span className="font-semibold text-slate-100">{policy.customer_name || (policy as any).insured_name}</span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Plan & Destination</span>
              <span className="font-semibold text-blue-400">{policy.plan_code || (policy as any).plan}</span> / {policy.destination}
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Coverage (Sum Insured)</span>
              <span className="font-semibold text-slate-200">
                {policy.currency === 'USD' ? '$' : '₹'}{Number(policy.sum_insured || (policy as any).coverage_type || 0).toLocaleString('en-IN')}
              </span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Trip Dates</span>
              <span className="text-slate-200 font-mono">{policy.trip_start} to {policy.trip_end}</span>
            </div>
            <div>
              <span className="text-slate-400 block text-[11px]">Authoritative Premium</span>
              <span className="font-bold text-emerald-400 text-sm font-mono">
                {policy.currency === 'USD' ? '$' : '₹'}{Number(policy.premium_amount).toFixed(2)} {policy.currency || 'INR'}
              </span>
            </div>
          </div>

          {/* Authoritative Premium Snapshot Breakdown */}
          {policy.premium_snapshot && (
            <div className="bg-slate-800/30 p-4 rounded-xl border border-slate-700/40">
              <h4 className="font-bold text-slate-200 mb-2 flex items-center gap-1.5">
                <Cpu className="w-4 h-4 text-emerald-400" /> Immutable Pricing Calculation Snapshot
              </h4>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-[11px] font-mono">
                <div className="bg-slate-900/60 p-2 rounded border border-slate-800">
                  <span className="text-slate-400 block">Trip Days (Inclusive)</span>
                  <span className="text-slate-200 font-bold">{policy.premium_snapshot.trip_days}</span>
                </div>
                <div className="bg-slate-900/60 p-2 rounded border border-slate-800">
                  <span className="text-slate-400 block">Age at Trip Start</span>
                  <span className="text-slate-200 font-bold">{policy.premium_snapshot.age_at_trip_start ?? (policy.premium_snapshot as any).traveler_age} yrs</span>
                </div>
                <div className="bg-slate-900/60 p-2 rounded border border-slate-800">
                  <span className="text-slate-400 block">Base Daily Rate</span>
                  <span className="text-slate-200 font-bold">
                    {policy.currency === 'USD' ? '$' : '₹'}{(policy.premium_snapshot as any).rates_and_multipliers?.base_daily_rate ?? policy.premium_snapshot.base_daily_rate ?? '-'}
                  </span>
                </div>
                <div className="bg-slate-900/60 p-2 rounded border border-slate-800">
                  <span className="text-slate-400 block">Multipliers (Age × Dest × Cov)</span>
                  <span className="text-slate-200">
                    {(policy.premium_snapshot as any).rates_and_multipliers?.age_multiplier ?? policy.premium_snapshot.age_multiplier ?? '1.00'} × {(policy.premium_snapshot as any).rates_and_multipliers?.destination_multiplier ?? policy.premium_snapshot.destination_multiplier ?? '1.00'} × {(policy.premium_snapshot as any).rates_and_multipliers?.coverage_multiplier ?? policy.premium_snapshot.coverage_multiplier ?? '1.00'}
                  </span>
                </div>
              </div>
            </div>
          )}

          {/* Actions & Mock Insurer Simulation Controls */}
          <div className="bg-slate-800/60 p-4 rounded-xl border border-slate-700">
            <h4 className="font-bold text-slate-200 mb-3 flex items-center gap-2">
              <Send className="w-4 h-4 text-blue-400" /> Insurer Submission & Edge Case Simulator
            </h4>

            <div className="flex flex-wrap items-center gap-3">
              {canSubmit && (
                <>
                  <div className="flex items-center gap-2">
                    <span className="text-slate-400 text-xs">Scenario:</span>
                    <select
                      value={mockScenario}
                      onChange={e => setMockScenario(e.target.value)}
                      className="bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 text-xs"
                    >
                      <option value="SUCCESS">SUCCESS (Synchronous)</option>
                      <option value="TEMPORARY_FAILURE">TEMPORARY_FAILURE (Retryable)</option>
                      <option value="TIMEOUT_AFTER_ISSUE">TIMEOUT_AFTER_ISSUE (Reconciliation)</option>
                      <option value="DELAYED_CALLBACK">DELAYED_CALLBACK (Async)</option>
                      <option value="DUPLICATE_CALLBACK">DUPLICATE_CALLBACK (Idempotency)</option>
                    </select>
                  </div>

                  <button
                    onClick={() => handleAction(() => submitPolicy(policy.id, mockScenario))}
                    disabled={submitting}
                    className="px-4 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold rounded shadow transition flex items-center gap-1.5"
                  >
                    <Send className="w-3.5 h-3.5" />
                    {submitting ? 'Submitting...' : policy.status === 'FAILED' ? 'Retry Issuance' : 'Submit to Insurer'}
                  </button>
                </>
              )}

              {needsReconcile && (
                <button
                  onClick={() => handleAction(() => reconcilePolicy(policy.id))}
                  disabled={submitting}
                  className="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white font-semibold rounded shadow transition flex items-center gap-1.5"
                >
                  <RotateCw className="w-3.5 h-3.5" />
                  {submitting ? 'Reconciling...' : 'Poll Reconciliation Endpoint'}
                </button>
              )}

              {policy.status === 'ISSUED' && (
                <button
                  onClick={handleSendDuplicateCallback}
                  disabled={submitting}
                  className="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded font-medium transition flex items-center gap-1.5"
                >
                  <RotateCw className="w-3.5 h-3.5 text-amber-400" />
                  Test Duplicate Callback (No Regression)
                </button>
              )}
            </div>
          </div>

          {/* Issuance Attempts */}
          <div className="space-y-2">
            <h4 className="font-bold text-slate-200 flex items-center gap-1.5">
              <RotateCw className="w-4 h-4 text-blue-400" /> Issuance Attempts ({policy.issuance_attempts?.length || 0})
            </h4>
            {(!policy.issuance_attempts || policy.issuance_attempts.length === 0) ? (
              <p className="text-slate-500 italic text-[11px]">No issuance attempts recorded yet.</p>
            ) : (
              <div className="space-y-2">
                {policy.issuance_attempts.map(att => (
                  <div key={att.id} className="bg-slate-800/40 border border-slate-700/60 rounded-lg p-3 text-[11px] font-mono">
                    <div className="flex justify-between items-center mb-1">
                      <span className="font-bold text-slate-200">Attempt #{att.attempt_number} ({att.provider_code})</span>
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                        att.status === 'CALLBACK_DELIVERED' || att.status === 'ISSUED'
                          ? 'bg-emerald-950 text-emerald-400 border border-emerald-700/50'
                          : att.status === 'TEMPORARY_FAILURE'
                          ? 'bg-rose-950 text-rose-400 border border-rose-700/50'
                          : 'bg-amber-950 text-amber-400 border border-amber-700/50'
                      }`}>
                        {att.status}
                      </span>
                    </div>
                    {att.error_message && (
                      <div className="text-rose-400 mb-1">Error: {att.error_message} ({att.error_code})</div>
                    )}
                    <div className="text-slate-500 text-[10px]">{att.attempted_at}</div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Audit Timeline / State History */}
          <div className="space-y-2">
            <h4 className="font-bold text-slate-200 flex items-center gap-1.5">
              <History className="w-4 h-4 text-blue-400" /> State Transition Audit Timeline
            </h4>
            <div className="border-l-2 border-slate-700 ml-2 pl-4 space-y-3">
              {(policy.state_histories || (policy as any).state_history)?.map((h: any, i: number) => (
                <div key={i} className="relative text-[11px]">
                  <div className="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full bg-blue-500 ring-4 ring-slate-900" />
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-slate-200 font-mono">
                      {h.from_status || 'INIT'} &rarr; {h.to_status}
                    </span>
                    <span className="text-[10px] text-slate-500">{h.transitioned_at || h.created_at}</span>
                  </div>
                  <div className="text-slate-400 text-[10px]">
                    Trigger: <span className="font-mono text-blue-300">{h.trigger_event}</span> | Actor: {h.actor_type}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Modal Footer */}
        <div className="p-4 bg-slate-800/80 border-t border-slate-700 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded-lg text-xs font-semibold transition"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};
