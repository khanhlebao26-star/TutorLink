import type { AdminTutorProfile, CustomerProfile, ListingView, ProfileResponse, Role, TutorOnboardingData, TutorProfile, User } from "./types";

const mockRoleKey = "tutorlink-mock-role";

export function getMockRole(): Role {
  if (typeof window === "undefined") return "tutor";
  const role = window.localStorage.getItem(mockRoleKey);
  return role === "customer" || role === "admin" ? role : "tutor";
}

export function setMockRole(role: Role) {
  window.localStorage.setItem(mockRoleKey, role);
  window.dispatchEvent(new Event(mockRoleKey));
}

const users: Record<string, User> = {
  customer: { id: 302, role: "customer", status: "active", full_name: "Trần Gia Bảo", email: "bao@example.test", date_of_birth: "1998-01-12", email_verified_at: "2026-10-06T08:00:00Z" },
  tutor: { id: 301, role: "tutor", status: "active", full_name: "Nguyễn Minh Anh", email: "anh@example.test", date_of_birth: "1995-04-02", email_verified_at: "2026-10-06T08:00:00Z" },
  pendingTutor: { id: 303, role: "tutor", status: "active", full_name: "Lê Thu Hà", email: "ha@example.test", date_of_birth: "1997-06-18", email_verified_at: "2026-10-06T08:00:00Z" },
  admin: { id: 304, role: "admin", status: "active", full_name: "Phạm Quỳnh Trang", email: "admin@example.test", email_verified_at: "2026-10-06T08:00:00Z" },
};

const customerProfile: CustomerProfile = { id: 401, user_id: users.customer.id, avatar_file_id: null, city: "Đà Nẵng", district: "Hải Châu", bio: "Đang tìm gia sư tiếng Anh giao tiếp." };
const tutorProfile: TutorProfile = { id: 201, user_id: users.tutor.id, specialization_id: 12, avatar_file_id: 701, verification_document_file_id: 702, headline: "Gia sư tiếng Anh giao tiếp", bio: "Tám năm hướng dẫn người đi làm luyện nói và phỏng vấn.", experience_years: 8, status: "approved", approval_status: "active", submitted_at: "2026-10-06T08:00:00Z", review_reason: null, reviewed_at: "2026-10-06T09:30:00Z", created_at: "2026-10-01T08:00:00Z", updated_at: "2026-10-06T09:30:00Z", };
const pendingProfile: TutorProfile = { id: 202, user_id: users.pendingTutor.id, specialization_id: 21, avatar_file_id: null, verification_document_file_id: null, headline: "Toán lớp 9 online", bio: "Hỗ trợ học sinh củng cố nền tảng và chuẩn bị thi vào 10.", experience_years: 4, status: "submitted", approval_status: "pending", submitted_at: "2026-10-07T03:00:00Z", review_reason: null, reviewed_at: null,  };

export const mockListings: ListingView[] = [
{
  id: 501,
  tutor_profile_id: 201,
  specialization_id: 12,
  slug: "tieng-anh-giao-tiep-cho-nguoi-di-lam",
  title: "Tiếng Anh giao tiếp cho người đi làm",
  description: "Lộ trình thực hành theo tình huống họp, thuyết trình và phỏng vấn.",
  delivery_mode: "hybrid",
  city: "Đà Nẵng",
  district: "Hải Châu",
  price_from_minor: 480000,
  currency: "VND",
  price_unit: "hour",
  status: "active",
  tutor_name: users.tutor.full_name,
  specialization_name: "Tiếng Anh giao tiếp",
  created_at: "2026-10-01T08:00:00Z",
  updated_at: "2026-10-01T08:00:00Z",
},
{
  id: 502,
  tutor_profile_id: 201,
  specialization_id: 12,
  slug: "luyen-phong-van-tieng-anh-online",
  title: "Luyện phỏng vấn tiếng Anh online",
  description: "Thực hành trả lời phỏng vấn và giới thiệu bản thân cùng gia sư.",
  delivery_mode: "online",
  city: "Đà Nẵng",
  district: "Hải Châu",
  price_from_minor: 480000,
  currency: "VND",
  price_unit: "hour",
  status: "active",
  tutor_name: users.tutor.full_name,
  specialization_name: "Tiếng Anh giao tiếp",
  created_at: "2026-10-01T08:00:00Z",
  updated_at: "2026-10-01T08:00:00Z",
}];

export function mockCurrentUser(role: Role = getMockRole()): User { return users[role]; }
export function mockProfile(role: "customer" | "tutor"): ProfileResponse { return role === "customer" ? { user: users.customer, profile: customerProfile } : { user: users.tutor, profile: tutorProfile }; }
export function mockTutorOnboarding(): TutorOnboardingData { return { user: users.tutor, profile: tutorProfile, specializations: [{ id: 12, subcategory_id: 1, name: "Tiếng Anh giao tiếp", slug: "tieng-anh-giao-tiep", status: "active", }] }; }
const adminProfiles: AdminTutorProfile[] = [
  { user: { ...users.pendingTutor }, profile: { ...pendingProfile }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.tutor }, profile: { ...tutorProfile }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.pendingTutor, id: 305, full_name: "Đỗ Hoàng Nam", email: "nam@example.test" }, profile: { ...pendingProfile, id: 203, user_id: 305, status: "changes_requested", approval_status: "changes_requested", review_reason: "Vui lòng bổ sung mô tả kinh nghiệm giảng dạy." }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.pendingTutor, id: 306, full_name: "Vũ Thảo Linh", email: "linh@example.test" }, profile: { ...pendingProfile, id: 204, user_id: 306, status: "rejected", approval_status: "rejected", review_reason: "Hồ sơ xác minh chưa đáp ứng yêu cầu." }, allowed_actions: [], verification_documents: [] },
];

// Preview decisions persist until a full page reload; no backend is involved.
export function mockAdminProfiles(status?: string): AdminTutorProfile[] {
  for (const entry of adminProfiles) {
    entry.allowed_actions = entry.user.status === "inactive"
      ? ["account-restore"]
      : entry.profile.approval_status === "active"
        ? [entry.profile.suspended_at ? "restore" : "suspend", "account-suspend"]
        : entry.profile.approval_status === "rejected"
          ? ["account-suspend"]
          : ["approve", "request-changes", "reject", "account-suspend"];
  }
  return status ? adminProfiles.filter((entry) => entry.profile.approval_status === status) : adminProfiles;
}
