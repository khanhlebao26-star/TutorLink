"use client";

import { ErrorActions, StatePanel } from "@/components/shared/ui";
import { getFeaturedListingCandidates } from "@/lib/data/marketplace";
import type { PublicListing, TutorPublic, TutorReviewAggregate } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Image from "next/image";
import Link from "next/link";
import { useCallback } from "react";

type Companion = {
  listing: PublicListing;
  profile: Pick<TutorPublic, "slug" | "name" | "headline" | "experience_years"> | null;
  rating: Pick<TutorReviewAggregate, "average" | "count"> | null;
};
type CompanionTone = "education" | "fitness" | "sports";

const categoryPriority: CompanionTone[] = ["education", "fitness", "sports"];
const imageByCategory: Record<CompanionTone, string> = {
  education: "/featured/education.svg",
  fitness: "/featured/fitness.svg",
  sports: "/featured/sports.svg",
};

function getTone(category?: string): CompanionTone {
  const normalized = (category ?? "").toLocaleLowerCase("vi");
  if (normalized.includes("fitness")) return "fitness";
  if (normalized.includes("sport") || normalized.includes("thể thao")) return "sports";
  return "education";
}

function categoryLabel(category?: string) {
  const tone = getTone(category);
  if (tone === "fitness") return "Fitness";
  if (tone === "sports") return "Thể thao";
  return "Giáo dục";
}

function selectCompanions(listings: PublicListing[]) {
  const remaining = [...listings];
  const selected: PublicListing[] = [];

  for (const tone of categoryPriority) {
    const index = remaining.findIndex((listing) => getTone(listing.category_name) === tone);
    if (index >= 0) selected.push(...remaining.splice(index, 1));
  }

  for (const listing of remaining) {
    if (selected.length >= 3) break;
    if (!selected.some((item) => item.tutor_slug === listing.tutor_slug)) selected.push(listing);
  }

  const seen = new Set<string>();
  return selected.filter((listing) => {
    const key = listing.tutor_slug ?? String(listing.tutor_profile_id);
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  }).slice(0, 3);
}

async function loadCompanions(): Promise<Companion[]> {
  const response = await getFeaturedListingCandidates();
  const listings = selectCompanions(response.data);

  return listings.map((listing) => ({ listing,
    profile: listing.tutor_card && listing.tutor_slug ? { slug: listing.tutor_slug, name: listing.tutor_name ?? null,
      headline: listing.tutor_card.headline ?? "", experience_years: listing.tutor_card.experience_years } : null,
    rating: listing.tutor_card ? { count: listing.tutor_card.rating_count, average: listing.tutor_card.rating_average } : null,
  }));
}

function CompanionCard({ companion }: { companion: Companion }) {
  const { listing, profile, rating } = companion;
  const tone = getTone(listing.category_name);
  const tutorName = profile?.name || listing.tutor_name || "TutorLink Tutor";
  const title = profile?.headline || listing.specialization_name || categoryLabel(listing.category_name);
  const href = profile?.slug ? `/tutors/${encodeURIComponent(profile.slug)}` : `/marketplace/${encodeURIComponent(listing.slug)}`;

  return <article className={`landing-companion-card landing-companion-card-${tone}`}>
    <Link className="landing-companion-image" href={href} aria-label={`Xem hồ sơ ${tutorName}`}>
      <Image src={imageByCategory[tone]} alt={`Ảnh minh họa mặc định cho người dạy lĩnh vực ${categoryLabel(listing.category_name)}`} fill sizes="(max-width: 760px) 100vw, (max-width: 1100px) 50vw, 33vw" />
      <span>{categoryLabel(listing.category_name)}</span>
    </Link>
    <div className="landing-companion-body">
      <h3><Link href={href}>{tutorName}</Link><span aria-label="Hồ sơ đã được duyệt">✓</span></h3>
      <p>{title}{profile ? ` · ${profile.experience_years} năm kinh nghiệm` : ""}</p>
      <div className="landing-companion-rating">
        {rating?.count ? <><span aria-hidden="true">★</span> {rating.average?.toFixed(1)} · {rating.count} đánh giá</> : "Chưa có đánh giá"}
      </div>
    </div>
  </article>;
}

function CompanionSkeleton() {
  return <div className="landing-companion-grid" aria-hidden="true">
    {[0, 1, 2].map((item) => <div className="landing-companion-skeleton" key={item}><span /><i /><i /></div>)}
  </div>;
}

export function FeaturedTutors() {
  const load = useCallback(() => loadCompanions(), []);
  const result = useAsyncData(load);

  return <section className="landing-companions landing-inner" aria-labelledby="landing-companions-title" aria-busy={result.loading}>
    <div className="landing-section-intro">
      <p className="landing-eyebrow">NGƯỜI ĐỒNG HÀNH PHÙ HỢP</p>
      <h2 id="landing-companions-title">Gặp gỡ Tutor &amp; Coach nổi bật</h2>
      <p>Kinh nghiệm thực tế, chuyên môn vững vàng và luôn tận tâm với hành trình của bạn.</p>
    </div>
    {result.loading ? <CompanionSkeleton /> : result.error ? <StatePanel kind="error" message={result.error.message} action={<ErrorActions onRetry={result.reload} />} /> : result.data?.length ? <div className="landing-companion-grid">
      {result.data.map((companion) => <CompanionCard companion={companion} key={companion.listing.tutor_slug ?? companion.listing.id} />)}
    </div> : <StatePanel kind="empty" message="Chưa có hồ sơ người dạy công khai để giới thiệu." action={<Link className="button button-secondary" href="/marketplace">Khám phá dịch vụ</Link>} />}
    <div className="landing-companions-action"><Link href="/marketplace">Xem tất cả dịch vụ <span aria-hidden="true">→</span></Link></div>
  </section>;
}
