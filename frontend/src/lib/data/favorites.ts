import { request } from "@/lib/api/client";
import type { ApiEnvelope } from "@/lib/data/types";

export interface FavoriteTutor {
  slug: string;
  name: string;
  headline: string | null;
  created_at: string;
}

export const favoriteUpdateEvent = "tutorlink-favorites-updated";

export async function getFavoriteTutors() {
  const response = await request<ApiEnvelope<FavoriteTutor[]>>({
    method: "GET",
    url: "/me/favorites",
  });
  return response.data.data;
}

export async function addFavoriteTutor(slug: string) {
  await request({ method: "POST", url: `/me/favorites/${encodeURIComponent(slug)}` }, true);
}

export async function removeFavoriteTutor(slug: string) {
  await request({ method: "DELETE", url: `/me/favorites/${encodeURIComponent(slug)}` }, true);
}

export function announceFavoriteUpdate() {
  if (typeof window !== "undefined") window.dispatchEvent(new Event(favoriteUpdateEvent));
}
