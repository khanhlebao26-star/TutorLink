import { request } from "@/lib/api/client";

export interface Page<T> {
  data: T[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page?: number;
  };
}
export interface Program {
  id: number;
  customer_id: number;
  source_listing_id: number | null;
  title: string;
  goal: string | null;
  level: string | null;
  delivery_mode: "online" | "offline" | "hybrid";
  agreed_fee_minor: number;
  currency: "VND";
  fee_unit: "session" | "hour" | "course";
  timezone: string;
  location_text: string | null;
  meeting_url: string | null;
  schedule_note: string | null;
  planned_session_count: number | null;
  status: "draft" | "active" | "paused" | "completed" | "cancelled";
  version: number;
  allowed_actions: string[];
}
export type ProgramInput = Pick<
  Program,
  "customer_id" | "title" | "delivery_mode" | "agreed_fee_minor" | "fee_unit"
> &
  Partial<
    Pick<
      Program,
      | "source_listing_id"
      | "goal"
      | "level"
      | "timezone"
      | "schedule_note"
      | "planned_session_count"
      | "location_text"
      | "meeting_url"
    >
  >;
export interface Invitation {
  id: number;
  program_id: number;
  status: "pending" | "accepted" | "rejected" | "cancelled" | "expired";
  expires_at: string;
  offer_snapshot: {
    target_customer_id: number;
    program_id: number;
    offer: Pick<Program, "title"|"goal"|"level"|"delivery_mode"|"agreed_fee_minor"|"currency"|"fee_unit"|"location_text"|"meeting_url"|"schedule_note"|"timezone"|"planned_session_count">;
    source_listing: { title: string } | null;
  };
}
export interface Overview {
  next_session: {
    id: number;
    title?: string;
    planned_starts_at: string;
  } | null;
  task_counts: Record<
    "todo" | "in_progress" | "submitted" | "completed" | "cancelled",
    number
  >;
  latest_progress: { value: number; note: string | null } | null;
  latest_announcement: { title: string; body: string } | null;
  capabilities: Record<string, boolean>;
}
export function mutationKey() {
  return crypto.randomUUID();
}
export async function listPrograms(page = 1): Promise<Page<Program>> {
  return (await request<Page<Program>>({ url: "/programs", params: { page } }))
    .data;
}
export async function getProgram(id: number): Promise<Program> {
  return (await request<{ data: Program }>({ url: `/programs/${id}` })).data
    .data;
}
export async function createProgram(input: ProgramInput): Promise<Program> {
  return (
    await request<{ data: Program }>(
      { method: "POST", url: "/programs", data: input },
      true,
    )
  ).data.data;
}
export async function editProgram(
  id: number,
  input: Partial<ProgramInput>,
  version: number,
): Promise<Program> {
  return (
    await request<{ data: Program }>(
      {
        method: "PATCH",
        url: `/programs/${id}`,
        data: { ...input, expected_version: version },
      },
      true,
    )
  ).data.data;
}
export async function getOverview(id: number): Promise<Overview> {
  return (
    await request<{ data: Overview }>({ url: `/programs/${id}/overview` })
  ).data.data;
}
export async function sendInvitation(
  id: number,
  version: number,
  key: string,
): Promise<Invitation> {
  return (
    await request<{ data: Invitation }>(
      {
        method: "POST",
        url: `/programs/${id}/invitations`,
        data: { expected_version: version },
        headers: { "Idempotency-Key": key },
      },
      true,
    )
  ).data.data;
}
export async function listInvitations(page = 1): Promise<Page<Invitation>> {
  return (
    await request<Page<Invitation>>({ url: "/invitations", params: { page } })
  ).data;
}
export async function getInvitation(id: number): Promise<Invitation> {
  return (await request<{ data: Invitation }>({ url: `/invitations/${id}` }))
    .data.data;
}
export async function decideInvitation(
  id: number,
  action: "accept" | "reject" | "cancel",
  key: string,
  reason?: string,
): Promise<void> {
  await request(
    {
      method: "POST",
      url: `/invitations/${id}/${action}`,
      ...(action === "accept" ? {} : { data: { reason: reason || null } }),
      headers: { "Idempotency-Key": key },
    },
    true,
  );
}

export async function transitionProgram(
  id: number,
  action: string,
  key: string,
) {
  return (
    await request<{ data: Program }>(
      {
        method: "POST",
        url: `/programs/${id}/${action}`,
        headers: { "Idempotency-Key": key },
      },
      true,
    )
  ).data.data;
}
