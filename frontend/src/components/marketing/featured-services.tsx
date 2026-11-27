"use client";

import { ErrorActions, StatePanel } from "@/components/shared/ui";
import { useFeaturedListings } from "@/components/marketing/featured-listing-provider";
import type { PublicListing, TutorReviewAggregate } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Image from "next/image";
import Link from "next/link";
import { useCallback } from "react";

type FeaturedService = PublicListing & { reviewAggregate: Pick<TutorReviewAggregate, "average" | "count"> | null };
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

async function loadFeaturedServices(getCandidates: ReturnType<typeof useFeaturedListings>): Promise<FeaturedService[]> {
  const response = await getCandidates();
  const listings = pickFeatured(response.data);

  return listings.map((listing) => ({ ...listing, reviewAggregate: listing.tutor_card
    ? { count: listing.tutor_card.rating_count, average: listing.tutor_card.rating_average } : null }));
}

function FeaturedCard({ listing }: { listing: FeaturedService }) {
  const tone = getTone(listing.category_name);
  const aggregate = listing.reviewAggregate;

  return <article className={`landing-featured-card landing-featured-card-${tone} min-w-0 overflow-hidden rounded-2xl border border-[#e4e8f2] bg-white shadow-[0_10px_28px_#13214b0a] transition-[box-shadow,transform] hover:-translate-y-[3px] hover:shadow-[0_16px_34px_#13214b16]`}>
    <Link className="landing-featured-cover relative block aspect-[2/1] overflow-hidden bg-[#f6f2fb]" href={`/marketplace/${encodeURIComponent(listing.slug)}`} aria-label={`Xem dịch vụ ${listing.title}`}>
      <Image src={coverByTone[tone]} alt={`Ảnh minh họa mặc định cho lĩnh vực ${getCategoryLabel(listing.category_name)}`} fill sizes="(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw" />
      <span className="landing-featured-category">{getCategoryLabel(listing.category_name)}</span>
    </Link>
    <div className="landing-featured-body px-4 pb-[14px] pt-[15px]">
      <h3 className="mb-[5px] text-[.97rem] leading-[1.4]"><Link href={`/marketplace/${encodeURIComponent(listing.slug)}`}>{listing.title}</Link></h3>
      <p className="landing-featured-description mb-0 min-h-[45px] overflow-hidden text-[.75rem] leading-6 text-[#7884a1]">{listing.description}</p>
      <div className="landing-featured-meta mt-[13px] flex items-center gap-[9px] border-t border-[#edf0f6] pt-3">
        <span className="landing-featured-avatar inline-flex h-9 w-9 flex-[0_0_36px] items-center justify-center rounded-full bg-[linear-gradient(135deg,#ff1a9b,#7c3aed)] text-[.63rem] font-extrabold text-white" aria-hidden="true">{tutorInitials(listing.tutor_name)}</span>
        <span className="landing-featured-tutor grid min-w-0"><strong className="overflow-hidden text-ellipsis whitespace-nowrap text-[.69rem] leading-[1.35]">{listing.tutor_name || "TutorLink Tutor"}<span className="landing-featured-verified ml-1 text-[.7rem] text-[#316fe8]" aria-label="Hồ sơ đã được duyệt">✓</span></strong><small className="text-[.61rem] leading-[1.35] text-[#7d89a5]">{aggregate?.count ? `${aggregate.average?.toFixed(1)} ★ · ${aggregate.count} đánh giá` : "Chưa có đánh giá"}</small></span>
        <span className="landing-featured-price ml-auto grid whitespace-nowrap text-right"><strong className="text-[.85rem] leading-[1.3] text-accent-dark">{formatPrice(listing.price_from_minor)}</strong><small className="text-[.6rem] text-[#7d89a5]">/ {listing.price_unit === "session" ? "buổi" : listing.price_unit === "hour" ? "giờ" : "khóa"}</small></span>
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
  const getCandidates = useFeaturedListings();
  const load = useCallback(() => loadFeaturedServices(getCandidates), [getCandidates]);
  const result = useAsyncData(load, false);

  return <section className="landing-featured-services landing-inner mx-auto max-w-[1250px] px-5 py-[52px] sm:px-[clamp(20px,5vw,64px)] sm:py-[clamp(58px,7vw,86px)]" aria-labelledby="landing-featured-title" aria-busy={result.loading}>
    <div className="landing-featured-heading mb-5 flex flex-col items-start justify-between gap-[14px] sm:mb-[25px] sm:flex-row sm:items-end sm:gap-6">
      <div className="landing-section-intro">
        <p className="landing-eyebrow">ĐƯỢC QUAN TÂM NHẤT</p>
        <h2 id="landing-featured-title">Dịch vụ nổi bật</h2>
        <p>Khám phá những dịch vụ được nhiều học viên lựa chọn.</p>
      </div>
      <Link className="landing-featured-all" href="/marketplace">Xem tất cả dịch vụ <span aria-hidden="true">→</span></Link>
    </div>

    {result.loading ? <FeaturedSkeleton /> : result.error ? <StatePanel kind="error" message={result.error.message} action={<ErrorActions onRetry={result.reload} />} /> : result.data?.length ? <div className="landing-featured-grid grid grid-cols-1 gap-[14px] sm:grid-cols-3 sm:gap-[18px]">
      {result.data.map((listing) => <FeaturedCard listing={listing} key={listing.id} />)}
    </div> : <StatePanel kind="empty" message="Chưa có dịch vụ công khai. Hãy quay lại sau hoặc khám phá danh mục dịch vụ." action={<Link className="button button-secondary" href="/marketplace">Khám phá dịch vụ</Link>} />}
  </section>;
}
