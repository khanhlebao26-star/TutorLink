"use client";

import { ApiClientError } from "@/lib/api/client";
import { useCallback, useEffect, useState } from "react";

export function useAsyncData<T>(loader: (signal: AbortSignal) => Promise<T>, sessionScoped = true) {
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<{
    loader: typeof loader;
    attempt: number;
    data: T | null;
    error: ApiClientError | null;
  } | null>(null);
  const reload = useCallback(() => setAttempt((value) => value + 1), []);

  useEffect(() => {
    const controller = new AbortController();
    const resetSession = (event: Event) => {
      if (sessionScoped && !(event as CustomEvent<boolean>).detail) {
        controller.abort();
        setResult(null);
      }
    };
    window.addEventListener("tutorlink-session-hint", resetSession);
    loader(controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setResult({ loader, attempt, data, error: null });
      })
      .catch((reason: unknown) => {
        if (!controller.signal.aborted) setResult({ loader, attempt, data: null, error: reason instanceof ApiClientError ? reason : new ApiClientError("Đã xảy ra lỗi không xác định.") });
      });
    return () => {
      controller.abort();
      window.removeEventListener("tutorlink-session-hint", resetSession);
    };
  }, [attempt, loader, sessionScoped]);

  const current = result?.loader === loader && (result.attempt === attempt || result.data !== null) ? result : null;
  return { data: current?.data ?? null, error: current?.error ?? null, loading: current === null,
    refreshing: current !== null && current.attempt !== attempt, reload };
}
