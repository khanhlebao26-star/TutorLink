import { request } from "../api/client";
export interface CursorPage<T> {
  data: T[];
  meta: {
    per_page: number;
    has_more: boolean;
    next_cursor: string | null;
    polling_cursor?: string | null;
  };
}
export interface Conversation {
  id: number;
  status: string;
  context_listing_id: number | null;
  peer: {
    id: number;
    display_name: string;
    tutor_slug?: string | null;
    tutor_headline?: string | null;
    tutor_specializations?: string | null;
  };
  unread_count: number;
  last_read_message_id: number | null;
  preview: { id: number; text: string; created_at: string } | null;
}
export interface Message {
  id: number;
  conversation_id: number;
  sender_id: number;
  client_message_id: string;
  message_type: string;
  body: string | null;
  attachments?: Array<{ id: number; name: string; mime_type: string; size_bytes: number }>;
  created_at: string;
}
export async function conversations(cursor?: string, signal?: AbortSignal) {
  return (
    await request<CursorPage<Conversation>>({
      url: "/conversations",
      params: cursor ? { cursor } : {},
      signal,
    })
  ).data;
}
export async function conversation(id: number, signal?: AbortSignal) {
  return (
    await request<{ data: Conversation }>({
      url: `/conversations/${id}`,
      signal,
    })
  ).data.data;
}
export async function openConversation(peer: number, listing?: number) {
  return (
    await request<{ data: Conversation }>(
      {
        method: "POST",
        url: "/conversations",
        data: {
          peer_user_id: peer,
          ...(listing ? { context_listing_id: listing } : {}),
        },
      },
      true,
    )
  ).data.data;
}
export async function messages(
  id: number,
  params: { before?: string; after?: string } = {},
  signal?: AbortSignal,
) {
  return (
    await request<CursorPage<Message>>({
      url: `/conversations/${id}/messages`,
      params,
      signal,
    })
  ).data;
}
export async function sendMessage(id: number, body: string, clientId: string, fileIds: number[] = []) {
  const messageType = fileIds.length ? "file" : "text";
  return (
    await request<{ data: Message }>(
      {
        method: "POST",
        url: `/conversations/${id}/messages`,
        data: { message_type: messageType, body: body || null, ...(fileIds.length ? { file_ids: fileIds } : {}), client_message_id: clientId },
      },
      true,
    )
  ).data.data;
}
export async function markRead(id: number, message: number) {
  await request(
    {
      method: "PATCH",
      url: `/conversations/${id}/read`,
      data: { last_read_message_id: message },
    },
    true,
  );
}
export function mergeMessages(previous: Message[], incoming: Message[]) {
  return [
    ...new Map([...previous, ...incoming].map((m) => [m.id, m])).values(),
  ].sort((a, b) => a.created_at.localeCompare(b.created_at) || a.id - b.id);
}
