"use client";

import { useActor } from "@/components/layout/app-shell";
import { describeApiError } from "@/lib/api/client";
import { addFavoriteTutor, announceFavoriteUpdate, getFavoriteTutors, removeFavoriteTutor } from "@/lib/data/favorites";
import Link from "next/link";
import { useEffect, useState } from "react";

export function FavoriteTutorButton({ slug }: { slug: string }) {
  const user = useActor();
  if (!user) return <Link href="/auth/login" className="listing-secondary-action"><span aria-hidden="true">♡</span><span>Yêu thích</span></Link>;
  return <SavedTutorButton key={`${user.id}:${slug}`} slug={slug} />;
}

function SavedTutorButton({ slug }: { slug: string }) {
  const [saved, setSaved] = useState(false);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    let current = true;
    getFavoriteTutors()
      .then((favorites) => { if (current) setSaved(favorites.some((tutor) => tutor.slug === slug)); })
      .finally(() => { if (current) setLoading(false); })
      .catch(() => { /* A failed lookup leaves the button unsaved. */ });
    return () => { current = false; };
  }, [slug]);

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
