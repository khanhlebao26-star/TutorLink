import { OwnerListings } from "@/components/owner-listings";
import { Suspense } from "react";
import { StatePanel } from "@/components/ui";

export default function OwnerListingsPage() { return <Suspense fallback={<StatePanel kind="loading" message="Đang tải dịch vụ…" />}><OwnerListings /></Suspense>; }
