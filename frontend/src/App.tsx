import React, { useEffect, useState } from 'react';
import { PolicyRequest } from './types';
import { fetchPolicies, fetchPolicy } from './services/api';
import { OverviewCards } from './components/OverviewCards';
import { PolicyForm } from './components/PolicyForm';
import { PolicyTable } from './components/PolicyTable';
import { PolicyDetailsModal } from './components/PolicyDetailsModal';
import { Shield, RefreshCw, CheckCircle2, XCircle } from 'lucide-react';

export function App() {
  const [policies, setPolicies] = useState<PolicyRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('ALL');
  const [selectedPolicy, setSelectedPolicy] = useState<PolicyRequest | null>(null);
  const [apiConnected, setApiConnected] = useState<boolean | null>(null);

  const loadData = async () => {
    setLoading(true);
    try {
      const res = await fetchPolicies(1, statusFilter);
      setPolicies(res.data || []);
      setApiConnected(true);
    } catch (err) {
      console.error(err);
      setApiConnected(false);
    } finally {
      setLoading(false);
    }
  };

  const handleSelectPolicy = async (p: PolicyRequest) => {
    try {
      const full = await fetchPolicy(p.id);
      setSelectedPolicy(full.data);
    } catch {
      setSelectedPolicy(p);
    }
  };

  const handleRefreshSelected = async () => {
    if (selectedPolicy) {
      try {
        const full = await fetchPolicy(selectedPolicy.id);
        setSelectedPolicy(full.data);
      } catch (err) {
        console.error(err);
      }
    }
    loadData();
  };

  useEffect(() => {
    loadData();
  }, [statusFilter]);

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans">
      {/* Navbar Header */}
      <header className="bg-slate-900 border-b border-slate-800 sticky top-0 z-40 px-6 py-3 shadow-md">
        <div className="max-w-7xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-600/20 border border-blue-500/30 rounded-xl text-blue-400">
              <Shield className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-lg font-extrabold tracking-tight text-white flex items-center gap-2">
                TRIPPERS SHIELD
                <span className="text-[10px] font-semibold bg-blue-500/20 text-blue-300 border border-blue-400/30 px-2 py-0.5 rounded-full uppercase tracking-wider">
                  PolicyFlow Engine
                </span>
              </h1>
              <p className="text-xs text-slate-400">Travel Insurance Issuance & Reconciliation Operations</p>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2 text-xs">
              <span className="text-slate-400">API Gateway:</span>
              {apiConnected === true && (
                <span className="inline-flex items-center gap-1 text-emerald-400 font-medium">
                  <CheckCircle2 className="w-3.5 h-3.5" /> Connected
                </span>
              )}
              {apiConnected === false && (
                <span className="inline-flex items-center gap-1 text-rose-400 font-medium">
                  <XCircle className="w-3.5 h-3.5" /> Disconnected
                </span>
              )}
              {apiConnected === null && (
                <span className="text-slate-500">Checking...</span>
              )}
            </div>

            <button
              onClick={loadData}
              disabled={loading}
              className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg transition border border-slate-700"
              title="Refresh Data"
            >
              <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin text-blue-400' : ''}`} />
            </button>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">
        {/* Overview Stats */}
        <OverviewCards policies={policies} />

        {/* Dashboard Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          {/* Left Column: Policy Intake Form (4 cols) */}
          <div className="lg:col-span-5">
            <PolicyForm onPolicyCreated={loadData} />
          </div>

          {/* Right Column: Policy Repository Table (7 cols) */}
          <div className="lg:col-span-7">
            <PolicyTable
              policies={policies}
              loading={loading}
              selectedStatus={statusFilter}
              onStatusChange={setStatusFilter}
              onSelectPolicy={handleSelectPolicy}
              onRefresh={loadData}
            />
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-slate-800/80 py-4 text-center text-xs text-slate-500">
        Trippers Shield &copy; 2026 PolicyFlow Architecture &bull; Local Deterministic Mock Insurer Operations
      </footer>

      {/* Details Modal */}
      {selectedPolicy && (
        <PolicyDetailsModal
          policy={selectedPolicy}
          onClose={() => setSelectedPolicy(null)}
          onRefresh={handleRefreshSelected}
        />
      )}
    </div>
  );
}

export default App;
