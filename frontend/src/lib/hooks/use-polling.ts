"use client";
import { ApiClientError } from "@/lib/api/client";
import { useEffect, useState } from "react";
/** One request at a time; hidden/offline requests are aborted and never rescheduled until visible/online. */
export function usePolling(
  task: (signal: AbortSignal) => Promise<void>,
  interval = 10000,
) {
  const [error, setError] = useState<unknown>(null);
  useEffect(() => {
    let disposed = false,
      timer: ReturnType<typeof setTimeout> | undefined,
      controller: AbortController | null = null,
      failures = 0,
      halted = false;
    const available = () =>
      document.visibilityState !== "hidden" && navigator.onLine;
    const clear = () => {
      if (timer) clearTimeout(timer);
      timer = undefined;
    };
    const run = async () => {
      if (disposed || !available() || controller || halted) return;
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
          failures++;
          delay = Math.min(120000, interval * 2 ** failures);
          if (e instanceof ApiClientError) {
            halted = [401, 403, 404].includes(e.status ?? 0);
            if (e.retryAfter) delay = Math.max(delay, e.retryAfter * 1000);
          }
        }
      } finally {
        if (controller === active) controller = null;
        if (!disposed && !active.signal.aborted && available() && !halted)
          timer = setTimeout(run, delay);
      }
    };
    const change = () => {
      clear();
      controller?.abort();
      controller = null;
      if (available() && !halted) void run();
    };
    document.addEventListener("visibilitychange", change);
    window.addEventListener("online", change);
    window.addEventListener("offline", change);
    void run();
    return () => {
      disposed = true;
      clear();
      controller?.abort();
      document.removeEventListener("visibilitychange", change);
      window.removeEventListener("online", change);
      window.removeEventListener("offline", change);
    };
  }, [task, interval]);
  return error;
}
