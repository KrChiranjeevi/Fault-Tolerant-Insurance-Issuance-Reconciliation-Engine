import React from 'react';
import { PolicyStatus } from '../types';

interface StatusBadgeProps {
  status: PolicyStatus;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status }) => {
  const getBadgeStyle = () => {
    switch (status) {
      case 'ISSUED':
        return 'bg-emerald-950/80 text-emerald-400 border-emerald-500/40 ring-1 ring-emerald-500/20';
      case 'PENDING_CONFIRMATION':
        return 'bg-amber-950/80 text-amber-400 border-amber-500/40 ring-1 ring-amber-500/20';
      case 'NEEDS_RECONCILIATION':
        return 'bg-purple-950/80 text-purple-400 border-purple-500/40 ring-1 ring-purple-500/20 animate-pulse';
      case 'FAILED':
        return 'bg-rose-950/80 text-rose-400 border-rose-500/40 ring-1 ring-rose-500/20';
      case 'SUBMITTED':
        return 'bg-blue-950/80 text-blue-400 border-blue-500/40 ring-1 ring-blue-500/20';
      case 'VALIDATED':
        return 'bg-cyan-950/80 text-cyan-400 border-cyan-500/40 ring-1 ring-cyan-500/20';
      case 'RECEIVED':
      default:
        return 'bg-slate-800 text-slate-300 border-slate-700';
    }
  };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold tracking-wide border ${getBadgeStyle()}`}>
      <span className="w-1.5 h-1.5 rounded-full mr-1.5 bg-current opacity-80" />
      {status.replace('_', ' ')}
    </span>
  );
};
