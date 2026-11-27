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

  if (catalog.loading) return <div className="landing-hero-catalog landing-hero-catalog-loading relative z-[3] m-0 rounded-2xl border border-[#f0e8f2] bg-white p-[9px] shadow-[0_12px_32px_#17254f0c] sm:p-[13px]" role="status" aria-label="Đang tải danh mục dịch vụ">
    <div className="landing-catalog-loading-head flex items-center gap-2 px-[3px] pb-2.5 pt-px text-[.72rem] text-[#7180a4]"><span className="landing-catalog-spinner" aria-hidden="true" /><span>Đang tải danh mục phù hợp với bạn…</span></div>
    <div className="landing-hero-catalog-grid landing-catalog-skeleton-grid grid grid-cols-1 gap-3 sm:grid-cols-3">
      {heroTopics.map(({ tone }) => <div className={`landing-hero-category landing-category-skeleton landing-hero-category-${tone}`} key={tone}>
        <span className="landing-skeleton-icon" />
        <span className="landing-skeleton-copy"><i /><i /></span>
        <span className="landing-skeleton-art" />
        <span className="landing-skeleton-arrow" />
      </div>)}
    </div>
  </div>;
  return <div className="landing-hero-catalog relative z-[3] m-0 rounded-2xl border border-[#f0e8f2] bg-white p-[9px] shadow-[0_12px_32px_#17254f0c] sm:p-[13px]" aria-label="Danh mục dịch vụ">
    <div className="landing-hero-catalog-grid grid grid-cols-1 gap-3 sm:grid-cols-3">
      {heroTopics.map(({ name, tone, description, matchNames }) => {
        const matchedCategory = matchNames
          .map((matchName) => catalog.data?.find((item) => `${item.name} ${item.slug}`.toLocaleLowerCase("vi").includes(matchName)))
          .find((category): category is CatalogCategory => Boolean(category));
        const href = matchedCategory ? `/marketplace?category=${matchedCategory.id}` : "/marketplace";
        return <Link className={`landing-hero-category landing-hero-category-${tone} relative grid min-h-[105px] grid-cols-[44px_minmax(0,1fr)_56px] items-center gap-2.5 rounded-[15px] border border-transparent p-[15px] text-brand transition-[box-shadow,transform] hover:-translate-y-0.5 hover:shadow-[0_10px_22px_#17254f12] sm:min-h-[120px] sm:grid-cols-[48px_minmax(0,1fr)_68px] sm:p-[17px]`} href={href} key={tone}>
          <span className="landing-hero-category-icon inline-flex h-[42px] w-[42px] items-center justify-center rounded-full bg-white/80 text-accent"><CategoryIcon tone={tone} /></span>
          <span className="landing-hero-category-copy grid self-center gap-1 pb-2.5">
            <strong>{name}</strong>
            <small>{description}</small>
          </span>
          <span className="landing-hero-category-art flex justify-center text-brand drop-shadow-[0_4px_3px_#071a4c18]"><CategoryIcon tone={tone} /></span>
          <span className="landing-hero-category-arrow absolute bottom-2 left-[15px] inline-flex h-[25px] w-[25px] items-center justify-center rounded-full bg-white/85 text-[.92rem] text-accent-dark" aria-hidden="true">→</span>
        </Link>;
      })}
    </div>
  </div>;
}
