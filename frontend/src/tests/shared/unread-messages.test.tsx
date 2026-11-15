import { ApiClientError, request } from "@/lib/api/client";
import { useUnreadMessages } from "@/lib/hooks/use-unread-messages";
import { act, renderHook } from "@testing-library/react";
import { AxiosHeaders } from "axios";
import { afterEach, beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  request: vi.fn(),
}));

const send = vi.mocked(request);
const headers = new AxiosHeaders();
const response = (count: number) => ({
  data: { data: { count } },
  status: 200,
  statusText: "OK",
  headers,
  config: { headers },
});

beforeEach(() => {
  vi.useFakeTimers();
  send.mockReset();
});
afterEach(() => vi.useRealTimers());

it("clears the badge and aborts the previous account's request when the actor changes", async () => {
  send.mockResolvedValueOnce(response(4));
  let resolveOldRequest!: (value: ReturnType<typeof response>) => void;
  send.mockReturnValueOnce(new Promise((resolve) => { resolveOldRequest = resolve; }));
  send.mockResolvedValueOnce(response(2));
  const { result, rerender } = renderHook<number, { actorId: number | null }>(({ actorId }) => useUnreadMessages(actorId), {
    initialProps: { actorId: 1 },
  });
  await act(async () => {});
  expect(result.current).toBe(4);
  await act(async () => { await vi.advanceTimersByTimeAsync(15_000); });
  const oldSignal = send.mock.calls[1][0].signal;

  rerender({ actorId: 2 });
  expect(result.current).toBe(0);
  expect(oldSignal?.aborted).toBe(true);
  await act(async () => { resolveOldRequest(response(99)); });
  expect(result.current).toBe(2);

  rerender({ actorId: null });
  expect(result.current).toBe(0);
  await act(async () => { await vi.advanceTimersByTimeAsync(60_000); });
  expect(send).toHaveBeenCalledTimes(3);
});

it("keeps the last known count on transient errors and stops when the session expires", async () => {
  send.mockResolvedValueOnce(response(4));
  send.mockRejectedValueOnce(new ApiClientError("Unavailable", { status: 503 }));
  send.mockRejectedValueOnce(new ApiClientError("Expired", { status: 401 }));
  const { result } = renderHook(() => useUnreadMessages(1));
  await act(async () => {});
  expect(result.current).toBe(4);

  await act(async () => { await vi.advanceTimersByTimeAsync(15_000); });
  expect(result.current).toBe(4);
  await act(async () => { await vi.advanceTimersByTimeAsync(5_000); });
  expect(result.current).toBe(0);
  await act(async () => { await vi.advanceTimersByTimeAsync(60_000); });
  expect(send).toHaveBeenCalledTimes(3);
});
