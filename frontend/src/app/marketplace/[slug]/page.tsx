import { ListingDetail } from "@/components/listing-detail";
import { AppShell } from "@/components/app-shell";

export default async function ListingDetailPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  return (
    <AppShell title="Chi tiết Listing">
      <ListingDetail slug={slug} />
    </AppShell>
  );
}
