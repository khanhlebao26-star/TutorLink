import { api, request, toApiClientError } from "@/lib/api/client";
import { expect, it, vi } from "vitest";

it("bounds reads, preserves explicit timeout and leaves mutations unchanged", async () => {
  const send = vi.spyOn(api, "request").mockResolvedValue({ data: {} });
  await request({ url: "/listings" });
  expect(send).toHaveBeenLastCalledWith({ url: "/listings", timeout: 15_000 });
  await request({ url: "/files/1", timeout: 30_000 });
  expect(send).toHaveBeenLastCalledWith({ url: "/files/1", timeout: 30_000 });
  await request({ method: "PATCH", url: "/tasks/1", data: { expected_version: 1 } });
  expect(send).toHaveBeenLastCalledWith({ method: "PATCH", url: "/tasks/1", data: { expected_version: 1 } });
  expect(send).toHaveBeenCalledTimes(3);
});

it("describes a timeout in Vietnamese", () => {
  expect(toApiClientError({ code: "ECONNABORTED", message: "timeout" }).message)
    .toBe("Máy chủ phản hồi chậm. Vui lòng thử lại.");
});
