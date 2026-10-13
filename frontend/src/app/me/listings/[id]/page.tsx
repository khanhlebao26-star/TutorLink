import { notFound } from "next/navigation";
import { OwnerListingDetail } from "@/components/owner-listings";

export default async function OwnerListingPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const listingId = Number(id);
  if (!/^\d+$/.test(id) || !Number.isSafeInteger(listingId) || listingId < 1) notFound();
  return <OwnerListingDetail key={listingId} id={listingId} />;
}
