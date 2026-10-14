// B3 integration proposal. A3 must confirm these additions before API acceptance.
import type { CatalogCategory, DeliveryMode, Listing, ListingStatus, PaginationMeta } from "./types";

export type MarketplaceStatus = ListingStatus;
export type PriceUnit = "hour" | "session" | "course";
export interface PublicListing extends Omit<Listing, "status"> {
  status: "active";
  tutor_name?: string | null;
  category_name?: string;
  subcategory_name?: string;
  specialization_name?: string;
}
export interface OwnerListing extends Omit<Listing, "status"> {
  status: MarketplaceStatus;
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
  meta: PaginationMeta;
}
export interface ListingQuery {
  q?: string; category_id?: number; subcategory_id?: number; specialization_id?: number;
  delivery_mode?: DeliveryMode; city?: string; min_price?: number; max_price?: number;
  price_unit?: PriceUnit; sort?: "newest" | "price_asc" | "price_desc"; page?: number; per_page?: number;
}
export interface AvailabilityRule {
  weekday: number;
  start_time: string;
  end_time: string;
  timezone?: string;
}
export interface Availability { timezone: string; rules: AvailabilityRule[] }
export interface PublicTutorSpecialization { id: number; name: string; slug: string; subcategory: string | null; category: string | null }
export interface PublicTutorAvailabilityRule extends AvailabilityRule { timezone: string }
export interface TutorPublic {
  slug: string;
  name: string | null;
  headline: string;
  bio?: string | null;
  experience_years: number;
  specializations: PublicTutorSpecialization[];
  availability_rules: PublicTutorAvailabilityRule[];
  listings: PublicListing[];
}
export type { CatalogCategory, DeliveryMode, PaginationMeta };
