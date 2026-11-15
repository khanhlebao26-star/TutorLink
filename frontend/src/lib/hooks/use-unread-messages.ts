"use client";

import { ApiClientError, request } from "@/lib/api/client";
import type { ApiEnvelope } from "@/lib/data/types";
import { INBOX_POLL_INTERVAL_MS, usePolling } from "@/lib/hooks/use-polling";
import { useCallback, useState } from "react";

export function useUnreadMessages(actorId: number | null) {
  const [badge, setBadge] = useState({ actorId, count: 0 });
  if (badge.actorId !== actorId) setBadge({ actorId, count: 0 });

  const refresh = useCallback(async (signal: AbortSignal) => {
    try {
      const response = await request<ApiEnvelope<{ count: number }>>({
        url: "/conversations/unread-count",
        signal,
        timeout: 10_000,
      });
      if (!signal.aborted) setBadge({ actorId, count: response.data.data.count });
    } catch (error) {
      if (
        !signal.aborted && error instanceof ApiClientError &&
        [401, 403, 404].includes(error.status ?? 0)
      ) {
        setBadge({ actorId, count: 0 });
      }
      throw error;
    }
  }, [actorId]);

  usePolling(refresh, INBOX_POLL_INTERVAL_MS, actorId !== null);
  return actorId === badge.actorId ? badge.count : 0;
}
