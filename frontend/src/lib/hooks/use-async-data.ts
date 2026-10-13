"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiClientError } from "../api/client";

export function useAsyncData<T>(loader: () => Promise<T>) {
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<{
    loader: typeof loader;
    attempt: number;
    data: T | null;
    error: ApiClientError | null;
  } | null>(null);
  const reload = useCallback(() => setAttempt((value) => value + 1), []);

  useEffect(() => {
    let active = true;
    loader()
      .then((data) => {
        if (active) setResult({ loader, attempt, data, error: null });
      })
      .catch((reason: unknown) => {
        if (active) setResult({ loader, attempt, data: null, error: reason instanceof ApiClientError ? reason : new ApiClientError("Đã xảy ra lỗi không xác định.") });
      });
    return () => { active = false; };
  }, [attempt, loader]);

  const current = result?.loader === loader && result.attempt === attempt ? result : null;
  return { data: current?.data ?? null, error: current?.error ?? null, loading: current === null, reload };
}
