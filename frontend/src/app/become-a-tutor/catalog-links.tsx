"use client";

import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import type { CatalogCategory } from "@/lib/data/marketplace-types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { BarbellIcon } from "@phosphor-icons/react/dist/ssr/Barbell";
import { BooksIcon } from "@phosphor-icons/react/dist/ssr/Books";
import { SoccerBallIcon } from "@phosphor-icons/react/dist/ssr/SoccerBall";
import Link from "next/link";
import { useCallback } from "react";

type CatalogTone = "education" | "fitness" | "sports";

const heroTopics: Array<{ name: string; tone: CatalogTone; description: string; matchNames: string[] }> = [
  { name: "Giáo dục", tone: "education", description: "Học tập, ôn luyện, phát triển kỹ năng", matchNames: ["education", "giáo dục", "language", "ngôn ngữ", "học"] },
  { name: "Fitness", tone: "fitness", description: "Rèn luyện sức khỏe, cải thiện vóc dáng", matchNames: ["fitness", "gym", "thể hình"] },
  { name: "Thể thao", tone: "sports", description: "Nâng cao kỹ năng, thực hành cùng người hướng dẫn", matchNames: ["sports", "sport", "thể thao"] },
];

function CategoryIcon({ tone }: { tone: CatalogTone }) {
  if (tone === "education") return <BooksIcon weight="regular" aria-hidden="true" />;
  if (tone === "fitness") return <BarbellIcon weight="regular" aria-hidden="true" />;
  return <SoccerBallIcon weight="regular" aria-hidden="true" />;
}

export function CatalogLinks() {
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog, false);

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
