// Types mirroring docs/03-openapi.yaml (the API contract).

export type SourceType = "url" | "text";
export type SummaryStyle = "tldr" | "bullets" | "short";
export type SummaryStatus = "pending" | "processing" | "completed" | "failed";
export type UserRole = "user" | "admin";

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  created_at: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface Summary {
  id: number;
  source_type: SourceType;
  source_url: string | null;
  title: string | null;
  style: SummaryStyle;
  status: SummaryStatus;
  result_text: string | null;
  error_message: string | null;
  model: string | null;
  input_tokens: number | null;
  output_tokens: number | null;
  cost_usd: number | null;
  created_at: string;
  completed_at: string | null;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface Paginated<T> {
  data: T[];
  meta: PaginationMeta;
}

export interface AdminStats {
  total_summaries: number;
  total_input_tokens: number;
  total_output_tokens: number;
  total_cost_usd: number;
  by_status: Partial<Record<SummaryStatus, number>>;
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}

export interface CreateSummaryInput {
  source_type: SourceType;
  url?: string;
  text?: string;
  style: SummaryStyle;
}
