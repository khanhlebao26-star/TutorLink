import { request } from "../api/client";
export interface WorkspaceRow {
  id: number;
  program_id?: number;
  version: number;
  title?: string;
  body?: string;
  description?: string;
  due_at?: string | null;
  status?: string;
  state?: string;
  value?: number;
  note?: string;
  period?: string;
  amount_minor?: number;
  tutor_payment_mark?: "unmarked" | "marked_paid" | "marked_unpaid";
  disclaimer?: string;
  planned_starts_at?: string;
  planned_ends_at?: string;
  delivery_mode?: string;
  location_text?: string;
  meeting_url?: string;
  assigned_to?: number;
  author_id?: number;
  published_at?: string | null;
  created_at?: string | null;
  files?: Array<{ id: number; complete?: boolean }>;
  actual?: { id: number; status: string } | null;
  current_submission_id?: number | null;
  submission_version?: number;
  content?: string;
  feedback?: string;
  file_ids?: number[];
}
export interface Collection<T> {
  data: T[];
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
    per_page?: number;
  };
}
export async function workspaceRead<T>(
  url: string,
  params?: Record<string, string | number>,
) {
  return (await request<T>({ url, params })).data;
}
export async function workspaceWrite(
  url: string,
  method: "POST" | "PATCH" | "PUT",
  data: Record<string, unknown>,
  key: string,
) {
  return (
    await request<{ data: unknown }>(
      { url, method, data, headers: { "Idempotency-Key": key } },
      true,
    )
  ).data;
}
export function retryIdentity(
  previous: { payload: string; key: string } | null,
  payload: unknown,
) {
  const text = JSON.stringify(payload);
  return previous?.payload === text
    ? previous
    : { payload: text, key: crypto.randomUUID() };
}
export function feeLabel(row: WorkspaceRow) {
  const declaration = row.status === "voided" ? "Đã hủy khai báo" : "Đã khai báo";
  const payment = row.tutor_payment_mark === "marked_paid" ? "Tutor đánh dấu đã trả"
    : row.tutor_payment_mark === "marked_unpaid" ? "Tutor đánh dấu chưa trả"
    : "Chưa đánh dấu thanh toán";
  return `${declaration} · ${payment}`;
}

export function localTimestamp(value?: string) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, -1);
}
