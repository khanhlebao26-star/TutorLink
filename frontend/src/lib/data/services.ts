import { ApiClientError, request, setAuthHint } from "@/lib/api/client";
import type {
  AdminTutorProfile,
  AdminTutorQueueItem,
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
} from "@/lib/data/types";
export const MAX_UPLOAD_BYTES = 5_000_000;

const unwrap = <T>(value: ApiEnvelope<T> | T): T =>
  typeof value === "object" && value !== null && "data" in value
    ? (value as ApiEnvelope<T>).data
    : value as T;
const notifyAccountUpdated = () => {
  if (typeof window !== "undefined") {
    window.dispatchEvent(new Event("tutorlink-account-updated"));
  }
};

export async function getProfile(role: "customer" | "tutor"): Promise<ProfileResponse> {

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
  const response = await request<ApiEnvelope<AdminTutorProfile[]>>({ method: "GET", url: `/admin/tutor-profiles?status=${encodeURIComponent(status)}&scope=mine` });
  return unwrap(response.data);
}

export async function getAdminTutorQueue(status: TutorProfileStatus = "submitted"): Promise<AdminTutorQueueItem[]> {
  const response = await request<ApiEnvelope<AdminTutorQueueItem[]>>({ method: "GET", url: `/admin/tutor-profiles?status=${encodeURIComponent(status)}&scope=unassigned` });
  return unwrap(response.data);
}

export async function getAdminTutorReviewPage(scope: "unassigned" | "mine", status: TutorProfileStatus, page: number) {
  const perPage = 10;
  const response = await request<ApiEnvelope<Array<AdminTutorProfile | AdminTutorQueueItem>>>({
    method: "GET",
    url: `/admin/tutor-profiles?status=${encodeURIComponent(status)}&scope=${scope}&page=${page}&per_page=${perPage}`,
  });
  return { items: response.data.data, total: Number(response.data.meta?.total ?? 0), page, perPage };
}

export async function assignAdminTutorProfile(id: number, adminId: number, assignmentVersion: number): Promise<void> {
  await request({
    method: "PUT",
    url: `/admin/tutor-profiles/${id}/assignment`,
    data: {
      assigned_admin_id: adminId,
      assignment_version: assignmentVersion,
      reason: "Admin nhận hồ sơ để xét duyệt.",
    },
  }, true);
}

export async function getAdminTutorProfile(id: number): Promise<AdminTutorProfile> {
  const response = await request<ApiEnvelope<AdminTutorProfile>>({ method: "GET", url: `/admin/tutor-profiles/${id}` });
  return unwrap(response.data);
}

export type AdminAction = "approve" | "request-changes" | "reject" | "suspend" | "restore";

export async function performAdminTutorAction(id: number, action: AdminAction, reason: string, context: AdminTutorProfile): Promise<AdminTutorProfile> {
  const decision=!["suspend","restore"].includes(action);
  if(decision && context.assignment?.assignment_version === undefined) throw new ApiClientError("Tải lại hồ sơ trước khi quyết định.");
  await request({ method:"POST", url:`/admin/tutor-profiles/${id}/${action}`, data:{reason,...(decision?{assignment_version:context.assignment?.assignment_version,...(context.revision?{expected_version:context.revision.version}:{})}:{})} },true);
  return getAdminTutorProfile(id);
}

export async function performAdminAccountAction(id: number, action: "suspend" | "restore", reason: string): Promise<User> {
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: `/admin/users/${id}/${action}`, data: { reason } }, true);
  return unwrap(response.data);
}

export async function login(email: string, password: string, remember = false): Promise<User> {
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/login", data: { email, password, remember } }, true);
  const user = unwrap(response.data);
  setAuthHint(true);
  notifyAccountUpdated();
  return user;
}

export async function getCurrentUser(): Promise<User> {
  const response = await request<ApiEnvelope<User>>({ method: "GET", url: "/me" });
  return unwrap(response.data);
}

