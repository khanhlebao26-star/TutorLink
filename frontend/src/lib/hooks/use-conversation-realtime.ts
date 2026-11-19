"use client";

import { request } from "@/lib/api/client";
import { useEffect, useState } from "react";

export const CHAT_CHANGED = "tutorlink-chat-changed";

/** Reverb speaks the Pusher protocol. Events invalidate HTTP data; they never carry trusted message content. */
export function useConversationRealtime(id?: number) {
  const [connectedId, setConnectedId] = useState<number | null>(null);
  useEffect(() => {
    const key = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
    const endpoint = process.env.NEXT_PUBLIC_REVERB_URL;
    if (!id || !key || !endpoint || typeof WebSocket === "undefined") return;
    let disposed = false, halted = false, failures = 0;
    let socket: WebSocket | null = null;
    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let deadline: ReturnType<typeof setTimeout> | undefined;
    let renewal: ReturnType<typeof setTimeout> | undefined;
    const available = () => document.visibilityState !== "hidden" && navigator.onLine;
    const notify = () => window.dispatchEvent(new CustomEvent(CHAT_CHANGED));
    const disconnect = () => {
      clearTimeout(timer); clearTimeout(deadline); clearTimeout(renewal);
      controller?.abort(); controller = null;
      if (socket) { socket.onclose = null; socket.close(); socket = null; }
      setConnectedId(null);
    };
    const connect = () => {
      if (disposed || halted || !available() || socket) return;
      controller = new AbortController();
      const signal = controller.signal;
      let url: URL;
      try { url = new URL(endpoint); } catch { return; }
      if (!["ws:", "wss:"].includes(url.protocol)) return;
      url.pathname = `${url.pathname.replace(/\/$/, "")}/app/${encodeURIComponent(key)}`;
      url.search = "protocol=7&client=tutorlink&version=1.0";
      let ws: WebSocket;
      try { ws = new WebSocket(url); } catch { return; }
      socket = ws;
      const watchdog = () => {
        clearTimeout(deadline);
        deadline = setTimeout(() => ws.close(), 75_000);
      };
      watchdog();
      ws.onerror = () => ws.close();
      ws.onmessage = async (event) => {
        try {
          const frame = JSON.parse(event.data);
          const data = typeof frame.data === "string" ? JSON.parse(frame.data) : frame.data;
          watchdog();
          if (frame.event === "pusher:ping") {
            ws.send(JSON.stringify({ event: "pusher:pong", data: {} }));
          } else if (frame.event === "pusher:connection_established") {
            const grant = await request<{data: {channel: string; expires_at: string}}>({ method: "POST", url: `/conversations/${id}/realtime`, data: {}, signal }, true);
            const channel = `private-${grant.data.data.channel}`;
            const auth = await request<{auth: string}>({ method: "POST", url: "/broadcasting/auth", baseURL: endpointToApiOrigin(), data: { socket_id: data.socket_id, channel_name: channel }, signal }, true);
            if (signal.aborted || socket !== ws) return;
            ws.send(JSON.stringify({ event: "pusher:subscribe", data: { channel, auth: auth.data.auth } }));
            renewal = setTimeout(() => ws.close(), Math.max(1000, Date.parse(grant.data.data.expires_at) - Date.now() - 5000));
          } else if (frame.event === "pusher_internal:subscription_succeeded") {
            failures = 0; setConnectedId(id); notify();
          } else if (frame.event === "conversation.messages_changed" && data?.conversation_id === id) {
            notify();
          } else if (frame.event === "pusher:error" || frame.event === "pusher:subscription_error") {
            ws.close();
          }
        } catch (error) {
          if (error && typeof error === "object" && "status" in error && [401, 403, 404].includes(Number(error.status))) halted = true;
          ws.close();
        }
      };
      ws.onclose = () => {
        if (socket !== ws) return;
        disconnect();
        if (!disposed && !halted && available()) timer = setTimeout(connect, Math.min(30_000, 1000 * 2 ** Math.min(failures++, 5)));
      };
    };
    const visibility = () => { disconnect(); if (available()) connect(); };
    const logout = (event: Event) => { if (!(event as CustomEvent<boolean>).detail) { halted = true; disconnect(); } };
    document.addEventListener("visibilitychange", visibility);
    window.addEventListener("online", visibility); window.addEventListener("offline", visibility);
    window.addEventListener("tutorlink-session-hint", logout);
    connect();
    return () => {
      disposed = true; disconnect();
      document.removeEventListener("visibilitychange", visibility);
      window.removeEventListener("online", visibility); window.removeEventListener("offline", visibility);
      window.removeEventListener("tutorlink-session-hint", logout);
    };
  }, [id]);
  return id !== undefined && connectedId === id;
}

function endpointToApiOrigin() {
  return (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/api\/v1\/?$/, "").replace(/\/$/, "");
}
