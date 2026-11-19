"use client";
import { ApiClientError } from "@/lib/api/client";
import { useEffect, useState } from "react";

export const INBOX_POLL_INTERVAL_MS = 15_000;
const retryDelays = [5_000, 10_000, 20_000, 30_000];
const maxTimeout = 2_147_483_647;

/** One request at a time; hidden/offline requests are aborted and never rescheduled until visible/online. */
export function usePolling(
  task: (signal: AbortSignal) => Promise<void>,
  interval = 10000,
  enabled = true,
  refreshEvent?: string,
) {
  const [error, setError] = useState<unknown>(null);
  useEffect(() => {
    if (!enabled) return;

    let disposed = false,
      timer: ReturnType<typeof setTimeout> | undefined,
      controller: AbortController | null = null,
      failures = 0,
      retryAt = 0,
      halted = false,
      refreshPending = false;
    const available = () =>
      document.visibilityState !== "hidden" && navigator.onLine;
    let wasAvailable = available();
    const clear = () => {
      if (timer !== undefined) clearTimeout(timer);
      timer = undefined;
    };
    const run = async () => {
      if (disposed || !available() || controller || halted) return;
      const retryDelay = retryAt - Date.now();
      if (retryDelay > 0) {
        timer = setTimeout(run, Math.min(retryDelay, maxTimeout));
        return;
      }

      retryAt = 0;
      controller = new AbortController();
      const active = controller;
      let delay = interval;
      try {
        await task(active.signal);
        if (!disposed && !active.signal.aborted) {
          failures = 0;
          setError(null);
        }
      } catch (e) {
        if (!disposed && !active.signal.aborted) {
          setError(e);
          delay = retryDelays[Math.min(failures, retryDelays.length - 1)];
          failures++;
          if (e instanceof ApiClientError) {
            halted = [401, 403, 404].includes(e.status ?? 0);
            const retryAfter = e.retryAfter;
            if (retryAfter !== undefined && Number.isFinite(retryAfter) && retryAfter > 0) {
              delay = Math.max(delay, retryAfter * 1000);
            }
          }
          retryAt = Date.now() + delay;
        }
      } finally {
        controller = null;
        if (!disposed && available() && !halted) {
          if (active.signal.aborted || refreshPending) {
            refreshPending = false;
            void run();
          } else {
            timer = setTimeout(run, Math.min(delay, maxTimeout));
          }
        }
      }
    };
    const change = () => {
      const isAvailable = available();
      if (isAvailable === wasAvailable) return;
      wasAvailable = isAvailable;
      clear();
      if (isAvailable) {
        void run();
      } else {
        controller?.abort();
      }
    };
    const refresh = () => { clear(); if (controller) refreshPending = true; else void run(); };
    if (refreshEvent) window.addEventListener(refreshEvent, refresh);
    document.addEventListener("visibilitychange", change);
    window.addEventListener("online", change);
    window.addEventListener("offline", change);
    void run();
    return () => {
      if (refreshEvent) window.removeEventListener(refreshEvent, refresh);
      disposed = true;
      clear();
      controller?.abort();
      document.removeEventListener("visibilitychange", change);
      window.removeEventListener("online", change);
      window.removeEventListener("offline", change);
    };
  }, [task, interval, enabled, refreshEvent]);
  return enabled ? error : null;
}
