export type Role = "customer" | "tutor" | "admin";
export type UserStatus = "pending" | "active" | "suspended" | "cancelled";
export type TutorApprovalStatus = "pending" | "active" | "rejected" | "cancelled" | "expired";
export type ListingStatus = "draft" | "active" | "inactive" | "cancelled";
export type DeliveryMode = "online" | "offline" | "hybrid";

export interface User {
  id: number;
  role: Role;
  status: UserStatus;
  full_name: string;
  email: string;
  email_verified_at: string | null;
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
  headline: string;
  bio: string | null;
  experience_years: number;
  approval_status: TutorApprovalStatus;
  submitted_at: string | null;
  reviewed_at: string | null;
}

export interface ProfileResponse {
  user: User;
  profile: CustomerProfile | TutorProfile;
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
