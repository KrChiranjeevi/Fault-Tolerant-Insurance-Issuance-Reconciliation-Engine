import { PolicyFormData, PolicyRequest, PolicyStatus } from '../types';

const API_BASE = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

export async function fetchPolicies(page = 1, status?: string): Promise<{ data: PolicyRequest[]; meta?: any }> {
  const url = new URL(`${API_BASE}/policy-requests`);
  url.searchParams.set('page', page.toString());
  if (status && status !== 'ALL') {
    url.searchParams.set('status', status);
  }

  const res = await fetch(url.toString(), {
    headers: { 'Accept': 'application/json' }
  });
  if (!res.ok) throw new Error(`Failed to fetch policies: ${res.statusText}`);
  return res.json();
}

export async function fetchPolicy(id: number): Promise<{ data: PolicyRequest }> {
  const res = await fetch(`${API_BASE}/policy-requests/${id}`, {
    headers: { 'Accept': 'application/json' }
  });
  if (!res.ok) throw new Error(`Failed to fetch policy: ${res.statusText}`);
  return res.json();
}

export async function createPolicy(data: PolicyFormData): Promise<{ data: PolicyRequest; message?: string }> {
  const res = await fetch(`${API_BASE}/policy-requests`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(data)
  });

  const body = await res.json();
  if (!res.ok) {
    const errorMsg = body.message || (body.errors ? Object.values(body.errors).flat().join(', ') : 'Creation failed');
    throw new Error(errorMsg);
  }
  return body;
}

export async function submitPolicy(id: number, mockScenario = 'SUCCESS'): Promise<{ data: PolicyRequest }> {
  const res = await fetch(`${API_BASE}/policy-requests/${id}/submit`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ mock_scenario: mockScenario })
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Submission failed');
  }
  return body;
}

export async function reconcilePolicy(id: number): Promise<{ data: PolicyRequest }> {
  const res = await fetch(`${API_BASE}/policy-requests/${id}/reconcile`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Reconciliation failed');
  }
  return body;
}

export async function sendMockCallback(payload: any): Promise<any> {
  const res = await fetch(`${API_BASE}/mock-insurer/callback`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  const body = await res.json();
  if (!res.ok) {
    throw new Error(body.message || 'Callback failed');
  }
  return body;
}
