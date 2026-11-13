import type { FileRecord, TutorProfile } from "@/lib/data/types";

export type UploadPurpose = "avatar" | "verification_document";
export type UploadState = { file: FileRecord | null; message: string; error: string; busy: boolean };
export type FieldErrors = Record<string, string[]>;

export const steps = ["Thông tin cơ bản", "Chuyên môn", "Kinh nghiệm", "Tài liệu xác minh", "Xem lại"];
export const emptyUpload: UploadState = { file: null, message: "", error: "", busy: false };
export const verificationDocumentsRequired = process.env.NEXT_PUBLIC_TUTOR_PROFILE_DOCUMENTS_REQUIRED !== "false";

export function statusLabel(profile: TutorProfile | null) {
  if (!profile) return "Bản nháp";

  const labels: Record<TutorProfile["status"], string> = {
    draft: "Bản nháp",
    submitted: "Đã gửi xét duyệt",
    changes_requested: "Yêu cầu chỉnh sửa",
    approved: "Đã phê duyệt",
    rejected: "Đã từ chối",
    suspended: "Đình chỉ hồ sơ",
  };

  return labels[profile.status];
}

export function statusTone(
  profile: TutorProfile | null,
): "neutral" | "success" | "warning" | "brand" {
  if (!profile) return "brand";
  if (profile.status === "approved") return "success";

  if (
    profile.status === "submitted"
    || profile.status === "changes_requested"
    || profile.status === "suspended"
  ) {
    return "warning";
  }

  return "neutral";
}

export function editableProfile(profile: TutorProfile | null): TutorProfile | null {
  return profile?.status === "approved" && profile.revision ? { ...profile, ...profile.revision.payload, revision: profile.revision } : profile;
}
export function profileIsLocked(profile: TutorProfile | null) {
  if (profile?.status === "approved") return !!profile.revision && !["draft", "changes_requested", "approved"].includes(profile.revision.status);
  return profile !== null
    && profile.status !== "draft"
    && profile.status !== "changes_requested";
}

export function fieldStep(name: string) {
  if (name === "headline" || name === "bio") return 1;
  if (name === "specialization_id") return 2;
  if (name.startsWith("qualification_claims")) return 3;
  if (name === "experience_years") return 3;
  if (name === "avatar_file_id") return 1;
  if (name === "verification_document_file_id" || name === "document_type") return 4;
  return null;
}
