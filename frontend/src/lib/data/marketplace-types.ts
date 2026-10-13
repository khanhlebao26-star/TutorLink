// B3 integration proposal. A3 must confirm these additions before API acceptance.
import type { CatalogCategory, DeliveryMode, Listing, PaginationMeta } from "./types";

export type MarketplaceStatus = "draft" | "active" | "paused" | "hidden" | "archived";
export type ListingAction = "edit" | "publish" | "pause" | "archive";
export type PriceUnit = "hour" | "session" | "package";
export interface RatingSummary { average: number; count: number; histogram?: Record<1 | 2 | 3 | 4 | 5, number> }
export interface TutorSummary { slug: string; full_name: string; headline: string; avatar_url?: string | null; experience_years: number; rating?: RatingSummary | null }
export interface PublicListing extends Omit<Listing, "status"> {
  status: "active";
  tutor?: TutorSummary;
  category_name?: string;
  subcategory_name?: string;
  specialization_name?: string;
  rating?: RatingSummary | null;
  availability?: Availability | null;
}
export interface OwnerListing extends Omit<Listing, "status"> {
  status: MarketplaceStatus;
  allowed_actions: ListingAction[];
  category_name?: string;
  subcategory_name?: string;
  specialization_name?: string;
}
export interface ListingInput {
  title: string; description: string; specialization_id: number;
  delivery_mode: DeliveryMode; city: string | null; district: string | null;
  price_from_minor: number; currency: "VND"; price_unit: PriceUnit;
}
export interface PublicListingCollection { data: PublicListing[]; meta: PaginationMeta }
export interface OwnerListingCollection {
  data: OwnerListing[];
  meta: PaginationMeta & { status_counts: Record<MarketplaceStatus | "all", number>; allowed_actions: ("create")[] };
}
export interface ListingQuery {
  q?: string; category_id?: number; subcategory_id?: number; specialization_id?: number;
  delivery_mode?: DeliveryMode; city?: string; min_fee?: number; max_fee?: number;
  price_unit?: PriceUnit; sort?: "newest" | "price_asc" | "price_desc"; page?: number; per_page?: number;
}
export interface AvailabilityRule { weekday: number; start_time: string; end_time: string }
export interface Availability { timezone: string; rules: AvailabilityRule[] }
export interface VisibleReview { id: number; author_name: string; rating: number; comment: string; created_at: string }
export interface ReviewCollection { data: VisibleReview[]; meta: PaginationMeta; aggregate?: RatingSummary | null }
export interface TutorPublic extends TutorSummary {
  bio?: string | null;
  listings: PublicListing[];
  availability?: Availability | null;
}
export type { CatalogCategory, DeliveryMode, PaginationMeta };
