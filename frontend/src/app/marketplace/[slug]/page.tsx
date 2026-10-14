import { AppShell } from "@/components/app-shell";
import { ListingDetail } from "@/components/listing-detail";
import "@/app/public-detail.css";

export default async function MarketplaceListingPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  return <AppShell title="Chi tiết dịch vụ" mode="public"><ListingDetail slug={slug} /></AppShell>;
}
