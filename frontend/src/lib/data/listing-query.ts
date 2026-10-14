import type { ListingQuery } from "./marketplace-types";

export const listingQueryKeys = ["q", "category", "subcategory", "specialization", "delivery_mode", "city", "min_fee", "max_fee", "price_unit", "sort", "page", "per_page"] as const;
export type ListingQueryKey = typeof listingQueryKeys[number];
const positiveInteger = (value: string | null) => value && /^\d+$/.test(value) && Number.isSafeInteger(Number(value)) && Number(value) > 0 ? Number(value) : undefined;
const fee = (value: string | null) => value && /^\d+$/.test(value) && Number.isSafeInteger(Number(value)) ? Number(value) : undefined;

export function readListingQuery(params: Pick<URLSearchParams, "get">): ListingQuery {
  const unit = params.get("price_unit");
  const price_unit = unit === "hour" || unit === "session" || unit === "package" ? unit : undefined;
  const mode = params.get("delivery_mode");
  const sort = params.get("sort");
  return {
    q: params.get("q")?.trim() || undefined,
    category_id: positiveInteger(params.get("category")),
    subcategory_id: positiveInteger(params.get("subcategory")),
    specialization_id: positiveInteger(params.get("specialization")),
    delivery_mode: mode === "online" || mode === "offline" || mode === "hybrid" ? mode : undefined,
    city: params.get("city")?.trim() || undefined,
    price_unit,
    min_fee: price_unit ? fee(params.get("min_fee")) : undefined,
    max_fee: price_unit ? fee(params.get("max_fee")) : undefined,
    sort: price_unit && (sort === "price_asc" || sort === "price_desc") ? sort : "newest",
    page: positiveInteger(params.get("page")) ?? 1,
    per_page: Math.min(positiveInteger(params.get("per_page")) ?? 6, 100),
  };
}

export function updateListingQuery(current: string, changes: Partial<Record<ListingQueryKey, string>>): string {
  const params = new URLSearchParams(current);
  for (const key of [...params.keys()]) if (!listingQueryKeys.includes(key as ListingQueryKey)) params.delete(key);
  for (const [key, value] of Object.entries(changes)) {
    if (value.trim()) params.set(key, value.trim()); else params.delete(key);
  }
  if ("category" in changes) { params.delete("subcategory"); params.delete("specialization"); }
  if ("subcategory" in changes) params.delete("specialization");
  if (!["hour", "session", "package"].includes(params.get("price_unit") ?? "")) {
    params.delete("price_unit"); params.delete("min_fee"); params.delete("max_fee");
    if (params.get("sort")?.startsWith("price_")) params.delete("sort");
  }
  if (Object.keys(changes).some((key) => key !== "page")) params.delete("page");
  return params.toString();
}