export async function logout(): Promise<void> {
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
  const response = await request<ApiEnvelope<User>>({ method: "POST", url: "/auth/register", data: input }, true);
  return unwrap(response.data);
}

export async function verifyEmail(id: string, hash: string, signature?: { expires?: string; signature?: string }): Promise<User> {
  const query = new URLSearchParams(
    Object.entries(signature ?? {}).filter((entry): entry is [string, string] => Boolean(entry[1])),
  ).toString();
  const response = await request<ApiEnvelope<User>>({
    method: "GET",
    url: `/auth/email/verify/${encodeURIComponent(id)}/${encodeURIComponent(hash)}${query ? `?${query}` : ""}`,
  });
  return unwrap(response.data);
}

export async function resendVerification(email?: string): Promise<string> {
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/email/verification-notification", data: email ? {email} : undefined }, true);
  return response.data.message;
}

export async function requestPasswordReset(email: string): Promise<string> {
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/forgot-password", data: { email } }, true);
  return response.data.message;
}

export async function resetPassword(input: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<string> {
  const response = await request<{ message: string }>({ method: "POST", url: "/auth/reset-password", data: input }, true);
  return response.data.message;
}

export async function getSpecializations(): Promise<Specialization[]> {
  const response = await request<SpecializationCollection>({ method: "GET", url: "/catalog/specializations" });
  return unwrap(response.data);
}

export async function getTutorProfile(): Promise<TutorProfile> {
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "GET", url: "/tutor/profile" });
  return unwrap(response.data);
}

export async function saveTutorProfile(input: {
  expected_version?: number;
  qualification_claims?: Array<{type:"degree"|"certificate";name:string}>;
  headline: string;
  bio?: string | null;
  experience_years: number;
  specialization_id: number;
}): Promise<TutorProfile> {
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "PUT", url: "/tutor/profile", data: input }, true);
  return unwrap(response.data);
}

export async function submitTutorProfile(): Promise<TutorProfile> {
  const response = await request<ApiEnvelope<TutorProfile>>({ method: "POST", url: "/tutor/profile/submit" }, true);
  return unwrap(response.data);
}

export async function uploadFile(file: File, purpose: FilePurpose, onProgress?: (percent:number)=>void): Promise<FileRecord> {
  if (file.size <= 0 || file.size > MAX_UPLOAD_BYTES) {
    throw new Error(
      "File phải có dung lượng từ 1 đến 5.000.000 byte (5 MB).",
    );
  }

  const formData = new FormData();
  formData.append("file", file);
  formData.append("purpose", purpose);
  const response = await request<ApiEnvelope<FileRecord>>({ method: "POST", url: "/files", data: formData, onUploadProgress: event => {if(event.total)onProgress?.(Math.round(event.loaded/event.total*100));} }, true);
  return unwrap(response.data);
}

export async function completeFile(fileId: number): Promise<FileRecord> {
  const response = await request<ApiEnvelope<FileRecord>>({ method: "POST", url: `/files/${fileId}/complete` }, true);
  return unwrap(response.data);
}

export async function attachTutorAvatar(fileId: number, expectedVersion?: number): Promise<TutorProfile> {
  const response = await request<ApiEnvelope<TutorProfile>>({
    method: "PUT",
    url: "/tutor/profile/avatar",
    data: { file_id: fileId, ...(expectedVersion!==undefined?{expected_version:expectedVersion}:{}) },
  }, true);
  return unwrap(response.data);
}

export async function createVerificationDocument(input: {
  fileId: number;
  documentType: string;
}): Promise<VerificationDocument> {
  const response = await request<ApiEnvelope<VerificationDocument>>({
    method: "POST",
    url: "/tutor/profile/verification-documents",
    data: { file_id: input.fileId, document_type: input.documentType },
  }, true);
  return unwrap(response.data);
}
