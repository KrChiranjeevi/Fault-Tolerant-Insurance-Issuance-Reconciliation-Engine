import React, { useState } from 'react';
import { PolicyFormData } from '../types';
import { createPolicy } from '../services/api';
import { Calculator, Send, RefreshCw, CheckCircle2, AlertCircle } from 'lucide-react';

interface PolicyFormProps {
  onPolicyCreated: () => void;
}

export const PolicyForm: React.FC<PolicyFormProps> = ({ onPolicyCreated }) => {
  const [formData, setFormData] = useState<PolicyFormData>({
    client_request_id: 'REQ-' + Math.random().toString(36).substring(2, 9).toUpperCase(),
    agent_id: 'AGT-001',
    customer_name: 'Rahul Sharma',
    date_of_birth: '1998-04-10',
    destination: 'INTERNATIONAL',
    trip_start: new Date(Date.now() + 86400000 * 5).toISOString().split('T')[0],
    trip_end: new Date(Date.now() + 86400000 * 15).toISOString().split('T')[0],
    plan_code: 'PLUS',
    sum_insured: 300000,
  });

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const calculateDays = () => {
    if (!formData.trip_start || !formData.trip_end) return 0;
    const start = new Date(formData.trip_start);
    const end = new Date(formData.trip_end);
    const diff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 0;
  };

  const calculateAge = () => {
    if (!formData.date_of_birth || !formData.trip_start) return 0;
    const dob = new Date(formData.date_of_birth);
    const start = new Date(formData.trip_start);
    let age = start.getFullYear() - dob.getFullYear();
    const m = start.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && start.getDate() < dob.getDate())) {
      age--;
    }
    return age;
  };

  const tripDays = calculateDays();
  const travelerAge = calculateAge();

  const getEstimatedQuote = () => {
    const baseRates: Record<string, number> = { BASIC: 12, PLUS: 20, PREMIUM: 30 };
    const destMults: Record<string, number> = { DOMESTIC: 1.0, INTERNATIONAL: 1.8 };
    const covMults: Record<number, number> = { 100000: 1.0, 300000: 1.25, 500000: 1.5 };

    const base = baseRates[formData.plan_code] || 20;
    const dest = destMults[formData.destination] || 1.8;
    const cov = covMults[formData.sum_insured] || 1.25;

    let ageMult = 1.0;
    if (travelerAge <= 17) ageMult = 1.1;
    else if (travelerAge <= 60) ageMult = 1.0;
    else if (travelerAge <= 70) ageMult = 1.5;
    else ageMult = 2.0;

    return tripDays * base * dest * cov * ageMult;
  };

  const estimatedTotal = getEstimatedQuote();

  const handleRegenerateId = () => {
    setFormData(prev => ({
      ...prev,
      client_request_id: 'REQ-' + Math.random().toString(36).substring(2, 9).toUpperCase(),
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const res = await createPolicy(formData);
      setSuccess(`Policy created successfully! ID: #${res.data.id} (${res.data.status}) • Authoritative Premium: ₹${Number(res.data.premium_amount).toFixed(2)}`);
      onPolicyCreated();
      handleRegenerateId();
    } catch (err: any) {
      setError(err.message || 'Failed to intake policy');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-slate-800/80 border border-slate-700 rounded-xl p-5 shadow-xl backdrop-blur">
      <div className="flex items-center justify-between border-b border-slate-700/60 pb-3 mb-4">
        <div>
          <h2 className="text-lg font-bold text-slate-100 flex items-center gap-2">
            <Calculator className="w-5 h-5 text-blue-400" />
            New Policy Intake
          </h2>
          <p className="text-xs text-slate-400">Deterministic pricing engine with idempotent deduplication</p>
        </div>
        <button
          type="button"
          onClick={handleRegenerateId}
          className="text-xs text-blue-400 hover:text-blue-300 flex items-center gap-1 bg-slate-700/50 px-2 py-1 rounded"
          title="Generate new client request ID"
        >
          <RefreshCw className="w-3 h-3" /> New Req ID
        </button>
      </div>

      {error && (
        <div className="mb-4 p-3 bg-rose-950/60 border border-rose-500/50 rounded-lg text-rose-300 text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 text-rose-400 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {success && (
        <div className="mb-4 p-3 bg-emerald-950/60 border border-emerald-500/50 rounded-lg text-emerald-300 text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
          <span>{success}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
          <div>
            <label className="block text-slate-300 font-medium mb-1">Client Request ID (Idempotency Key)</label>
            <input
              type="text"
              required
              value={formData.client_request_id}
              onChange={e => setFormData({ ...formData, client_request_id: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 font-mono focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Agent ID</label>
            <input
              type="text"
              required
              value={formData.agent_id}
              onChange={e => setFormData({ ...formData, agent_id: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 font-mono focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Customer Name</label>
            <input
              type="text"
              required
              value={formData.customer_name}
              onChange={e => setFormData({ ...formData, customer_name: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Date of Birth</label>
            <input
              type="date"
              required
              value={formData.date_of_birth}
              onChange={e => setFormData({ ...formData, date_of_birth: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Destination</label>
            <select
              value={formData.destination}
              onChange={e => setFormData({ ...formData, destination: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            >
              <option value="DOMESTIC">Domestic (1.00x)</option>
              <option value="INTERNATIONAL">International (1.80x)</option>
            </select>
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Plan Code</label>
            <select
              value={formData.plan_code}
              onChange={e => setFormData({ ...formData, plan_code: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            >
              <option value="BASIC">Basic (₹12/day)</option>
              <option value="PLUS">Plus (₹20/day)</option>
              <option value="PREMIUM">Premium (₹30/day)</option>
            </select>
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Trip Start Date</label>
            <input
              type="date"
              required
              value={formData.trip_start}
              onChange={e => setFormData({ ...formData, trip_start: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Trip End Date</label>
            <input
              type="date"
              required
              value={formData.trip_end}
              onChange={e => setFormData({ ...formData, trip_end: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div className="md:col-span-2">
            <label className="block text-slate-300 font-medium mb-1">Sum Insured (INR - ₹)</label>
            <select
              value={formData.sum_insured}
              onChange={e => setFormData({ ...formData, sum_insured: parseInt(e.target.value, 10) })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            >
              <option value={100000}>₹100,000 (Base 1.00x)</option>
              <option value={300000}>₹300,000 (1.25x)</option>
              <option value={500000}>₹500,000 (1.50x)</option>
            </select>
          </div>
        </div>

        {/* Live quote preview */}
        <div className="bg-slate-900/90 border border-slate-700/80 rounded-lg p-3 text-xs flex flex-wrap justify-between items-center gap-2">
          <div>
            <span className="text-slate-400">Calculated Metrics: </span>
            <span className="text-blue-400 font-semibold">{tripDays} Days (Inclusive)</span>
            <span className="text-slate-500 mx-2">|</span>
            <span className="text-slate-400">Age: </span>
            <span className="text-blue-400 font-semibold">{travelerAge} yrs</span>
            <span className="text-slate-500 mx-2">|</span>
            <span className="text-slate-400">Est. Quote: </span>
            <span className="text-emerald-400 font-bold">₹{estimatedTotal.toFixed(2)}</span>
          </div>
          <span className="text-[11px] text-slate-500 italic">Authoritative snapshot on submit</span>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full py-2 px-4 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-semibold rounded-lg shadow transition flex items-center justify-center gap-2 text-sm"
        >
          {loading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
          {loading ? 'Creating Policy...' : 'Submit Policy Request'}
        </button>
      </form>
    </div>
  );
};
