export type Role = "customer" | "tutor" | "admin";
export type RegisterRole = Exclude<Role, "admin">;
export type UserStatus = "pending" | "active" | "suspended" | "cancelled";
export type TutorApprovalStatus = "pending" | "changes_requested" | "active" | "rejected" | "cancelled" | "expired";
export type TutorProfileStatus = "draft" | "submitted" | "changes_requested" | "approved" | "rejected";
export type FilePurpose = "avatar" | "verification_document" | "task_attachment" | "profile_document";
export type ListingStatus = "draft" | "active" | "inactive" | "cancelled";
export type DeliveryMode = "online" | "offline" | "hybrid";

export interface User {
  id: number;
  role: Role;
  status: UserStatus;
  full_name: string;
  email: string;
  date_of_birth?: string | null;
  email_verified_at: string | null;
  profile?: CustomerProfile | TutorProfile | null;
}

export interface FileRecord {
  id: number;
  owner_id: number;
  original_name: string | null;
  mime_type: string;
  size_bytes: number;
  purpose: FilePurpose;
  scan_status: "pending" | "clean" | "rejected";
  complete: boolean;
  created_at: string;
}

export interface CustomerProfile {
  id: number;
  user_id: number;
  avatar_file_id: number | null;
  city: string | null;
  district: string | null;
  bio: string | null;
}

export interface TutorProfile {
  id: number;
  user_id: number;
  specialization_id: number | null;
  avatar_file_id: number | null;
  verification_document_file_id: number | null;
  headline: string;
  bio: string | null;
  experience_years: number;
  status: TutorProfileStatus;
  approval_status: TutorApprovalStatus;
  submitted_at: string | null;
  review_reason: string | null;
  reviewed_by?: number | null;
  reviewed_at: string | null;
  review_feedback?: string | null;
  suspended_at?: string | null;
  suspension_reason?: string | null;
}

export interface VerificationDocument {
  id: number;
  tutor_profile_id: number;
  file_id: number;
  document_type: string;
  status: "pending" | "active" | "rejected" | "expired";
  reviewed_at?: string | null;
  original_name?: string | null;
  mime_type?: string;
  size_bytes?: number;
  scan_status?: string;
  download_url?: string;
}

export interface AdminTutorProfile {
  user: User;
  profile: TutorProfile;
  verification_documents: VerificationDocument[] | null;
}

export interface ProfileResponse {
  user: User;
  profile: CustomerProfile | TutorProfile;
}

export interface TutorOnboardingData {
  user: User;
  profile: TutorProfile | null;
  specializations: Specialization[];
}

export interface Specialization {
  id: number;
  name: string;
  slug: string;
  status?: "active" | "inactive";
}

export interface Listing {
  id: number;
  tutor_profile_id: number;
  specialization_id: number;
  slug: string;
  title: string;
  description: string;
  delivery_mode: DeliveryMode;
  city: string | null;
  district: string | null;
  price_from_minor: number;
  currency: string;
  price_unit: string;
  status: ListingStatus;
}

export interface ListingView extends Listing {
  tutor_name: string;
  specialization_name: string;
}

export interface ApiEnvelope<T> {
  data: T;
  meta?: Record<string, unknown>;
  request_id?: string;
}
