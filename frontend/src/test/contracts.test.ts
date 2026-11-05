import { beforeEach, describe, expect, it, vi } from "vitest";
import {
  request,
  toApiClientError,
  ApiClientError,
  describeApiError,
} from "@/lib/api/client";
import {
  decideInvitation,
  editProgram,
  sendInvitation,
  transitionProgram,
} from "@/lib/data/collaboration";
import {
  messages,
  sendMessage,
  mergeMessages,
  type Message,
} from "@/lib/data/messaging";
vi.mock("@/lib/api/client", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api/client")>()),
  request: vi.fn(),
}));
beforeEach(() => {
  vi.mocked(request).mockReset();
  vi.mocked(request).mockResolvedValue({ data: { data: { id: 7 } } } as never);
});
describe("Backend contract", () => {
  it("sends version on edit and keeps the original caller key on invitation retries", async () => {
    await editProgram(7, { title: "New title" }, 3);
    expect(request).toHaveBeenLastCalledWith(
      expect.objectContaining({
        method: "PATCH",
        data: { title: "New title", expected_version: 3 },
      }),
      true,
    );
    await sendInvitation(7, 3, "stable-key");
    await sendInvitation(7, 3, "stable-key");
    expect(
      vi
        .mocked(request)
        .mock.calls.slice(1)
        .map((c) => c[0].headers),
    ).toEqual([
      { "Idempotency-Key": "stable-key" },
      { "Idempotency-Key": "stable-key" },
    ]);
  });
  it("sends no body for accept and Program transitions, which reject even empty JSON", async () => {
    await decideInvitation(4, "accept", "key");
    expect(request).toHaveBeenLastCalledWith(
      expect.not.objectContaining({ data: expect.anything() }),
      true,
    );
    await transitionProgram(7, "complete", "key");
    expect(vi.mocked(request).mock.lastCall?.[0]).not.toHaveProperty("data");
  });
  it("sends only a text payload with a stable client UUID and separates before from after", async () => {
    await sendMessage(8, "Hello", "uuid");
    expect(request).toHaveBeenLastCalledWith(
      {
        method: "POST",
        url: "/conversations/8/messages",
        data: {
          message_type: "text",
          body: "Hello",
          client_message_id: "uuid",
        },
      },
      true,
    );
    await messages(8, { before: "older" });
    expect(request).toHaveBeenLastCalledWith(
      expect.objectContaining({ params: { before: "older" } }),
    );
  });
  it("deduplicates racing poll and send results and orders ties by id", () => {
    const m = (id: number, body: string): Message => ({
      id,
      body,
      conversation_id: 8,
      sender_id: 2,
      client_message_id: `key${id}`,
      message_type: "text",
      created_at: "2026-11-05T00:00:00Z",
    });
    expect(
      mergeMessages(
        [m(2, "old"), m(1, "first")],
        [m(2, "new"), m(3, "third")],
      ).map((m) => [m.id, m.body]),
    ).toEqual([
      [1, "first"],
      [2, "new"],
      [3, "third"],
    ]);
  });
});
describe("Error mapping", () => {
  it("preserves flat error code, request id, field errors and Retry-After", () => {
    const error = toApiClientError({
      response: {
        status: 422,
        data: {
          code: "validation_failed",
          message: "Invalid",
          errors: { title: ["Required"] },
        },
        headers: { "x-request-id": "trace", "retry-after": "12" },
      },
    });
    expect(error).toMatchObject({
      status: 422,
      code: "validation_failed",
      fieldErrors: { title: ["Required"] },
      requestId: "trace",
      retryAfter: 12,
    });
  });
  it.each([401, 403, 419, 429])(
    "maps HTTP %i to a user-facing explanation",
    (status) => {
      expect(
        describeApiError(new ApiClientError("server", { status })),
      ).not.toBe("server");
    },
  );
});
