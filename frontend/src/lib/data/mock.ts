import type { AdminTutorProfile, CustomerProfile, ProfileResponse, Role, TutorOnboardingData, TutorProfile, User } from "./types";

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
const tutorProfile: TutorProfile = { id: 201, user_id: users.tutor.id, specialization_id: 12, avatar_file_id: 701, verification_document_file_id: 702, headline: "Gia sư tiếng Anh giao tiếp", bio: "Tám năm hướng dẫn người đi làm luyện nói và phỏng vấn.", experience_years: 8, status: "approved", submitted_at: "2026-10-06T08:00:00Z", review_reason: null, reviewed_at: "2026-10-06T09:30:00Z", created_at: "2026-10-01T08:00:00Z", updated_at: "2026-10-06T09:30:00Z", };
const pendingProfile: TutorProfile = { id: 202, user_id: users.pendingTutor.id, specialization_id: 21, avatar_file_id: null, verification_document_file_id: null, headline: "Toán lớp 9 online", bio: "Hỗ trợ học sinh củng cố nền tảng và chuẩn bị thi vào 10.", experience_years: 4, status: "submitted", submitted_at: "2026-10-07T03:00:00Z", review_reason: null, reviewed_at: null,  };

export function mockCurrentUser(role: Role = getMockRole()): User { return users[role]; }
export function mockProfile(role: "customer" | "tutor"): ProfileResponse { return role === "customer" ? { user: users.customer, profile: customerProfile } : { user: users.tutor, profile: tutorProfile }; }
export function mockTutorOnboarding(): TutorOnboardingData { return { user: users.tutor, profile: tutorProfile, specializations: [{ id: 12, subcategory_id: 1, name: "Tiếng Anh giao tiếp", slug: "tieng-anh-giao-tiep", status: "active", }] }; }
const adminProfiles: AdminTutorProfile[] = [
  { user: { ...users.pendingTutor }, profile: { ...pendingProfile }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.tutor }, profile: { ...tutorProfile }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.pendingTutor, id: 305, full_name: "Đỗ Hoàng Nam", email: "nam@example.test" }, profile: { ...pendingProfile, id: 203, user_id: 305, status: "changes_requested", review_reason: "Vui lòng bổ sung mô tả kinh nghiệm giảng dạy." }, allowed_actions: [], verification_documents: [] },
  { user: { ...users.pendingTutor, id: 306, full_name: "Vũ Thảo Linh", email: "linh@example.test" }, profile: { ...pendingProfile, id: 204, user_id: 306, status: "rejected", review_reason: "Hồ sơ xác minh chưa đáp ứng yêu cầu." }, allowed_actions: [], verification_documents: [] },
];

// Preview decisions persist until a full page reload; no backend is involved.
export function mockAdminProfiles(
  status?: TutorProfile["status"],
): AdminTutorProfile[] {
  for (const entry of adminProfiles) {
  const actions: AdminTutorProfile["allowed_actions"] = [];

    if (entry.user.status === "active") {
      actions.push("account-suspend");

      if (entry.profile.status === "submitted") {
        actions.push("approve", "request-changes", "reject");
      }
    } else if (entry.user.status === "suspended") {
      actions.push("account-restore");
    }

    if (entry.profile.status === "approved") {
      actions.push("suspend");
    } else if (entry.profile.status === "suspended") {
      actions.push("restore");
    }

    entry.allowed_actions = actions;
  }

  return status
    ? adminProfiles.filter((entry) => entry.profile.status === status)
    : adminProfiles;
}
