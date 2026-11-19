import { useAsyncData } from "@/lib/hooks/use-async-data";
import { ApiClientError } from "@/lib/api/client";
import { act, renderHook, waitFor } from "@testing-library/react";
import { expect, it, vi } from "vitest";

it("retains same-scope content during refresh, then replaces it", async () => {
  let resolve!: (value: string) => void;
  const loader = vi.fn().mockResolvedValueOnce("before").mockImplementationOnce(() => new Promise<string>(r => { resolve = r; }));
  const { result } = renderHook(() => useAsyncData<string>(loader));
  await waitFor(() => expect(result.current.data).toBe("before"));
  act(() => result.current.reload());
  expect(result.current.data).toBe("before");
  expect(result.current.loading).toBe(false);
  expect(result.current.refreshing).toBe(true);
  await act(async () => resolve("after"));
  expect(result.current.data).toBe("after");
});

it("clears retained data after permission failure and on logout", async () => {
  const loader = vi.fn().mockResolvedValueOnce("private").mockRejectedValueOnce(new ApiClientError("denied", { status: 403 }));
  const { result } = renderHook(() => useAsyncData<string>(loader));
  await waitFor(() => expect(result.current.data).toBe("private"));
  act(() => result.current.reload());
  await waitFor(() => expect(result.current.error?.status).toBe(403));
  expect(result.current.data).toBeNull();
  act(() => window.dispatchEvent(new CustomEvent("tutorlink-session-hint", { detail: false })));
  expect(result.current.data).toBeNull();
});

it("aborts stale reads and never shows another scope's result", async () => {
  let oldSignal!: AbortSignal;
  let finish!: (value: string) => void;
  const old = (signal: AbortSignal) => { oldSignal = signal; return new Promise<string>(r => { finish = r; }); };
  const fresh = () => Promise.resolve("second scope");
  const { result, rerender } = renderHook(({ loader }) => useAsyncData(loader), { initialProps: { loader: old } });
  rerender({ loader: fresh });
  expect(oldSignal.aborted).toBe(true);
  await waitFor(() => expect(result.current.data).toBe("second scope"));
  await act(async () => finish("first scope"));
  expect(result.current.data).toBe("second scope");
});

it("discards private content on logout even when the loader identity is unchanged", async () => {
  const loader = vi.fn().mockResolvedValue("private content");
  const { result } = renderHook(() => useAsyncData<string>(loader));
  await waitFor(() => expect(result.current.data).toBe("private content"));
  act(() => window.dispatchEvent(new CustomEvent("tutorlink-session-hint", { detail: false })));
  expect(result.current.data).toBeNull();
  expect(loader).toHaveBeenCalledTimes(1);
});
