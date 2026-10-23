import React, { useState, useId } from 'react';
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
    customer_name: 'Jane Doe',
    date_of_birth: '1995-04-12',
    destination: 'SCHENGEN',
    trip_start: new Date(Date.now() + 86400000 * 5).toISOString().split('T')[0],
    trip_end: new Date(Date.now() + 86400000 * 15).toISOString().split('T')[0],
    plan_code: 'GOLD',
    sum_insured: 100000,
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
      setSuccess(`Policy created successfully! ID: ${res.data.id} (${res.data.status})`);
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
              <option value="ASIA">Asia (Base 1.0x)</option>
              <option value="EUROPE">Europe (1.2x)</option>
              <option value="SCHENGEN">Schengen (1.25x)</option>
              <option value="USA_CANADA">USA & Canada (1.5x)</option>
              <option value="WORLDWIDE">Worldwide (1.6x)</option>
            </select>
          </div>

          <div>
            <label className="block text-slate-300 font-medium mb-1">Plan Code</label>
            <select
              value={formData.plan_code}
              onChange={e => setFormData({ ...formData, plan_code: e.target.value })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            >
              <option value="SILVER">Silver ($15/day)</option>
              <option value="GOLD">Gold ($25/day)</option>
              <option value="PLATINUM">Platinum ($40/day)</option>
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
            <label className="block text-slate-300 font-medium mb-1">Sum Insured (USD)</label>
            <select
              value={formData.sum_insured}
              onChange={e => setFormData({ ...formData, sum_insured: parseInt(e.target.value, 10) })}
              className="w-full bg-slate-900 border border-slate-700 rounded px-2.5 py-1.5 text-slate-100 focus:ring-1 focus:ring-blue-500"
            >
              <option value={50000}>$50,000 (Base 1.0x)</option>
              <option value={100000}>$100,000 (1.15x)</option>
              <option value={250000}>$250,000 (1.30x)</option>
              <option value={500000}>$500,000 (1.50x)</option>
            </select>
          </div>
        </div>

        {/* Live quote preview */}
        <div className="bg-slate-900/90 border border-slate-700/80 rounded-lg p-3 text-xs flex justify-between items-center">
          <div>
            <span className="text-slate-400">Calculated Metrics: </span>
            <span className="text-blue-400 font-semibold">{tripDays} Days (Inclusive)</span>
            <span className="text-slate-500 mx-2">|</span>
            <span className="text-slate-400">Age: </span>
            <span className="text-blue-400 font-semibold">{travelerAge} yrs</span>
          </div>
          <span className="text-[11px] text-slate-500 italic">Backend serves authoritative immutable snapshot</span>
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
