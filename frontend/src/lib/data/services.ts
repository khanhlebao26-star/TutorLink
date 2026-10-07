import { ApiClientError, dataMode, request } from "../api/client";
import { mockAdminProfiles, mockCurrentUser, mockListings, mockProfile, mockTutorOnboarding } from "./mock";
import type {
  ApiEnvelope,
  FilePurpose,
  FileRecord,
  ListingView,
  ProfileResponse,
  RegisterRole,
  Specialization,
  TutorOnboardingData,
  TutorProfile,
  User,
  VerificationDocument,
} from "./types";

const unwrap = <T>(value: ApiEnvelope<T> | T): T =>
  typeof value === "object" && value !== null && "data" in value
    ? (value as ApiEnvelope<T>).data
    : value as T;
const delay = () => new Promise((resolve) => setTimeout(resolve, 220));

export async function getProfile(role: "customer" | "tutor"): Promise<ProfileResponse> {
  if (dataMode === "mock") {
    await delay();
    return mockProfile(role);
  }

  const user = await getCurrentUser();
  if (role === "customer") {
    if (!user.profile || "headline" in user.profile) {
      throw new ApiClientError("Customer profile không tồn tại.", { status: 404 });
    }
    return { user, profile: user.profile };
  }

  const profile = await getTutorProfile();
  return { user, profile };
}

export async function getTutorOnboarding(): Promise<TutorOnboardingData> {
  if (dataMode === "mock") {
    await delay();
    return mockTutorOnboarding();
  }

  const user = await getCurrentUser();
  if (user.role !== "tutor") {
    throw new ApiClientError("Chỉ tài khoản Tutor mới có thể mở onboarding.", { status: 403 });
  }

  const specializations = await getSpecializations();

  let profile: TutorProfile | null = null;
  try {
    profile = await getTutorProfile();
  } catch (reason) {
    if (!(reason instanceof ApiClientError) || reason.status !== 404) {
      throw reason;
    }
  }

  return { user, profile, specializations };
}

export async function getListings(): Promise<ListingView[]> {
  if (dataMode === "mock") { await delay(); return mockListings; }
  const response = await request<ApiEnvelope<ListingView[]>>({ method: "GET", url: "/listings" });
  return unwrap(response.data);
}

export async function getListing(slug: string): Promise<ListingView> {
  if (dataMode === "mock") {
    await delay();
    const listing = mockListings.find((item) => item.slug === slug);
    if (!listing) throw new ApiClientError("Không tìm thấy dịch vụ.", { status: 404 });
    return listing;
  }
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

export async function getCurrentUser(): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(); }
  const response = await request<ApiEnvelope<User>>({ method: "GET", url: "/me" });
  return unwrap(response.data);
}

export async function logout(): Promise<void> {
  if (dataMode === "mock") { await delay(); return; }
  await request<void>({ method: "POST", url: "/auth/logout" }, true);
}

export async function register(input: {
  full_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: RegisterRole;
  date_of_birth: string;
}): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(input.role); }
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/register", data: input }, true);
  return unwrap(response.data);
}

export async function verifyEmail(id: string, hash: string, signature?: { expires?: string; signature?: string }): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(); }
  const query = new URLSearchParams(
    Object.entries(signature ?? {}).filter((entry): entry is [string, string] => Boolean(entry[1])),
  ).toString();
  const response = await request<ApiEnvelope<User>>({
    method: "GET",
    url: `/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}${query ? `?${query}` : ""}`,
  });
  return unwrap(response.data);
}

export async function resendVerification(): Promise<string> {
  if (dataMode === "mock") { await delay(); return "Verification notification sent."; }
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/email/verification-notification" }, true);
  return response.data.message;
}

export async function requestPasswordReset(email: string): Promise<string> {
  if (dataMode === "mock") { await delay(); return "Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi."; }
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/forgot-password", data: { email } }, true);
  return response.data.message;
}

