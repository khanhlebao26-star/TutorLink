import { AppShell } from "@/components/layout/app-shell";
import { MarketplaceSearch } from "@/components/marketplace/marketplace-search";
import { StatePanel } from "@/components/shared/ui";
import "@/styles/marketplace.css";
import { Suspense } from "react";

export default function MarketplacePage() {
  return <AppShell mode="public" title="Khám phá dịch vụ">
    <Suspense fallback={<StatePanel kind="loading" message="Đang mở trang tìm dịch vụ…" />}>
      <MarketplaceSearch />
    </Suspense>
  </AppShell>;
}
