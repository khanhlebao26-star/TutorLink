import { ApiClientError } from "@/lib/api/client";
import { usePolling } from "@/lib/hooks/use-polling";
import { act, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
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
    Object.defineProperty(document, "visibilityState", { value: "hidden" });
    document.dispatchEvent(new Event("visibilitychange"));
    await vi.advanceTimersByTimeAsync(1000);
    Object.defineProperty(document, "visibilityState", { value: "visible" });
    document.dispatchEvent(new Event("visibilitychange"));
  });
  await act(async () => {
    await vi.advanceTimersByTimeAsync(8999);
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

it("paces requests from completion and ignores duplicate availability events", async () => {
  let complete!: () => void;
  const task = vi.fn().mockImplementationOnce(
    () => new Promise<void>((resolve) => { complete = resolve; }),
  ).mockResolvedValue(undefined);
  renderHook(() => usePolling(task, 15_000));

  await act(async () => {
    window.dispatchEvent(new Event("online"));
    document.dispatchEvent(new Event("visibilitychange"));
    await vi.advanceTimersByTimeAsync(30_000);
  });
  expect(task).toHaveBeenCalledTimes(1);

  await act(async () => { complete(); });
  await act(async () => { await vi.advanceTimersByTimeAsync(14_999); });
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => { await vi.advanceTimersByTimeAsync(1); });
  expect(task).toHaveBeenCalledTimes(2);
});

it("waits for canceled work to settle before restarting on a quick tab switch", async () => {
  let complete!: () => void;
  const task = vi.fn<(signal: AbortSignal) => Promise<void>>().mockImplementationOnce(
    () => new Promise<void>((resolve) => { complete = resolve; }),
  ).mockResolvedValue(undefined);
  renderHook(() => usePolling(task, 15_000));

  await act(async () => {
    Object.defineProperty(document, "visibilityState", { value: "hidden" });
    document.dispatchEvent(new Event("visibilitychange"));
    Object.defineProperty(document, "visibilityState", { value: "visible" });
    document.dispatchEvent(new Event("visibilitychange"));
  });
  expect(task.mock.calls[0][0].aborted).toBe(true);
  expect(task).toHaveBeenCalledTimes(1);

  await act(async () => { complete(); });
  expect(task).toHaveBeenCalledTimes(2);
});

it("backs off at 5, 10, 20, then at most 30 seconds for transient failures", async () => {
  const task = vi.fn().mockRejectedValue(new Error("Unavailable"));
  renderHook(() => usePolling(task, 15_000));

  for (const [index, delay] of [5000, 10000, 20000, 30000, 30000].entries()) {
    await act(async () => { await vi.advanceTimersByTimeAsync(delay - 1); });
    expect(task).toHaveBeenCalledTimes(index + 1);
    await act(async () => { await vi.advanceTimersByTimeAsync(1); });
    expect(task).toHaveBeenCalledTimes(index + 2);
  }
});

it("resyncs once after an event arrives during an active request", async () => {
  let complete!: () => void;
  const task = vi.fn().mockImplementationOnce(() => new Promise<void>((resolve) => { complete = resolve; })).mockResolvedValue(undefined);
  const { unmount } = renderHook(() => usePolling(task, 60000, true, "chat-test"));
  await act(async () => { window.dispatchEvent(new Event("chat-test")); window.dispatchEvent(new Event("chat-test")); });
  expect(task).toHaveBeenCalledTimes(1);
  await act(async () => { complete(); });
  expect(task).toHaveBeenCalledTimes(2);
  unmount();
});
