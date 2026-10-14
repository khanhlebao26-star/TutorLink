import { ApiClientError, request } from "../api/client";
import type { ApiEnvelope } from "./types";
import type { Availability, CatalogCategory, ListingInput, ListingQuery, MarketplaceStatus, OwnerListing, OwnerListingCollection, PublicListing, PublicListingCollection, ReviewCollection, TutorPublic } from "./marketplace-types";

// Marketplace always calls the backend; legacy auth preview mode cannot supply Listing data.
export async function getMarketplaceCatalog(): Promise<CatalogCategory[]> {
  const response = await request<ApiEnvelope<CatalogCategory[]>>({ method: "GET", url: "/catalog/categories" });
  return response.data.data.filter((category) => category.status === "active").map((category) => ({ ...category,
    subcategories: category.subcategories.filter((subcategory) => subcategory.status === "active").map((subcategory) => ({ ...subcategory, specializations: subcategory.specializations.filter((item) => item.status === "active") })),
  }));
}
export async function searchListings(params: ListingQuery): Promise<PublicListingCollection> {
  if ((params.min_fee !== undefined || params.max_fee !== undefined || params.sort?.startsWith("price_")) && !params.price_unit) {
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
export async function getOwnerListings(status?: MarketplaceStatus, page = 1): Promise<OwnerListingCollection> {
  const response = await request<OwnerListingCollection>({ method: "GET", url: "/me/listings", params: { status, page } });
  requirePagination(response.data);
  const meta = response.data.meta;
  if (!meta.status_counts || ["all", "draft", "active", "paused", "hidden", "archived"].some((key) => !Number.isSafeInteger(meta.status_counts[key as keyof typeof meta.status_counts]) || meta.status_counts[key as keyof typeof meta.status_counts] < 0) || !Array.isArray(meta.allowed_actions) || !Array.isArray(response.data.data) || response.data.data.some((item) => !Array.isArray(item.allowed_actions))) {
    throw new ApiClientError("Máy chủ chưa trả đủ quyền thao tác và số lượng trạng thái. Hãy thử lại sau.");
  }
  return response.data;
}
export async function getOwnerListing(id: number): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "GET", url: `/me/listings/${id}` });
  if (!Array.isArray(response.data.data.allowed_actions)) throw new ApiClientError("Máy chủ chưa trả quyền thao tác cho dịch vụ này.");
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
export async function transitionOwnerListing(id: number, status: "active" | "paused"): Promise<OwnerListing> {
  const response = await request<ApiEnvelope<OwnerListing>>({ method: "PATCH", url: `/me/listings/${id}`, data: { status } }, true);
  return response.data.data;
}
// Proposal: A3 DELETE performs terminal archive and preserves Program references.
export async function archiveOwnerListing(id: number): Promise<void> {
  await request({ method: "DELETE", url: `/me/listings/${id}` }, true);
}
export async function getPublicTutor(slug: string): Promise<TutorPublic> {
  const response = await request<ApiEnvelope<TutorPublic>>({ method: "GET", url: `/tutors/${encodeURIComponent(slug)}` });
  return response.data.data;
}
export async function getVisibleReviews(slug: string, page = 1): Promise<ReviewCollection> {
  const response = await request<ReviewCollection>({ method: "GET", url: `/tutors/${encodeURIComponent(slug)}/reviews`, params: { page, per_page: 5 } });
  requirePagination(response.data);
  return response.data;
}
export async function getOwnerAvailability(): Promise<Availability> {
  const response = await request<ApiEnvelope<Availability>>({ method: "GET", url: "/me/availability-rules" });
  return response.data.data;
}
export async function saveOwnerAvailability(input: Availability): Promise<Availability> {
  const response = await request<ApiEnvelope<Availability>>({ method: "PUT", url: "/me/availability-rules", data: input }, true);
  return response.data.data;
}
export const deliveryLabels = { online: "Trực tuyến", offline: "Trực tiếp", hybrid: "Kết hợp" };
export const priceUnitLabels: Record<string, string> = { hour: "giờ", session: "buổi", package: "gói" };
export const statusLabels = { draft: "Bản nháp", active: "Đang hoạt động", paused: "Tạm dừng", hidden: "Đã ẩn", archived: "Đã lưu trữ" };
export function formatListingPrice(amount: number, currency: string, unit: string) {
  return `${new Intl.NumberFormat("vi-VN").format(amount)} ${currency} / ${priceUnitLabels[unit] ?? unit}`;
}
