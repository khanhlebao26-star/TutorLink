import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { usePolling } from "@/lib/hooks/use-polling";
import { ApiClientError } from "@/lib/api/client";
beforeEach(() => {
  vi.useFakeTimers();
  Object.defineProperty(document, "visibilityState", {
    configurable: true,
    value: "visible",
  });
  Object.defineProperty(navigator, "onLine", {
    configurable: true,
    value: true,
  });
});
afterEach(() => vi.useRealTimers());
it("pauses hidden/offline requests and resumes once without overlapping", async () => {
  let aborted = false;
  const task = vi.fn(
    (signal: AbortSignal) =>
      new Promise<void>((resolve) =>
        signal.addEventListener("abort", () => {
          aborted = true;
          resolve();
        }),
      ),
  );
  const { unmount } = renderHook(() => usePolling(task, 1000));
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => {
    Object.defineProperty(document, "visibilityState", { value: "hidden" });
    document.dispatchEvent(new Event("visibilitychange"));
  });
  expect(aborted).toBe(true);
  await act(async () => {
    await vi.advanceTimersByTimeAsync(10000);
  });
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => {
    Object.defineProperty(navigator, "onLine", { value: false });
    Object.defineProperty(document, "visibilityState", { value: "visible" });
    document.dispatchEvent(new Event("visibilitychange"));
  });
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => {
    Object.defineProperty(navigator, "onLine", { value: true });
    window.dispatchEvent(new Event("online"));
  });
  expect(task).toHaveBeenCalledTimes(2);
  unmount();
});
it("uses Retry-After backoff and stops on lost permission", async () => {
  const task = vi
    .fn()
    .mockRejectedValueOnce(
      new ApiClientError("Rate limit", { status: 429, retryAfter: 10 }),
    )
    .mockRejectedValue(new ApiClientError("Denied", { status: 403 }));
  const { result } = renderHook(() => usePolling(task, 1000));
  await act(async () => {});
  await act(async () => {
    await vi.advanceTimersByTimeAsync(9999);
  });
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => {
    await vi.advanceTimersByTimeAsync(1);
  });
  expect(task).toHaveBeenCalledTimes(2);
  expect(result.current).toMatchObject({ status: 403 });
  await act(async () => {
    await vi.advanceTimersByTimeAsync(60000);
  });
  expect(task).toHaveBeenCalledTimes(2);
});
