import { request } from "@/lib/api/client";
import { CHAT_CHANGED, useConversationRealtime } from "@/lib/hooks/use-conversation-realtime";
import { act, renderHook } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
vi.mock("@/lib/api/client", () => ({ request: vi.fn() }));
class Socket {
  static instances: Socket[] = [];
  onmessage: ((event: {data: string}) => void) | null = null;
  onclose: (() => void) | null = null;
  onerror: (() => void) | null = null;
  send = vi.fn(); close = vi.fn();
  constructor(public url: string) { Socket.instances.push(this); }
  receive(event: string, data: unknown = {}) { this.onmessage?.({data: JSON.stringify({ event, data })}); }
}
afterEach(() => { vi.unstubAllEnvs(); vi.unstubAllGlobals(); Socket.instances = []; });
it("authorizes a session channel, resyncs, handles ping and disconnects on logout", async () => {
  vi.stubEnv("NEXT_PUBLIC_REVERB_APP_KEY", "public-key");
  vi.stubEnv("NEXT_PUBLIC_REVERB_URL", "ws://localhost:8080");
  vi.stubGlobal("WebSocket", Socket);
  vi.mocked(request).mockResolvedValueOnce({data: {data: {channel: "conversations.7.sessions.token", expires_at: new Date(Date.now()+600000).toISOString()}}} as never).mockResolvedValueOnce({data: {auth: "signature"}} as never);
  const notify = vi.fn(); window.addEventListener(CHAT_CHANGED, notify);
  const {result, unmount} = renderHook(() => useConversationRealtime(7));
  const socket = Socket.instances[0];
  await act(async () => { socket.receive("pusher:connection_established", JSON.stringify({socket_id: "1.2"})); });
  expect(socket.send).toHaveBeenCalledWith(expect.stringContaining("private-conversations.7.sessions.token"));
  await act(async () => { socket.receive("pusher_internal:subscription_succeeded"); });
  expect(result.current).toBe(true); expect(notify).toHaveBeenCalledTimes(1);
  await act(async () => { socket.receive("conversation.messages_changed", {conversation_id: 7}); socket.receive("pusher:ping"); });
  expect(notify).toHaveBeenCalledTimes(2);
  expect(socket.send).toHaveBeenCalledWith(JSON.stringify({event: "pusher:pong", data: {}}));
  await act(async () => { window.dispatchEvent(new CustomEvent("tutorlink-session-hint", {detail: false})); });
  expect(socket.close).toHaveBeenCalled(); expect(result.current).toBe(false);
  unmount(); window.removeEventListener(CHAT_CHANGED, notify);
});
it("reconnects after transport failure and stops after auth denial", async () => {
  vi.useFakeTimers();
  vi.stubEnv("NEXT_PUBLIC_REVERB_APP_KEY", "key"); vi.stubEnv("NEXT_PUBLIC_REVERB_URL", "ws://localhost:8080"); vi.stubGlobal("WebSocket", Socket);
  const { unmount } = renderHook(() => useConversationRealtime(8));
  await act(async () => { Socket.instances[0].onclose?.(); await vi.advanceTimersByTimeAsync(1000); });
  expect(Socket.instances).toHaveLength(2);
  vi.mocked(request).mockRejectedValueOnce({status: 403});
  await act(async () => { Socket.instances[1].receive("pusher:connection_established", {socket_id: "2.3"}); });
  await act(async () => { Socket.instances[1].onclose?.(); await vi.advanceTimersByTimeAsync(60000); });
  expect(Socket.instances).toHaveLength(2);
  unmount(); vi.useRealTimers();
});
