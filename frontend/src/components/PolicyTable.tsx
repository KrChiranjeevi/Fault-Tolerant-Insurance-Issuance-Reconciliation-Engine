import React from 'react';
import { PolicyRequest } from '../types';
import { StatusBadge } from './StatusBadge';
import { Eye, Shield, AlertTriangle } from 'lucide-react';

interface PolicyTableProps {
  policies: PolicyRequest[];
  loading: boolean;
  selectedStatus: string;
  onStatusChange: (status: string) => void;
  onSelectPolicy: (policy: PolicyRequest) => void;
  onRefresh: () => void;
}

export const PolicyTable: React.FC<PolicyTableProps> = ({
  policies,
  loading,
  selectedStatus,
  onStatusChange,
  onSelectPolicy,
  onRefresh,
}) => {
  const statusOptions = [
    'ALL',
    'RECEIVED',
    'VALIDATED',
    'SUBMITTED',
    'PENDING_CONFIRMATION',
    'ISSUED',
    'NEEDS_RECONCILIATION',
    'FAILED',
  ];

  return (
    <div className="bg-slate-800/80 border border-slate-700 rounded-xl p-5 shadow-xl backdrop-blur flex flex-col h-full">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-700/60 pb-3 mb-4">
        <div>
          <h2 className="text-lg font-bold text-slate-100 flex items-center gap-2">
            <Shield className="w-5 h-5 text-blue-400" />
            Policy Repository
          </h2>
          <p className="text-xs text-slate-400">Real-time state tracking and lifecycle audit</p>
        </div>

        {/* Filter Badges */}
        <div className="flex flex-wrap gap-1.5 text-xs">
          {statusOptions.map(st => (
            <button
              key={st}
              onClick={() => onStatusChange(st)}
              className={`px-2.5 py-1 rounded-md font-medium transition ${
                selectedStatus === st
                  ? 'bg-blue-600 text-white shadow'
                  : 'bg-slate-700/60 hover:bg-slate-700 text-slate-300'
              }`}
            >
              {st.replace('_', ' ')}
            </button>
          ))}
        </div>
      </div>

      {loading ? (
        <div className="py-16 text-center text-slate-400 text-sm animate-pulse">
          Loading policies from server...
        </div>
      ) : policies.length === 0 ? (
        <div className="py-16 text-center text-slate-400 text-sm flex flex-col items-center justify-center">
          <AlertTriangle className="w-8 h-8 text-slate-500 mb-2" />
          <p>No policy requests found matching criteria.</p>
          <span className="text-xs text-slate-500 mt-1">Submit a new policy request using the intake form.</span>
        </div>
      ) : (
        <div className="overflow-x-auto flex-1">
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-900/60 text-slate-400 uppercase tracking-wider text-[11px] border-b border-slate-700">
              <tr>
                <th className="py-2.5 px-3">ID / Policy #</th>
                <th className="py-2.5 px-3">Customer</th>
                <th className="py-2.5 px-3">Plan / Dest</th>
                <th className="py-2.5 px-3">Trip Period</th>
                <th className="py-2.5 px-3">Premium</th>
                <th className="py-2.5 px-3">Status</th>
                <th className="py-2.5 px-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-700/50 text-slate-200">
              {policies.map(p => (
                <tr key={p.id} className="hover:bg-slate-700/30 transition">
                  <td className="py-3 px-3 font-mono">
                    <div className="font-semibold text-slate-100">#{p.id}</div>
                    <div className="text-[11px] text-slate-400">
                      {p.policy_number || <span className="italic text-slate-500">Unissued</span>}
                    </div>
                  </td>
                  <td className="py-3 px-3">
                    <div className="font-medium">{p.customer_name || (p as any).insured_name}</div>
                    <div className="text-[11px] text-slate-400 font-mono">DOB: {p.date_of_birth}</div>
                  </td>
                  <td className="py-3 px-3">
                    <span className="font-semibold text-blue-400">{p.plan_code || (p as any).plan}</span>
                    <div className="text-[11px] text-slate-400">{p.destination}</div>
                  </td>
                  <td className="py-3 px-3 font-mono text-[11px]">
                    <div>{p.trip_start}</div>
                    <div className="text-slate-400">to {p.trip_end}</div>
                  </td>
                  <td className="py-3 px-3">
                    <div className="font-bold text-emerald-400 font-mono">
                      {p.currency === 'USD' ? '$' : '₹'}{Number(p.premium_amount).toFixed(2)}
                    </div>
                    <div className="text-[10px] text-slate-400">
                      Cover: {p.currency === 'USD' ? '$' : '₹'}{Number(p.sum_insured || (p as any).coverage_type || 0).toLocaleString('en-IN')}
                    </div>
                  </td>
                  <td className="py-3 px-3">
                    <StatusBadge status={p.status} />
                  </td>
                  <td className="py-3 px-3 text-right">
                    <button
                      onClick={() => onSelectPolicy(p)}
                      className="px-2.5 py-1 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded font-medium inline-flex items-center gap-1 shadow transition"
                    >
                      <Eye className="w-3.5 h-3.5 text-blue-400" /> Details
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};
