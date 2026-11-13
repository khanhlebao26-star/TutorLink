"use client";

import { ErrorActions, StatePanel } from "@/components/shared/ui";
import { getTutorReviews, searchListings } from "@/lib/data/marketplace";
import type { PublicListing, TutorReviewAggregate } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Image from "next/image";
import Link from "next/link";
import { useCallback } from "react";

type FeaturedService = PublicListing & { reviewAggregate: TutorReviewAggregate | null };
type CoverTone = "education" | "fitness" | "sports";

const coverByTone: Record<CoverTone, string> = {
  education: "/featured/education.svg",
  fitness: "/featured/fitness.svg",
  sports: "/featured/sports.svg",
};

function getTone(category?: string): CoverTone {
  const value = (category ?? "").toLocaleLowerCase("vi");
  if (value.includes("fitness")) return "fitness";
  if (value.includes("sport") || value.includes("thể thao")) return "sports";
  return "education";
}

function getCategoryLabel(category?: string) {
  const tone = getTone(category);
  if (tone === "fitness") return "Fitness";
  if (tone === "sports") return "Thể thao";
  return "Giáo dục";
}

function formatPrice(amount: number) {
  return amount >= 100000 ? `${Math.round(amount / 1000)}K` : new Intl.NumberFormat("vi-VN").format(amount);
}

function tutorInitials(name?: string | null) {
  const parts = (name ?? "TutorLink").trim().split(/\s+/);
  return parts.slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi");
}

function pickFeatured(listings: PublicListing[]) {
  const remaining = [...listings];
  const selected: PublicListing[] = [];
  const categoryOrder = ["education", "fitness", "sports"];

  for (const category of categoryOrder) {
    const index = remaining.findIndex((listing) => {
      const name = (listing.category_name ?? "").toLocaleLowerCase("vi");
      return category === "education"
        ? name.includes("education") || name.includes("language") || name.includes("giáo dục")
        : name.includes(category) || (category === "sports" && name.includes("thể thao"));
    });
    if (index >= 0) selected.push(...remaining.splice(index, 1));
  }

  selected.push(...remaining.slice(0, Math.max(0, 3 - selected.length)));
  return selected.slice(0, 3);
}

async function loadFeaturedServices(): Promise<FeaturedService[]> {
  const response = await searchListings({ sort: "newest", per_page: 50 });
  const listings = pickFeatured(response.data);

  return Promise.all(listings.map(async (listing) => {
    if (!listing.tutor_slug) return { ...listing, reviewAggregate: null };
    try {
      const reviews = await getTutorReviews(listing.tutor_slug, 1);
      return { ...listing, reviewAggregate: reviews.aggregate };
    } catch {
      return { ...listing, reviewAggregate: null };
    }
  }));
}

function FeaturedCard({ listing }: { listing: FeaturedService }) {
  const tone = getTone(listing.category_name);
  const aggregate = listing.reviewAggregate;

  return <article className={`landing-featured-card landing-featured-card-${tone}`}>
    <Link className="landing-featured-cover" href={`/marketplace/${encodeURIComponent(listing.slug)}`} aria-label={`Xem dịch vụ ${listing.title}`}>
      <Image src={coverByTone[tone]} alt={`Ảnh minh họa mặc định cho lĩnh vực ${getCategoryLabel(listing.category_name)}`} fill sizes="(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw" />
      <span className="landing-featured-category">{getCategoryLabel(listing.category_name)}</span>
    </Link>
    <div className="landing-featured-body">
      <h3><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>{listing.title}</Link></h3>
      <p className="landing-featured-description">{listing.description}</p>
      <div className="landing-featured-meta">
        <span className="landing-featured-avatar" aria-hidden="true">{tutorInitials(listing.tutor_name)}</span>
        <span className="landing-featured-tutor"><strong>{listing.tutor_name || "TutorLink Tutor"}<span className="landing-featured-verified" aria-label="Hồ sơ đã được duyệt">✓</span></strong><small>{aggregate?.count ? `${aggregate.average?.toFixed(1)} ★ · ${aggregate.count} đánh giá` : "Chưa có đánh giá"}</small></span>
        <span className="landing-featured-price"><strong>{formatPrice(listing.price_from_minor)}</strong><small>/ {listing.price_unit === "session" ? "buổi" : listing.price_unit === "hour" ? "giờ" : "khóa"}</small></span>
      </div>
    </div>
  </article>;
}

function FeaturedSkeleton() {
  return <div className="landing-featured-grid" aria-hidden="true">
    {[0, 1, 2].map((item) => <div className="landing-featured-skeleton" key={item}><span /><i /><i /><i /></div>)}
  </div>;
}

export function FeaturedServices() {
  const load = useCallback(() => loadFeaturedServices(), []);
  const result = useAsyncData(load);

  return <section className="landing-featured-services landing-inner" aria-labelledby="landing-featured-title" aria-busy={result.loading}>
    <div className="landing-featured-heading">
      <div className="landing-section-intro">
        <p className="landing-eyebrow">ĐƯỢC QUAN TÂM NHẤT</p>
        <h2 id="landing-featured-title">Dịch vụ nổi bật</h2>
        <p>Khám phá những dịch vụ được nhiều học viên lựa chọn.</p>
      </div>
      <Link className="landing-featured-all" href="/marketplace">Xem tất cả dịch vụ <span aria-hidden="true">→</span></Link>
    </div>

    {result.loading ? <FeaturedSkeleton /> : result.error ? <StatePanel kind="error" message={result.error.message} action={<ErrorActions onRetry={result.reload} />} /> : result.data?.length ? <div className="landing-featured-grid">
      {result.data.map((listing) => <FeaturedCard listing={listing} key={listing.id} />)}
    </div> : <StatePanel kind="empty" message="Chưa có dịch vụ công khai. Hãy quay lại sau hoặc khám phá danh mục dịch vụ." action={<Link className="button button-secondary" href="/marketplace">Khám phá dịch vụ</Link>} />}
  </section>;
}
