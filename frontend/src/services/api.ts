import { PolicyFormData, PolicyRequest } from '../types';

const API_BASE = import.meta.env.VITE_API_BASE_URL || '/api';

function buildUrl(path: string, params?: Record<string, string>): string {
  const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:5173';
  const base = API_BASE.startsWith('http') ? API_BASE : `${origin}${API_BASE.startsWith('/') ? '' : '/'}${API_BASE}`;
  const url = new URL(`${base}${path.startsWith('/') ? '' : '/'}${path}`);
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        url.searchParams.set(k, v);
      }
    });
  }
  return url.toString();
}

export async function checkHealth(): Promise<boolean> {
  try {
    const res = await fetch(buildUrl('/health'), {
      headers: { 'Accept': 'application/json' },
      signal: AbortSignal.timeout(10000),
    });
    return res.ok;
  } catch {
    return false;
  }
}

export async function fetchPolicies(page = 1, status?: string): Promise<{ data: PolicyRequest[]; meta?: any }> {
  const params: Record<string, string> = { page: page.toString() };
  if (status && status !== 'ALL') {
    params.status = status;
  }
  const url = buildUrl('/policy-requests', params);

  const res = await fetch(url, {
    headers: { 'Accept': 'application/json' },
    signal: AbortSignal.timeout(15000),
  });
  if (!res.ok) throw new Error(`Failed to fetch policies: ${res.statusText}`);
  return res.json();
}

export async function fetchPolicy(id: number): Promise<{ data: PolicyRequest }> {
  const res = await fetch(buildUrl(`/policy-requests/${id}`), {
    headers: { 'Accept': 'application/json' },
    signal: AbortSignal.timeout(15000),
  });
  if (!res.ok) throw new Error(`Failed to fetch policy: ${res.statusText}`);
  return res.json();
}

export async function createPolicy(data: PolicyFormData): Promise<{ data: PolicyRequest; message?: string }> {
  const res = await fetch(buildUrl('/policy-requests'), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(data),
    signal: AbortSignal.timeout(10000),
  });

  const body = await res.json();
  if (!res.ok) {
    const errorMsg = body.message || (body.errors ? Object.values(body.errors).flat().join(', ') : 'Creation failed');
    throw new Error(errorMsg);
  }
  return body;
}

export async function submitPolicy(id: number, mockScenario = 'SUCCESS'): Promise<{ data: PolicyRequest }> {
  const res = await fetch(buildUrl(`/policy-requests/${id}/submit`), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ mock_scenario: mockScenario }),
    signal: AbortSignal.timeout(12000),
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Submission failed');
  }
  return body;
}

export async function reconcilePolicy(id: number): Promise<{ data: PolicyRequest }> {
  const res = await fetch(buildUrl(`/policy-requests/${id}/reconcile`), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    signal: AbortSignal.timeout(12000),
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Reconciliation failed');
  }
  return body;
}

export async function sendMockCallback(payload: any): Promise<any> {
  const res = await fetch(buildUrl('/mock-insurer/callback'), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(10000),
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Callback failed');
  }
  return body;
}
