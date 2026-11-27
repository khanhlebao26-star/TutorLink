import { request } from "@/lib/api/client";
import { addFavoriteTutor, announceFavoriteUpdate, favoriteUpdateEvent, getFavoriteTutors, removeFavoriteTutor } from "@/lib/data/favorites";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", () => ({ request: vi.fn() }));

beforeEach(() => vi.clearAllMocks());

it("reads and mutates favorites using encoded slugs and broadcasts updates", async () => {
  const saved = [{ slug: "anna", name: "Anna Tutor", headline: null, created_at: "2026-01-01" }];
  vi.mocked(request).mockResolvedValueOnce({ data: { data: saved } } as never);
  await expect(getFavoriteTutors()).resolves.toEqual(saved);
  expect(request).toHaveBeenCalledWith({ method: "GET", url: "/me/favorites" });

  await addFavoriteTutor("tutor name");
  expect(request).toHaveBeenLastCalledWith({ method: "POST", url: "/me/favorites/tutor%20name" }, true);
  await removeFavoriteTutor("tutor/name");
  expect(request).toHaveBeenLastCalledWith({ method: "DELETE", url: "/me/favorites/tutor%2Fname" }, true);

  const listener = vi.fn();
  window.addEventListener(favoriteUpdateEvent, listener);
  announceFavoriteUpdate();
  expect(listener).toHaveBeenCalledOnce();
  window.removeEventListener(favoriteUpdateEvent, listener);
});
