import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { MarketplaceSearch } from "@/components/marketplace-search";
import { StatePanel } from "@/components/ui";
import "../marketplace.css";

export default function MarketplacePage() {
  return <AppShell mode="public" title="Khám phá dịch vụ">
    <Suspense fallback={<StatePanel kind="loading" message="Đang mở trang tìm dịch vụ…" />}>
      <MarketplaceSearch />
    </Suspense>
  </AppShell>;
}
