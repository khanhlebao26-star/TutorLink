import { request } from "@/lib/api/client";
import { getFeaturedListingCandidates, getMarketplaceCatalog, getTutorReviews } from "@/lib/data/marketplace";
import { AxiosHeaders } from "axios";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", () => (() => { const send = vi.fn(); return { request: send, publicRequest: send }; })());

const send = vi.mocked(request);
const headers = new AxiosHeaders();
const response = (data: unknown) => ({ data, status: 200, statusText: "OK", headers, config: { headers } });

beforeEach(() => {
  send.mockReset();
});

it("shares pending catalog and featured listing requests, then fetches fresh data", async () => {
  send.mockResolvedValueOnce(response({ data: [] }));
  send.mockResolvedValueOnce(response({ data: [], meta: { total: 0, current_page: 1, per_page: 50 } }));

  await Promise.all([
    getMarketplaceCatalog(),
    getMarketplaceCatalog(),
    getFeaturedListingCandidates(),
    getFeaturedListingCandidates(),
  ]);
  expect(send).toHaveBeenCalledTimes(2);

  send.mockResolvedValueOnce(response({ data: [] }));
  await getMarketplaceCatalog();
  expect(send).toHaveBeenCalledTimes(3);
});

it("shares reviews per tutor and page and releases failed requests for retry", async () => {
  const offline = new Error("offline");
  send.mockRejectedValue(offline);

  const first = getTutorReviews("tutor-a");
  expect(getTutorReviews("tutor-a", 1)).toBe(first);
  const results = await Promise.allSettled([
    first,
    getTutorReviews("tutor-a", 2),
    getTutorReviews("tutor-b"),
  ]);
  expect(send).toHaveBeenCalledTimes(3);
  expect(results.every((result) => result.status === "rejected" && result.reason === offline)).toBe(true);

  await expect(getTutorReviews("tutor-a")).rejects.toBe(offline);
  expect(send).toHaveBeenCalledTimes(4);
});
