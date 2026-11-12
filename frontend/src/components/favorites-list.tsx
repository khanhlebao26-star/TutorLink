"use client";

import Image from "next/image";
import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { describeApiError } from "@/lib/api/client";
import { announceFavoriteUpdate, favoriteUpdateEvent, getFavoriteTutors, removeFavoriteTutor, type FavoriteTutor } from "@/lib/data/favorites";
import { deliveryLabels, formatListingPrice, getPublicTutor, getTutorReviews } from "@/lib/data/marketplace";
import type { TutorPublic, TutorReviewAggregate } from "@/lib/data/marketplace-types";
import { FavoritesLoadingState } from "./favorites-loading-state";

type FavoriteCardData = FavoriteTutor & {
  profile?: TutorPublic;
  rating?: TutorReviewAggregate | null;
};

const coverByCategory = {
  education: "/featured/education.svg",
  fitness: "/featured/fitness.svg",
  sports: "/featured/sports.svg",
} as const;

function categoryTone(value: string) {
  const normalized = value.toLocaleLowerCase("vi");
  if (normalized.includes("fitness") || normalized.includes("thể hình")) return "fitness";
  if (normalized.includes("sport") || normalized.includes("thể thao")) return "sports";
  return "education";
}

function initials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi");
}

function getCategory(profile?: TutorPublic) {
  return profile?.specializations.find((item) => item.category)?.category ?? profile?.specializations[0]?.subcategory ?? "Dịch vụ";
}

function FavoriteTutorCard({ tutor, removing, onRemove }: { tutor: FavoriteCardData; removing: boolean; onRemove: () => void }) {
  const profileHref = `/tutors/${encodeURIComponent(tutor.slug)}`;
  const listing = tutor.profile?.listings[0];
  const category = getCategory(tutor.profile);
  const tone = categoryTone(category);
  const specialization = tutor.profile?.specializations[0]?.name ?? listing?.specialization_name ?? tutor.headline ?? "Tutor / Coach";
  const mode = listing ? deliveryLabels[listing.delivery_mode] : "Chưa cập nhật hình thức";
  const rating = tutor.rating;
  const name = tutor.name || "TutorLink Tutor";

  return <article className={`favorite-tutor-card favorite-tutor-card-${tone}`}>
    <Link className="favorite-tutor-cover" href={listing ? `/marketplace/${encodeURIComponent(listing.slug)}` : profileHref} aria-label={`Xem dịch vụ của ${name}`}>
      <Image src={coverByCategory[tone]} alt={`Ảnh minh họa ${category}`} fill sizes="(max-width: 760px) 100vw, 200px" />
      <span className="favorite-cover-label">{category}</span>
    </Link>

    <div className="favorite-tutor-copy">
      <h2><Link href={profileHref}>{listing?.title ?? tutor.headline ?? `Gia sư ${specialization}`}</Link></h2>
      <Link className="favorite-tutor-owner" href={profileHref}>
        <span className="favorite-owner-avatar" aria-hidden="true">{initials(name)}</span>
        <strong>{name}</strong><span className="favorite-verified" aria-label="Hồ sơ công khai">✓</span>
      </Link>
      <div className="favorite-tutor-tags" aria-label="Lĩnh vực và hình thức">
        <span>{category}</span><span>{specialization}</span><span className="favorite-mode-tag">{mode}</span>
      </div>
      <p className="favorite-tutor-rating"><span aria-label="Đánh giá">★</span> <strong>{rating?.average?.toFixed(1) ?? "—"}</strong> <small>({rating?.count ?? 0} đánh giá)</small></p>
      <div className="favorite-provided-service">
        <strong>Dịch vụ đang cung cấp{tutor.profile ? ` (${tutor.profile.listings.length})` : ""}</strong>
        {listing ? <div className="favorite-service-detail"><span className="favorite-service-icon" aria-hidden="true">▤</span><span><b>{listing.title}</b><small>{formatListingPrice(listing.price_from_minor, listing.currency, listing.price_unit)} · {mode}</small></span></div> : <p>Liên hệ tutor để xem các dịch vụ hiện có.</p>}
      </div>
    </div>

    <div className="favorite-tutor-actions">
      <span className="favorite-saved-heart" aria-label="Đã lưu vào yêu thích">♥</span>
      <Link className="favorite-profile-action" href={profileHref}>Xem hồ sơ</Link>
      {listing ? <Link className="favorite-service-action" href={`/marketplace/${encodeURIComponent(listing.slug)}`}>Xem dịch vụ <span aria-hidden="true">→</span></Link> : <Link className="favorite-service-action" href={profileHref}>Xem dịch vụ <span aria-hidden="true">→</span></Link>}
      <button className="favorite-remove-action" type="button" onClick={onRemove} disabled={removing}>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
        {removing ? "Đang xóa…" : "Bỏ yêu thích"}
      </button>
    </div>
  </article>;
}

export function FavoritesList() {
  const [items, setItems] = useState<FavoriteCardData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [removing, setRemoving] = useState<string | null>(null);

  const load = useCallback(async () => {
    setError("");
    try {
      const favorites = await getFavoriteTutors();
      const enriched = await Promise.all(favorites.map(async (favorite): Promise<FavoriteCardData> => {
        const [profileResult, reviewResult] = await Promise.allSettled([
          getPublicTutor(favorite.slug),
          getTutorReviews(favorite.slug),
        ]);
        return {
          ...favorite,
          profile: profileResult.status === "fulfilled" ? profileResult.value : undefined,
          rating: reviewResult.status === "fulfilled" ? reviewResult.value.aggregate : null,
        };
      }));
      setItems(enriched);
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
    <nav className="favorites-breadcrumb" aria-label="Đường dẫn"><Link href="/">Trang chủ</Link><span aria-hidden="true">/</span><span>Yêu thích</span></nav>
    <h1 id="favorites-title">Tutor yêu thích</h1>
    <p className="favorites-intro">Các tutor bạn đã lưu. Bạn có thể xem lại thông tin, dịch vụ hoặc bỏ yêu thích bất cứ lúc nào.</p>
    {error ? <p className="favorites-error" role="alert">{error}</p> : null}
    {loading ? <FavoritesLoadingState /> : items.length ? <>
      <p className="favorites-count"><strong>{items.length}</strong> {items.length === 1 ? "tutor" : "tutor"}</p>
      <div className="favorites-grid">
        {items.map((tutor) => <FavoriteTutorCard key={tutor.slug} tutor={tutor} removing={removing === tutor.slug} onRemove={() => void remove(tutor.slug)} />)}
      </div>
    </> : <div className="favorites-empty"><strong>Bạn chưa lưu tutor nào</strong><p>Nhấn “Yêu thích” trên hồ sơ tutor để lưu vào danh sách này.</p><Link className="button button-primary" href="/marketplace">Khám phá dịch vụ</Link></div>}
  </section>;
}
