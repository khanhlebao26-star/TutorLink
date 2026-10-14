import { notFound } from "next/navigation";
import { ListingForm } from "@/components/listing-form";

export default async function EditListingPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const listingId = Number(id);
  if (!/^\d+$/.test(id) || !Number.isSafeInteger(listingId) || listingId < 1) notFound();
  return <ListingForm key={listingId} id={listingId} />;
}
