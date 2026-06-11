// Thin typed client for the Laravel API. Bearer token is stored client-side
// (localStorage) — the Anthropic key and DB creds live only on the server.

import type {
  AdminStats,
  AuthResponse,
  CreateSummaryInput,
  Paginated,
  Summary,
  SummaryStatus,
  User,
} from "./types";

const BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000";

const TOKEN_KEY = "ai_summarizer_token";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  window.localStorage.removeItem(TOKEN_KEY);
}

/** Error carrying the HTTP status and (optional) field validation errors. */
export class ApiRequestError extends Error {
  constructor(
    public status: number,
    message: string,
    public fieldErrors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiRequestError";
  }
}

async function request<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token = getToken();
  const res = await fetch(`${BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
  });

  if (res.status === 204) {
    return undefined as T;
  }

  const body = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new ApiRequestError(
      res.status,
      body?.message ?? `Request failed (${res.status})`,
      body?.errors,
    );
  }

  return body as T;
}

export const api = {
  // --- Auth ---
  register: (name: string, email: string, password: string) =>
    request<AuthResponse>("/api/auth/register", {
      method: "POST",
      body: JSON.stringify({ name, email, password }),
    }),

  login: (email: string, password: string) =>
    request<AuthResponse>("/api/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    }),

  logout: () => request<void>("/api/auth/logout", { method: "POST" }),

  // --- Summaries ---
  listSummaries: (page = 1, status?: SummaryStatus) => {
    const params = new URLSearchParams({ page: String(page) });
    if (status) params.set("status", status);
    return request<Paginated<Summary>>(`/api/summaries?${params}`);
  },

  createSummary: (input: CreateSummaryInput) =>
    request<{ data: Summary }>("/api/summaries", {
      method: "POST",
      body: JSON.stringify(input),
    }),

  getSummary: (id: number) => request<{ data: Summary }>(`/api/summaries/${id}`),

  deleteSummary: (id: number) =>
    request<void>(`/api/summaries/${id}`, { method: "DELETE" }),

  // --- Admin ---
  adminSummaries: (page = 1) =>
    request<Paginated<Summary>>(`/api/admin/summaries?page=${page}`),

  adminUsers: () => request<User[]>("/api/admin/users"),

  adminStats: () => request<AdminStats>("/api/admin/stats"),
};
