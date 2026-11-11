"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { describeApiError } from "@/lib/api/client";
import { announceFavoriteUpdate, getFavoriteTutors, addFavoriteTutor, removeFavoriteTutor } from "@/lib/data/favorites";
import { useActor } from "./app-shell";

export function FavoriteTutorButton({ slug }: { slug: string }) {
  const user = useActor();
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(Boolean(user));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const refresh = useCallback(async () => {
    if (!user) {
      setLoading(false);
      setSaved(false);
      return;
    }
    try {
      const favorites = await getFavoriteTutors();
      setSaved(favorites.some((tutor) => tutor.slug === slug));
    } catch {
      setSaved(false);
    } finally {
      setLoading(false);
    }
  }, [slug, user]);

  useEffect(() => { void refresh(); }, [refresh]);

  if (!user) return <Link href="/auth/login" className="listing-secondary-action"><span aria-hidden="true">♡</span><span>Yêu thích</span></Link>;

  async function toggleFavorite() {
    if (busy || loading) return;
    setBusy(true);
    setError("");
    try {
      if (saved) await removeFavoriteTutor(slug);
      else await addFavoriteTutor(slug);
      setSaved(!saved);
      announceFavoriteUpdate();
    } catch (reason) {
      setError(describeApiError(reason, "Chưa thể cập nhật danh sách yêu thích."));
    } finally {
      setBusy(false);
    }
  }

  return <>
    <button className={`listing-secondary-action${saved ? " is-favorite" : ""}`} type="button" aria-pressed={saved} disabled={loading || busy} onClick={toggleFavorite}>
      <span aria-hidden="true">{saved ? "♥" : "♡"}</span>
      <span>{loading ? "Đang kiểm tra…" : saved ? "Đã yêu thích" : "Yêu thích"}</span>
    </button>
    {error ? <p className="favorite-action-error" role="alert">{error}</p> : null}
  </>;
}
