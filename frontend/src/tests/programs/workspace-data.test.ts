import { request } from "@/lib/api/client";
import { feeLabel, localTimestamp, readAllWorkspaceRows, retryIdentity, workspaceRead, workspaceWrite } from "@/lib/data/workspace";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", () => ({ request: vi.fn() }));

beforeEach(() => vi.clearAllMocks());

it("reads collections and writes idempotent mutations with their response payload", async () => {
  vi.mocked(request).mockResolvedValueOnce({ data: { data: [{ id: 1 }] } } as never);
  const signal = new AbortController().signal;
  expect(await workspaceRead("/programs/2/tasks", { page: 3 }, signal)).toEqual({ data: [{ id: 1 }] });
  expect(request).toHaveBeenCalledWith({ url: "/programs/2/tasks", params: { page: 3 }, signal });

  vi.mocked(request).mockResolvedValueOnce({ data: { data: { id: 4 } } } as never);
  expect(await workspaceWrite("/tasks/4/submissions", "POST", { content: "Answer" }, "idem-4")).toEqual({ data: { id: 4 } });
  expect(request).toHaveBeenLastCalledWith({ url: "/tasks/4/submissions", method: "POST", data: { content: "Answer" }, headers: { "Idempotency-Key": "idem-4" } }, true);
});

it("reuses a retry key only for an identical payload", () => {
  const previous = { payload: '{"content":"same"}', key: "stable-key" };
  expect(retryIdentity(previous, { content: "same" })).toBe(previous);
  vi.spyOn(globalThis.crypto, "randomUUID").mockReturnValue("new-key" as `${string}-${string}-${string}-${string}-${string}`);
  expect(retryIdentity(previous, { content: "changed" })).toEqual({ payload: '{"content":"changed"}', key: "new-key" });
});

it("formats local timestamps and fee/payment declarations", () => {
  expect(localTimestamp()).toBe("");
  expect(localTimestamp("invalid")).toBe("");
  expect(localTimestamp("2026-06-10T09:30:00.000Z")).toMatch(/^2026-06-10T/);
  expect(feeLabel({ id: 1, version: 1, status: "voided", tutor_payment_mark: "marked_paid" })).toBe("Đã hủy khai báo · Tutor đánh dấu đã trả");
  expect(feeLabel({ id: 2, version: 1, status: "submitted", tutor_payment_mark: "marked_unpaid" })).toBe("Đã khai báo · Tutor đánh dấu chưa trả");
  expect(feeLabel({ id: 3, version: 1, status: "submitted" })).toBe("Đã khai báo · Chưa đánh dấu thanh toán");
});

it("loads all workspace pages while preserving order", async () => {
  vi.mocked(request)
    .mockResolvedValueOnce({ data: { data: [{ id: 1 }, { id: 2 }], meta: { last_page: 3 } } } as never)
    .mockResolvedValueOnce({ data: { data: [{ id: 3 }], meta: { last_page: 3 } } } as never)
    .mockResolvedValueOnce({ data: { data: [{ id: 4 }], meta: { last_page: 3 } } } as never);
  await expect(readAllWorkspaceRows("/programs/8/tasks")).resolves.toEqual([{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }]);
  expect(request).toHaveBeenNthCalledWith(1, { url: "/programs/8/tasks", params: { page: 1, per_page: 50 } });
  expect(request).toHaveBeenNthCalledWith(2, { url: "/programs/8/tasks", params: { page: 2, per_page: 50 } });
  expect(request).toHaveBeenNthCalledWith(3, { url: "/programs/8/tasks", params: { page: 3, per_page: 50 } });
});

it("returns a first page directly when the collection has one page", async () => {
  vi.mocked(request).mockResolvedValueOnce({ data: { data: [{ id: 9 }], meta: { last_page: 1 } } } as never);
  await expect(readAllWorkspaceRows("/programs/8/files")).resolves.toEqual([{ id: 9 }]);
  expect(request).toHaveBeenCalledTimes(1);
});
