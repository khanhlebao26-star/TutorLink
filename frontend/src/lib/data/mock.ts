import type { CustomerProfile, ListingView, ProfileResponse, TutorOnboardingData, TutorProfile, User } from "./types";

const users: Record<string, User> = {
  customer: { id: 302, role: "customer", status: "active", full_name: "Trần Gia Bảo", email: "bao@example.test", date_of_birth: "1998-01-12", email_verified_at: "2026-10-06T08:00:00Z" },
  tutor: { id: 301, role: "tutor", status: "active", full_name: "Nguyễn Minh Anh", email: "anh@example.test", date_of_birth: "1995-04-02", email_verified_at: "2026-10-06T08:00:00Z" },
  pendingTutor: { id: 303, role: "tutor", status: "active", full_name: "Lê Thu Hà", email: "ha@example.test", date_of_birth: "1997-06-18", email_verified_at: "2026-10-06T08:00:00Z" },
};

const customerProfile: CustomerProfile = { id: 401, user_id: users.customer.id, avatar_file_id: null, city: "Đà Nẵng", district: "Hải Châu", bio: "Đang tìm gia sư tiếng Anh giao tiếp." };
const tutorProfile: TutorProfile = { id: 201, user_id: users.tutor.id, specialization_id: 12, avatar_file_id: 701, verification_document_file_id: 702, headline: "Gia sư tiếng Anh giao tiếp", bio: "Tám năm hướng dẫn người đi làm luyện nói và phỏng vấn.", experience_years: 8, status: "submitted", approval_status: "active", submitted_at: "2026-10-06T08:00:00Z", review_reason: null, reviewed_at: "2026-10-06T09:30:00Z" };
const pendingProfile: TutorProfile = { id: 202, user_id: users.pendingTutor.id, specialization_id: 21, avatar_file_id: null, verification_document_file_id: null, headline: "Toán lớp 9 online", bio: "Hỗ trợ học sinh củng cố nền tảng và chuẩn bị thi vào 10.", experience_years: 4, status: "submitted", approval_status: "pending", submitted_at: "2026-10-07T03:00:00Z", review_reason: null, reviewed_at: null };

export const mockListings: ListingView[] = [
  { id: 501, tutor_profile_id: 201, specialization_id: 12, slug: "tieng-anh-giao-tiep-cho-nguoi-di-lam", title: "Tiếng Anh giao tiếp cho người đi làm", description: "Lộ trình thực hành theo tình huống họp, thuyết trình và phỏng vấn.", delivery_mode: "hybrid", city: "Đà Nẵng", district: "Hải Châu", price_from_minor: 480000, currency: "VND", price_unit: "hour", status: "active", tutor_name: users.tutor.full_name, specialization_name: "Tiếng Anh giao tiếp" },
  { id: 502, tutor_profile_id: 201, specialization_id: 13, slug: "luyen-phat-am-online", title: "Luyện phát âm online", description: "Sửa âm và xây thói quen luyện nói ngắn mỗi ngày.", delivery_mode: "online", city: null, district: null, price_from_minor: 350000, currency: "VND", price_unit: "hour", status: "active", tutor_name: users.tutor.full_name, specialization_name: "Phát âm" },
];

export function mockCurrentUser(role: "customer" | "tutor" = "tutor"): User { return users[role]; }
export function mockProfile(role: "customer" | "tutor"): ProfileResponse { return role === "customer" ? { user: users.customer, profile: customerProfile } : { user: users.tutor, profile: tutorProfile }; }
export function mockTutorOnboarding(): TutorOnboardingData { return { user: users.tutor, profile: tutorProfile, specializations: [{ id: 12, name: "Tiếng Anh giao tiếp", slug: "tieng-anh-giao-tiep" }] }; }
export function mockAdminProfiles() { return [{ user: users.pendingTutor, profile: pendingProfile, verification_documents: null }]; }
