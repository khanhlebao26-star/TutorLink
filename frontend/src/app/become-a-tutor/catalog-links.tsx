"use client";

import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import type { CatalogCategory } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import Link from "next/link";
import { useCallback } from "react";

type CatalogTone = "education" | "fitness" | "sports";

const heroTopics: Array<{ name: string; tone: CatalogTone; description: string; matchNames: string[] }> = [
  { name: "Giáo dục", tone: "education", description: "Học tập, ôn luyện, phát triển kỹ năng", matchNames: ["education", "giáo dục", "language", "ngôn ngữ", "học"] },
  { name: "Fitness", tone: "fitness", description: "Rèn luyện sức khỏe, cải thiện vóc dáng", matchNames: ["fitness", "gym", "thể hình"] },
  { name: "Thể thao", tone: "sports", description: "Nâng cao kỹ năng, thực hành cùng người hướng dẫn", matchNames: ["sports", "sport", "thể thao"] },
];

function CategoryIcon({ tone }: { tone: CatalogTone }) {
  if (tone === "education") return <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <path d="m5 11 18-2 2 25-18 2-2-25ZM16 14l18-2 2 25-18 2-2-25ZM27 17l14-1 2 21-14 1-2-21Z" />
  </svg>;
  if (tone === "fitness") return <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <path d="M5 17v14M11 12v24M17 19v10M31 19v10M37 12v24M43 17v14M17 24h14" />
  </svg>;
  return <svg viewBox="0 0 48 48" fill="none" aria-hidden="true">
    <circle cx="24" cy="24" r="18" />
    <path d="m19 7 5 7-4 7-8 1-6-5M37 13l-7 2-2 8 6 5 8-3M15 39l-1-9 7-5 8 4 1 9M18 21l7 3 5-2" />
  </svg>;
}

export function CatalogLinks() {
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog);

  if (catalog.loading) return <div className="landing-hero-catalog landing-hero-catalog-loading" role="status" aria-label="Đang tải danh mục dịch vụ">
    <div className="landing-catalog-loading-head"><span className="landing-catalog-spinner" aria-hidden="true" /><span>Đang tải danh mục phù hợp với bạn…</span></div>
    <div className="landing-hero-catalog-grid landing-catalog-skeleton-grid" aria-hidden="true">
      {heroTopics.map(({ tone }) => <div className={`landing-hero-category landing-category-skeleton landing-hero-category-${tone}`} key={tone}>
        <span className="landing-skeleton-icon" />
        <span className="landing-skeleton-copy"><i /><i /></span>
        <span className="landing-skeleton-art" />
        <span className="landing-skeleton-arrow" />
      </div>)}
    </div>
  </div>;
  return <div className="landing-hero-catalog" aria-label="Danh mục dịch vụ">
    <div className="landing-hero-catalog-grid">
      {heroTopics.map(({ name, tone, description, matchNames }) => {
        const matchedCategory = matchNames
          .map((matchName) => catalog.data?.find((item) => `${item.name} ${item.slug}`.toLocaleLowerCase("vi").includes(matchName)))
          .find((category): category is CatalogCategory => Boolean(category));
        const href = matchedCategory ? `/marketplace?category=${matchedCategory.id}` : "/marketplace";
        return <Link className={`landing-hero-category landing-hero-category-${tone}`} href={href} key={tone}>
          <span className="landing-hero-category-icon"><CategoryIcon tone={tone} /></span>
          <span className="landing-hero-category-copy">
            <strong>{name}</strong>
            <small>{description}</small>
          </span>
          <span className="landing-hero-category-art"><CategoryIcon tone={tone} /></span>
          <span className="landing-hero-category-arrow" aria-hidden="true">→</span>
        </Link>;
      })}
    </div>
  </div>;
}
