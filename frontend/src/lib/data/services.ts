import { ApiClientError, dataMode, request } from "../api/client";
import { mockAdminProfiles, mockCurrentUser, mockListings, mockProfile } from "./mock";
import type { ApiEnvelope, ListingView, ProfileResponse, Role, TutorProfile, User } from "./types";

const unwrap = <T>(value: ApiEnvelope<T> | T): T => typeof value === "object" && value !== null && "data" in value ? (value as ApiEnvelope<T>).data : value as T;
const delay = () => new Promise((resolve) => setTimeout(resolve, 220));

export async function getProfile(role: "customer" | "tutor"): Promise<ProfileResponse> {
  if (dataMode === "mock") { await delay(); return mockProfile(role); }
  const response = await request<ApiEnvelope<ProfileResponse>>({ method: "GET", url: role === "tutor" ? "/me/tutor-profile" : "/me" });
  return unwrap(response.data);
}

export async function getListings(): Promise<ListingView[]> {
  if (dataMode === "mock") { await delay(); return mockListings; }
  const response = await request<ApiEnvelope<ListingView[]>>({ method: "GET", url: "/listings" });
  return unwrap(response.data);
}

export async function getListing(slug: string): Promise<ListingView> {
  if (dataMode === "mock") { await delay(); const listing = mockListings.find((item) => item.slug === slug); if (!listing) throw new ApiClientError("Không tìm thấy dịch vụ.", { status: 404 }); return listing; }
  const response = await request<ApiEnvelope<ListingView>>({ method: "GET", url: `/listings/${encodeURIComponent(slug)}` });
  return unwrap(response.data);
}

export async function getAdminTutorProfiles(): Promise<Array<{ user: User; profile: TutorProfile }>> {
  if (dataMode === "mock") { await delay(); return mockAdminProfiles(); }
  const response = await request<ApiEnvelope<Array<{ user: User; profile: TutorProfile }>>>({ method: "GET", url: "/admin/tutor-profiles?status=pending" });
  return unwrap(response.data);
}

export async function login(email: string, password: string): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(); }
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/login", data: { email, password } }, true);
  return unwrap(response.data);
}

export async function register(input: { full_name: string; email: string; password: string; role: Role }): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(input.role === "admin" ? "tutor" : input.role); }
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/register", data: input }, true);
  return unwrap(response.data);
}

export const isMock = dataMode === "mock";
