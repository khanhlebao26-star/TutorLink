import { toApiClientError } from "@/lib/api/client";
import { feeLabel, retryIdentity } from "@/lib/data/workspace";
import { expect, it } from "vitest";
it("keeps the UUID for the same retry but replaces it when payload changes", () => {
  const previous = retryIdentity(null, {
    expected_version: 2,
    content: "first",
  });
  expect(
    retryIdentity(previous, { expected_version: 2, content: "first" }),
  ).toBe(previous);
  expect(
    retryIdentity(previous, { expected_version: 2, content: "new" }).key,
  ).not.toBe(previous.key);
});
it("keeps void separate from a tutor payment declaration", () => {
  expect(
    feeLabel({
      id: 1,
      version: 1,
      status: "voided",
      tutor_payment_mark: "unmarked",
    }),
  ).toContain("Đã hủy khai báo");
  expect(
    feeLabel({
      id: 1,
      version: 1,
      status: "voided",
      tutor_payment_mark: "unmarked",
    }),
  ).not.toContain("Tutor đánh dấu đã trả");
});

it.each([
  ["marked_paid", "Tutor đánh dấu đã trả"],
  ["marked_unpaid", "Tutor đánh dấu chưa trả"],
] as const)("displays the server payment mark %s", (mark, label) => {
  expect(feeLabel({ id: 1, version: 2, status: "declared", tutor_payment_mark: mark }))
    .toBe(`Đã khai báo · ${label}`);
});

it("preserves real Program blocker ids from structured server responses", () => {
  const error = toApiClientError({
    response: {
      status: 409,
      data: {
        code: "program_has_blockers",
        message: "Blocked",
        errors: {
          blockers: {
            sessions: [{ id: 14, status: "scheduled" }],
            tasks: [{ id: 27, status: "todo" }],
          },
        },
      },
    },
  });
  expect(error.blockers).toEqual({ sessions: [14], tasks: [27] });
});
