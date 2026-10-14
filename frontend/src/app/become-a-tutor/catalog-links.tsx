"use client";

import Link from "next/link";
import { useCallback } from "react";
import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { ErrorActions, StatePanel } from "@/components/ui";

export function CatalogLinks() {
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog);

  if (catalog.loading) return <StatePanel kind="loading" message="Đang tải danh mục dịch vụ…" />;
  if (catalog.error) return <StatePanel kind="error" message={catalog.error.message} action={<ErrorActions onRetry={catalog.reload} />} />;
  if (!catalog.data?.length) return <StatePanel kind="empty" message="Hiện chưa có danh mục dịch vụ đang hoạt động." />;

  return <div className="landing-category-grid">
    {catalog.data.map((category) => <Link className="landing-category" href={"/marketplace?category=" + category.id} key={category.id}>
      <span className="landing-category-mark" aria-hidden="true">↗</span>
      <h3>{category.name}</h3>
      <span className="landing-category-action">Khám phá dịch vụ <span aria-hidden="true">→</span></span>
    </Link>)}
  </div>;
}
