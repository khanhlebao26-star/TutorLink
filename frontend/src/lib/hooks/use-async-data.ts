"use client";

import { useCallback, useEffect, useState } from "react";
import { ApiClientError } from "../api/client";

export function useAsyncData<T>(loader: () => Promise<T>) {
  const [data, setData] = useState<T | null>(null);
  const [error, setError] = useState<ApiClientError | null>(null);
  const [attempt, setAttempt] = useState(0);
  const reload = useCallback(() => {
    setData(null);
    setError(null);
    setAttempt((value) => value + 1);
  }, []);

  useEffect(() => {
    let active = true;
    loader().then((result) => active && setData(result)).catch((reason: unknown) => active && setError(reason instanceof ApiClientError ? reason : new ApiClientError("Đã xảy ra lỗi không xác định.")));
    return () => { active = false; };
  }, [attempt, loader]);

  return { data, error, loading: data === null && error === null, reload };
}
