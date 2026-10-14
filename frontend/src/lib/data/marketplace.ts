import { ApiClientError, request } from "../api/client";
import type { Availability, AvailabilityRule, CatalogCategory, ListingInput, ListingQuery, OwnerListing, OwnerListingCollection, PublicListing, PublicListingCollection, TutorPublic } from "./marketplace-types";
import type { ApiEnvelope } from "./types";

// Marketplace always calls the backend; legacy auth preview mode cannot supply Listing data.
export async function getMarketplaceCatalog(): Promise<CatalogCategory[]> {
  const response = await request<ApiEnvelope<CatalogCategory[]>>({ method: "GET", url: "/catalog/categories" });
  return response.data.data.filter((category) => category.status === "active").map((category) => ({ ...category,
    subcategories: category.subcategories.filter((subcategory) => subcategory.status === "active").map((subcategory) => ({ ...subcategory, specializations: subcategory.specializations.filter((item) => item.status === "active") })),
  }));
}
export async function searchListings(params: ListingQuery): Promise<PublicListingCollection> {
  if ((params.min_price !== undefined || params.max_price !== undefined || params.sort?.startsWith("price_")) && !params.price_unit) {
    throw new ApiClientError("Chọn đơn vị phí trước khi lọc hoặc sắp xếp theo giá.", { status: 422, fieldErrors: { price_unit: ["Vui lòng chọn đơn vị phí."] } });
  }
  const response = await request<PublicListingCollection>({ method: "GET", url: "/listings", params });
  requirePagination(response.data);
  if (!Array.isArray(response.data.data)) throw new ApiClientError("Máy chủ chưa trả danh sách dịch vụ hợp lệ.");
  response.data.data.forEach(requirePublicListing);
  return response.data;
}
function requirePagination(value: { meta?: { total?: number; current_page?: number; per_page?: number } }) {
  if (!value.meta || !Number.isSafeInteger(value.meta.total) || value.meta.total! < 0 || !Number.isSafeInteger(value.meta.current_page) || value.meta.current_page! < 1 || !Number.isSafeInteger(value.meta.per_page) || value.meta.per_page! < 1) {
    throw new ApiClientError("Máy chủ chưa trả đủ thông tin phân trang. Hãy thử lại sau.");
  }
}
export async function getPublicListing(slug: string): Promise<PublicListing> {
  const response = await request<ApiEnvelope<PublicListing>>({ method: "GET", url: `/listings/${encodeURIComponent(slug)}` });
  requirePublicListing(response.data.data);
  return response.data.data;
}
function requirePublicListing(value: PublicListing) {
  if (!value || value.status !== "active" || typeof value.slug !== "string" || typeof value.title !== "string" || typeof value.description !== "string" || !Number.isSafeInteger(value.price_from_minor) || value.price_from_minor < 0) {
    throw new ApiClientError("Chưa tải được dịch vụ công khai hợp lệ. Hãy thử lại sau.");
  }
}
export async function getOwnerListings(page = 1): Promise<OwnerListingCollection> {
  const response = await request<OwnerListingCollection>({ method: "GET", url: "/me/listings", params: { page } });
  requirePagination(response.data);
  if (!Array.isArray(response.data.data)) throw new ApiClientError("Máy chủ chưa trả danh sách dịch vụ hợp lệ.");
  return response.data;
}
export async function getOwnerListing(id: number): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "GET", url: `/me/listings/${id}` });
  return response.data.data;
}
export async function createOwnerListing(input: ListingInput): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "POST", url: "/me/listings", data: input }, true);
  return response.data.data;
}
export async function updateOwnerListing(id: number, input: ListingInput): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "PATCH", url: `/me/listings/${id}`, data: input }, true);
  return response.data.data;
}
export async function transitionOwnerListing(id: number, status: "active" | "inactive"): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "PATCH", url: `/me/listings/${id}`, data: { status } }, true);
  return response.data.data;
}
export async function archiveOwnerListing(id: number): Promise<void> {
  await request({ method: "DELETE", url: `/me/listings/${id}` }, true);
}
export async function getPublicTutor(slug: string): Promise<TutorPublic> {
  const response = await request<ApiEnvelope<TutorPublic>>({ method: "GET", url: `/tutors/${encodeURIComponent(slug)}` });
  return response.data.data;
}
export async function getOwnerAvailability(): Promise<Availability> {
  const response = await request<ApiEnvelope<AvailabilityRule[]>>({ method: "GET", url: "/me/availability-rules" });
  const rules = response.data.data ?? [];
  return {
    timezone: rules[0]?.timezone ?? "Asia/Ho_Chi_Minh",
    rules: rules.map((rule) => ({
      weekday: rule.weekday,
      start_time: rule.start_time,
      end_time: rule.end_time,
    })),
  };
}
export async function saveOwnerAvailability(input: Availability): Promise<Availability> {
  const response = await request<ApiEnvelope<AvailabilityRule[]>>({
    method: "PUT",
    url: "/me/availability-rules",
    data: {
      rules: input.rules.map((rule) => ({ ...rule, timezone: input.timezone })),
    },
  }, true);
  const rules = response.data.data ?? [];
  return {
    timezone: rules[0]?.timezone ?? input.timezone,
    rules: rules.map((rule) => ({
      weekday: rule.weekday,
      start_time: rule.start_time,
      end_time: rule.end_time,
    })),
  };
}
export const deliveryLabels = { online: "Trực tuyến", offline: "Trực tiếp", hybrid: "Kết hợp" };
export const priceUnitLabels: Record<string, string> = { hour: "giờ", session: "buổi", course: "khóa học" };
export const statusLabels = { draft: "Bản nháp", active: "Đang hoạt động", inactive: "Ngừng hoạt động", cancelled: "Đã hủy" };
export function formatListingPrice(amount: number, currency: string, unit: string) {
  return `${new Intl.NumberFormat("vi-VN").format(amount)} ${currency} / ${priceUnitLabels[unit] ?? unit}`;
}
