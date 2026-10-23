import React from 'react';
import { PolicyRequest } from '../types';
import { ShieldCheck, Clock, AlertTriangle, AlertCircle, FileText } from 'lucide-react';

interface OverviewCardsProps {
  policies: PolicyRequest[];
}

export const OverviewCards: React.FC<OverviewCardsProps> = ({ policies }) => {
  const total = policies.length;
  const issued = policies.filter(p => p.status === 'ISSUED').length;
  const pending = policies.filter(p => p.status === 'PENDING_CONFIRMATION' || p.status === 'SUBMITTED').length;
  const reconciliation = policies.filter(p => p.status === 'NEEDS_RECONCILIATION').length;
  const failed = policies.filter(p => p.status === 'FAILED').length;

  const cards = [
    { label: 'Total Policies', value: total, icon: FileText, color: 'text-slate-400', bg: 'bg-slate-800/60' },
    { label: 'Issued (Active)', value: issued, icon: ShieldCheck, color: 'text-emerald-400', bg: 'bg-emerald-950/40' },
    { label: 'In Flight / Pending', value: pending, icon: Clock, color: 'text-amber-400', bg: 'bg-amber-950/40' },
    { label: 'Needs Reconciliation', value: reconciliation, icon: AlertTriangle, color: 'text-purple-400', bg: 'bg-purple-950/40' },
    { label: 'Failed (Retryable)', value: failed, icon: AlertCircle, color: 'text-rose-400', bg: 'bg-rose-950/40' },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
      {cards.map((card, idx) => {
        const Icon = card.icon;
        return (
          <div key={idx} className={`${card.bg} border border-slate-700/60 rounded-xl p-4 flex flex-col justify-between shadow-lg backdrop-blur`}>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-medium text-slate-400">{card.label}</span>
              <Icon className={`w-4 h-4 ${card.color}`} />
            </div>
            <div className={`text-2xl font-bold tracking-tight ${card.color}`}>
              {card.value}
            </div>
          </div>
        );
      })}
    </div>
  );
};
