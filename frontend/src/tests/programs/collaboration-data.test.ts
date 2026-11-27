import { request } from "@/lib/api/client";
import { createProgram, decideInvitation, editProgram, getInvitation, getOverview, getProgram, listInvitations, listPrograms, mutationKey, sendInvitation, transitionProgram } from "@/lib/data/collaboration";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", () => ({ request: vi.fn() }));

beforeEach(() => vi.clearAllMocks());

it("maps collaboration reads and versioned writes to their API contracts", async () => {
  vi.spyOn(globalThis.crypto, "randomUUID").mockReturnValue("mutation-key" as `${string}-${string}-${string}-${string}-${string}`);
  expect(mutationKey()).toBe("mutation-key");
  const program = { id: 8, title: "English", version: 3 };
  const page = { data: [program], meta: { current_page: 1, per_page: 10, total: 1 } };
  vi.mocked(request)
    .mockResolvedValueOnce({ data: page } as never)
    .mockResolvedValueOnce({ data: { data: program } } as never)
    .mockResolvedValueOnce({ data: { data: program } } as never)
    .mockResolvedValueOnce({ data: { data: { ...program, version: 4 } } } as never)
    .mockResolvedValueOnce({ data: { data: { completed_session_count: 1 } } } as never);
  expect(await listPrograms()).toEqual(page);
  const signal = new AbortController().signal;
  expect(await getProgram(8, signal)).toEqual(program);
  expect(await createProgram({ customer_id: 4, title: "English", delivery_mode: "online", agreed_fee_minor: 200000, fee_unit: "hour" } as never)).toEqual(program);
  expect(await editProgram(8, { title: "Updated" } as never, 3)).toEqual({ ...program, version: 4 });
  expect(await getOverview(8)).toEqual({ completed_session_count: 1 });
  expect(request).toHaveBeenNthCalledWith(1, { url: "/programs", params: { page: 1 } });
  expect(request).toHaveBeenNthCalledWith(2, { url: "/programs/8", signal });
  expect(request).toHaveBeenNthCalledWith(4, { method: "PATCH", url: "/programs/8", data: { title: "Updated", expected_version: 3 } }, true);
});

it("sends invitation decisions with the correct idempotency and reason payloads", async () => {
  const invitation = { id: 5, program_id: 8, status: "pending" };
  vi.mocked(request)
    .mockResolvedValueOnce({ data: invitation } as never)
    .mockResolvedValueOnce({ data: { data: invitation } } as never)
    .mockResolvedValueOnce({ data: { data: invitation } } as never)
    .mockResolvedValueOnce({ data: { data: invitation } } as never)
    .mockResolvedValueOnce({ data: { data: invitation } } as never);
  expect(await listInvitations()).toEqual(invitation);
  expect(await getInvitation(5)).toEqual(invitation);
  expect(await sendInvitation(8, 3, "send-key")).toEqual(invitation);
  await decideInvitation(5, "accept", "accept-key");
  await decideInvitation(5, "reject", "reject-key");
  expect(request).toHaveBeenNthCalledWith(3, { method: "POST", url: "/programs/8/invitations", data: { expected_version: 3 }, headers: { "Idempotency-Key": "send-key" } }, true);
  expect(request).toHaveBeenNthCalledWith(4, { method: "POST", url: "/invitations/5/accept", headers: { "Idempotency-Key": "accept-key" } }, true);
  expect(request).toHaveBeenNthCalledWith(5, { method: "POST", url: "/invitations/5/reject", data: { reason: null }, headers: { "Idempotency-Key": "reject-key" } }, true);
});

it("allows cancellation reasons and program state transitions", async () => {
  vi.mocked(request).mockResolvedValueOnce({ data: null } as never).mockResolvedValueOnce({ data: { data: { id: 8, status: "paused" } } } as never);
  await decideInvitation(5, "cancel", "cancel-key", "Schedule changed");
  expect(await transitionProgram(8, "pause", "pause-key")).toEqual({ id: 8, status: "paused" });
  expect(request).toHaveBeenNthCalledWith(1, { method: "POST", url: "/invitations/5/cancel", data: { reason: "Schedule changed" }, headers: { "Idempotency-Key": "cancel-key" } }, true);
  expect(request).toHaveBeenNthCalledWith(2, { method: "POST", url: "/programs/8/pause", headers: { "Idempotency-Key": "pause-key" } }, true);
});
