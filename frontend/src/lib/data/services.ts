import { ApiClientError, dataMode, request, setAuthHint } from "../api/client";
import { mockAdminProfiles, mockCurrentUser, mockProfile, mockTutorOnboarding, setMockRole } from "./mock";
import type {
  AdminTutorProfile,
  ApiEnvelope,
  FilePurpose,
  FileRecord,
  ProfileResponse,
  RegisterRole,
  Specialization,
  SpecializationCollection,
  TutorOnboardingData,
  TutorProfile,
  TutorProfileStatus,
  User,
  VerificationDocument
} from "./types";
export const MAX_UPLOAD_BYTES = 5_000_000;

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

export async function getAdminTutorProfiles(status: TutorProfileStatus = "submitted",): Promise<AdminTutorProfile[]> {
  if (dataMode === "mock") { await delay(); return mockAdminProfiles(status); }
  const response = await request<ApiEnvelope<AdminTutorProfile[]>>({ method: "GET", url: `/admin/tutor-profiles?status=${encodeURIComponent(status)}` });
  return unwrap(response.data);
}

export async function getAdminTutorProfile(id: number): Promise<AdminTutorProfile> {
  if (dataMode === "mock") { await delay(); const profile = mockAdminProfiles().find((item) => item.profile.id === id); if (!profile) throw new ApiClientError("Không tìm thấy hồ sơ.", { status: 404 }); return profile; }
  const response = await request<ApiEnvelope<AdminTutorProfile>>({ method: "GET", url: `/admin/tutor-profiles/${id}` });
  return unwrap(response.data);
}

export type AdminAction = "approve" | "request-changes" | "reject" | "suspend" | "restore";

export async function performAdminTutorAction(id: number, action: AdminAction, reason: string): Promise<AdminTutorProfile> {
  if (dataMode === "mock") {
    await delay();
    const current = await getAdminTutorProfile(id);
const nextStatus: Record<AdminAction, TutorProfileStatus> = {
  approve: "approved",
  "request-changes": "changes_requested",
  reject: "rejected",
  suspend: "suspended",
  restore: "approved",
};

const profile: TutorProfile = {
  ...current.profile,
  status: nextStatus[action],
};

if (action === "suspend") {
  profile.suspended_at = new Date().toISOString();
  profile.suspension_reason = reason;
} else if (action === "restore") {
  profile.suspended_at = null;
  profile.suspension_reason = null;
} else {
  profile.review_reason = reason;
}    current.profile = profile;
    return (await getAdminTutorProfile(id));
  }
  const response = await request<ApiEnvelope<AdminTutorProfile>>({ method: "POST", url: `/admin/tutor-profiles/${id}/${action}`, data: { reason } }, true);
  return unwrap(response.data);
}

export async function performAdminAccountAction(id: number, action: "suspend" | "restore", reason: string): Promise<User> {
  if (dataMode === "mock") {
    await delay();
    const entry = mockAdminProfiles().find((item) => item.user.id === id);
    if (!entry) throw new ApiClientError("Không tìm thấy tài khoản.", { status: 404 });
    entry.user = { ...entry.user, status: action === "suspend" ? "suspended" : "active" };
    return entry.user;
  }
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: `/admin/users/${id}/${action}`, data: { reason } }, true);
  return unwrap(response.data);
}

export async function login(email: string, password: string, remember = false): Promise<User> {
  if (dataMode === "mock") {
    await delay();
    const user = mockCurrentUser();
    setAuthHint(true);
    return user;
  }
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/login", data: { email, password, remember } }, true);
  const user = unwrap(response.data);
  setAuthHint(true);
  return user;
}

export async function getCurrentUser(): Promise<User> {
  if (dataMode === "mock") { await delay(); return mockCurrentUser(); }
  const response = await request<ApiEnvelope<User>>({ method: "GET", url: "/me" });
  return unwrap(response.data);
}

export async function logout(): Promise<void> {
  if (dataMode === "mock") { await delay(); setAuthHint(false); return; }
  await request<void>({ method: "POST", url: "/auth/logout" }, true);
  setAuthHint(false);
}

export async function register(input: {
  full_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: RegisterRole;
  date_of_birth: string;
}): Promise<User> {
  if (dataMode === "mock") { await delay(); setMockRole(input.role); return mockCurrentUser(input.role); }
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
  const response = await request<SpecializationCollection>({ method: "GET", url: "/catalog/specializations" });
  return unwrap(response.data);
}

export async function getTutorProfile(): Promise<TutorProfile> {
  if (dataMode === "mock") { await delay(); return mockTutorOnboarding().profile as TutorProfile; }
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "GET", url: "/tutor/profile" });
  return unwrap(response.data);
}

export async function saveTutorProfile(input: {
  headline: string;
  bio?: string | null;
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
  if (file.size <= 0 || file.size > MAX_UPLOAD_BYTES) {
    throw new Error(
      "File phải có dung lượng từ 1 đến 5.000.000 byte (5 MB).",
    );
  }

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