export async function resetPassword(input: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  if (dataMode === "mock") { await delay(); return "Đặt lại mật khẩu thành công."; }
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/reset-password", data: input }, true);
  return response.data.message;
}

export async function getSpecializations(): Promise<Specialization[]> {
  if (dataMode === "mock") { await delay(); return mockTutorOnboarding().specializations; }
  const response = await request<ApiEnvelope<Specialization[]>>({ method: "GET", url: "/catalog/specializations" });
  return unwrap(response.data);
}

export async function getTutorProfile(): Promise<TutorProfile> {
  if (dataMode === "mock") { await delay(); return mockTutorOnboarding().profile as TutorProfile; }
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "GET", url: "/tutor/profile" });
  return unwrap(response.data);
}

export async function saveTutorProfile(input: {
  headline: string;
  bio: string;
  experience_years: number;
  specialization_id: number;
}): Promise<TutorProfile> {
  if (dataMode === "mock") { await delay(); return { ...mockTutorOnboarding().profile as TutorProfile, ...input, status: "draft" }; }
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "PUT", url: "/tutor/profile", data: input }, true);
  return unwrap(response.data);
}

export async function submitTutorProfile(): Promise<TutorProfile> {
  if (dataMode === "mock") { await delay(); return { ...mockTutorOnboarding().profile as TutorProfile, status: "submitted", submitted_at: new Date().toISOString() }; }
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "POST", url: "/tutor/profile/submit" }, true);
  return unwrap(response.data);
}

export async function uploadFile(file: File, purpose: FilePurpose): Promise<FileRecord> {
  if (dataMode === "mock") {
    await delay();
    return {
      id: Date.now(),
      owner_id: mockCurrentUser().id,
      original_name: file.name,
      mime_type: file.type,
      size_bytes: file.size,
      purpose,
      scan_status: "pending",
      complete: false,
      created_at: new Date().toISOString(),
    };
  }
  const formData = new FormData();
  formData.append("file", file);
  formData.append("purpose", purpose);
  const response = await request<ApiEnvelope<FileRecord>>({ method: "POST", url: "/files", data: formData }, true);
  return unwrap(response.data);
}

export async function completeFile(fileId: number): Promise<FileRecord> {
  if (dataMode === "mock") {
    await delay();
    return {
      id: fileId,
      owner_id: mockCurrentUser().id,
      original_name: "completed-file",
      mime_type: "application/octet-stream",
      size_bytes: 0,
      purpose: "profile_document",
      scan_status: "clean",
      complete: true,
      created_at: new Date().toISOString(),
    };
  }
  const response = await request<ApiEnvelope<FileRecord>>({ method: "POST", url: `/files/${fileId}/complete` }, true);
  return unwrap(response.data);
}

export async function attachTutorAvatar(fileId: number): Promise<TutorProfile> {
  if (dataMode === "mock") {
    await delay();
    return { ...mockTutorOnboarding().profile as TutorProfile, avatar_file_id: fileId };
  }
  const response = await request<ApiEnvelope<TutorProfile>>({
    method: "PUT",
    url: "/tutor/profile/avatar",
    data: { file_id: fileId },
  }, true);
  return unwrap(response.data);
}

export async function createVerificationDocument(input: {
  fileId: number;
  documentType: string;
}): Promise<VerificationDocument> {
  if (dataMode === "mock") {
    await delay();
    return {
      id: Date.now(),
      tutor_profile_id: mockTutorOnboarding().profile?.id ?? 0,
      file_id: input.fileId,
      document_type: input.documentType,
      status: "pending",
    };
  }
  const response = await request<ApiEnvelope<VerificationDocument>>({
    method: "POST",
    url: "/tutor/profile/verification-documents",
    data: { file_id: input.fileId, document_type: input.documentType },
  }, true);
  return unwrap(response.data);
}

export const isMock = dataMode === "mock";
