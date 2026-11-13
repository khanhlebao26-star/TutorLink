import { render, screen, within, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
import type { ReactNode } from "react";
import AdminPage from "@/app/admin/page";
import type { AdminTutorProfile } from "@/lib/data/types";
import * as services from "@/lib/data/services";

vi.mock("@/components/app-shell", () => ({ AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main> }));
vi.mock("@/lib/data/services", () => ({
  getAdminTutorReviewPage: vi.fn(), getAdminTutorProfile: vi.fn(), getCurrentUser: vi.fn(),
  getSpecializations: vi.fn(), assignAdminTutorProfile: vi.fn(), performAdminTutorAction: vi.fn(), performAdminAccountAction: vi.fn(),
}));

const tutor: AdminTutorProfile = {
  user: { id: 7, full_name: "Nguyễn Minh Anh", email: "anh@example.test", role: "tutor", status: "active", date_of_birth: "2000-02-12", email_verified_at: "2026-11-01" },
  profile: { id: 43, user_id: 7, specialization_id: 1, avatar_file_id: null, verification_document_file_id: null, headline: "Tiêu đề cũ", bio: "Giới thiệu cũ", experience_years: 3, status: "approved" },
  assignment: { assigned_admin_id: 1, assignment_version: 4 },
  revision: { id: 10, revision_no: 2, version: 5, status: "submitted", payload: { headline: "Tiếng Anh giao tiếp", bio: "Giới thiệu gửi xét duyệt", qualification_claims: [{ type: "certificate", name: "IELTS 8.0" }] }, review_reason: null },
  allowed_actions: ["approve", "request-changes", "reject"], verification_documents: [],
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(services.getSpecializations).mockResolvedValue([{ id: 1, name: "Tiếng Anh", subcategory_id: 1, slug: "english", status: "active" }]);
  vi.mocked(services.getCurrentUser).mockResolvedValue({ id: 1, role: "admin", status: "active", full_name: "Admin", email: "admin@example.test" });
  vi.mocked(services.getAdminTutorProfile).mockResolvedValue(tutor);
  vi.mocked(services.assignAdminTutorProfile).mockResolvedValue(undefined);
  vi.mocked(services.getAdminTutorReviewPage).mockImplementation(async (scope, _status, page) => ({
    items: scope === "mine" ? [tutor] : [{ id: 43, status: "submitted", submitted_at: "2026-11-13", assigned_admin_id: null, assigned_by: null, assigned_at: null, assignment_version: 3 }],
    total: 1, page, perPage: 10,
  }));
});

it("keeps the unassigned queue private, then claims with its current version and opens the profile", async () => {
  render(<AdminPage />);
  expect(await screen.findByText("Hồ sơ #43")).toBeVisible();
  expect(screen.queryByText(tutor.user.email)).not.toBeInTheDocument();
  await userEvent.click(screen.getByRole("button", { name: "Nhận hồ sơ" }));
  expect(services.assignAdminTutorProfile).toHaveBeenCalledWith(43, 1, 3);
  expect(await screen.findByRole("heading", { name: tutor.user.full_name })).toBeVisible();
  expect(screen.getByRole("button", { name: "Hồ sơ của tôi" })).toHaveAttribute("aria-pressed", "true");
});

it("reviews the submitted revision and preserves the required decision reason and version context", async () => {
  vi.mocked(services.performAdminTutorAction).mockResolvedValue({ ...tutor, revision: { ...tutor.revision!, status: "approved" }, allowed_actions: [] });
  render(<AdminPage />);
  await userEvent.click(screen.getByRole("button", { name: "Hồ sơ của tôi" }));
  await userEvent.click(await screen.findByRole("button", { name: /Nguyễn Minh Anh/ }));
  const detail = await screen.findByRole("region", { name: "Chi tiết hồ sơ" });
  expect(await within(detail).findByText("Giới thiệu gửi xét duyệt")).toBeVisible();
  expect(within(detail).getByText("IELTS 8.0")).toBeVisible();
  expect(within(detail).getByText("12/02/2000")).toBeVisible();
  expect(within(detail).queryByText("Giới thiệu cũ")).not.toBeInTheDocument();
  await userEvent.click(within(detail).getByRole("button", { name: "Phê duyệt hồ sơ" }));
  expect(services.performAdminTutorAction).not.toHaveBeenCalled();
  expect(within(detail).getByRole("alert")).toHaveTextContent("2.000 ký tự");
  await userEvent.type(within(detail).getByLabelText("Ghi chú xét duyệt"), "Thông tin đã được kiểm tra.");
  await userEvent.click(within(detail).getByRole("button", { name: "Phê duyệt hồ sơ" }));
  await waitFor(() => expect(services.performAdminTutorAction).toHaveBeenCalledWith(43, "approve", "Thông tin đã được kiểm tra.", tutor));
  expect(await screen.findByText("Đã lưu quyết định xét duyệt.")).toBeVisible();
});

it("loads the next API page and clears the selected detail on a status filter change", async () => {
  vi.mocked(services.getAdminTutorReviewPage).mockResolvedValue({ items: [tutor], total: 12, page: 1, perPage: 10 });
  render(<AdminPage />);
  await userEvent.click(await screen.findByRole("button", { name: /Nguyễn Minh Anh/ }));
  expect(await screen.findByRole("heading", { name: tutor.user.full_name })).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Sau" }));
  await waitFor(() => expect(services.getAdminTutorReviewPage).toHaveBeenLastCalledWith("unassigned", "submitted", 2));
  await userEvent.selectOptions(screen.getByLabelText("Lọc trạng thái"), "approved");
  await waitFor(() => expect(services.getAdminTutorReviewPage).toHaveBeenLastCalledWith("unassigned", "approved", 1));
  expect(screen.queryByRole("heading", { name: tutor.user.full_name })).not.toBeInTheDocument();
});
