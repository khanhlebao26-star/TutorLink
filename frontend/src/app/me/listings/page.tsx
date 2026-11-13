import { OwnerListings } from "@/components/marketplace/owner-listings";
import { StatePanel } from "@/components/shared/ui";
import { Suspense } from "react";

export default function OwnerListingsPage() { return <Suspense fallback={<StatePanel kind="loading" message="Đang tải dịch vụ…" />}><OwnerListings /></Suspense>; }
