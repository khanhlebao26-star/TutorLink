"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { describeApiError } from "@/lib/api/client";
import { announceFavoriteUpdate, favoriteUpdateEvent, getFavoriteTutors, removeFavoriteTutor, type FavoriteTutor } from "@/lib/data/favorites";

export function FavoritesList() {
  const [items, setItems] = useState<FavoriteTutor[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [removing, setRemoving] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError("");
    try {
      setItems(await getFavoriteTutors());
    } catch (reason) {
      setError(describeApiError(reason, "Chưa thể tải danh sách yêu thích."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
    window.addEventListener(favoriteUpdateEvent, load);
    return () => window.removeEventListener(favoriteUpdateEvent, load);
  }, [load]);

  async function remove(slug: string) {
    setRemoving(slug);
    setError("");
    try {
      await removeFavoriteTutor(slug);
      setItems((current) => current.filter((item) => item.slug !== slug));
      announceFavoriteUpdate();
    } catch (reason) {
      setError(describeApiError(reason, "Chưa thể xóa gia sư khỏi danh sách yêu thích."));
    } finally {
      setRemoving(null);
    }
  }

  return <section className="favorites-page" aria-labelledby="favorites-title">
    <p className="listing-program-eyebrow">DANH SÁCH CỦA BẠN</p>
    <h1 id="favorites-title">Tutor yêu thích</h1>
    <p className="favorites-intro">Các hồ sơ bạn đã lưu để tiện xem lại và liên hệ.</p>
    {error ? <p className="favorites-error" role="alert">{error}</p> : null}
    {loading ? <p className="favorites-empty">Đang tải danh sách yêu thích…</p> : items.length ? <div className="favorites-grid">
      {items.map((tutor) => <article className="favorite-tutor-card" key={tutor.slug}>
        <span className="listing-tutor-avatar" aria-hidden="true">{tutor.name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi")}</span>
        <div className="favorite-tutor-copy"><h2><Link href={`/tutors/${encodeURIComponent(tutor.slug)}`}>{tutor.name}</Link></h2><p>{tutor.headline || "Tutor / Coach trên TutorLink"}</p></div>
        <div className="favorite-tutor-actions"><Link href={`/tutors/${encodeURIComponent(tutor.slug)}`}>Xem hồ sơ</Link><button type="button" onClick={() => void remove(tutor.slug)} disabled={removing === tutor.slug}>{removing === tutor.slug ? "Đang xóa…" : "Bỏ yêu thích"}</button></div>
      </article>)}
    </div> : <div className="favorites-empty"><strong>Bạn chưa lưu tutor nào</strong><p>Nhấn “Yêu thích” trên hồ sơ tutor để lưu vào danh sách này.</p><Link className="button button-primary" href="/marketplace">Khám phá dịch vụ</Link></div>}
  </section>;
}
