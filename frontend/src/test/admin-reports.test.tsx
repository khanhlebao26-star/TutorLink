import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
import type { ReactNode } from "react";
import AdminReportsPage from "@/app/admin/reports/page";
import * as reports from "@/lib/data/admin-reports";
import type { AdminReport } from "@/lib/data/admin-reports";
import { ApiClientError } from "@/lib/api/client";
vi.mock("@/components/app-shell", () => ({ AppShell: ({ children }: { children: ReactNode }) => <main>{children}</main>, useActor: () => ({ id: 1, role: "admin" }) }));
vi.mock("@/lib/data/admin-reports", async original => ({ ...await original<typeof import("@/lib/data/admin-reports")>(), getAdminReports: vi.fn(), getAdminReport: vi.fn(), getReportEvidence: vi.fn(), claimReport: vi.fn(), decideReport: vi.fn() }));
const detail: AdminReport = { id: 23, resource_type: "system", resource_id: 1, reason_code: "bug_report", description: "Không mở được lịch học.", status: "open", assigned_admin_id: 1, assignment_version: 7, created_at: "2026-11-13", resolution_note: null, handled_at: null };
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(reports.getAdminReports).mockImplementation(async scope => ({ data: scope === "mine" ? [detail] : [{ id: 23, status: "open", assigned_admin_id: null, assignment_version: 6 }], meta: { total: 1 } }));
  vi.mocked(reports.getAdminReport).mockResolvedValue(detail);
  vi.mocked(reports.claimReport).mockResolvedValue(undefined);
  vi.mocked(reports.getReportEvidence).mockResolvedValue({ resource: { name: "Hệ thống TutorLink" }, attachments: [{ id: 9, name: "anh-loi.png", mime_type: "image/png", size_bytes: 800 }] });
});
async function openMine() {
  await userEvent.click(screen.getByRole("button", { name: "Báo cáo của tôi" }));
  await userEvent.click(await screen.findByRole("button", { name: /Báo cáo #23/ }));
  await screen.findByText(detail.description!);
}
it("requires claiming the metadata-only report before showing its description", async () => {
  render(<AdminReportsPage />);
  await screen.findByText("Báo cáo #23");
  expect(screen.queryByText(detail.description!)).not.toBeInTheDocument();
  await userEvent.click(screen.getByRole("button", { name: "Nhận báo cáo" }));
  expect(reports.claimReport).toHaveBeenCalledWith(expect.objectContaining({ id: 23, assignment_version: 6 }), 1);
  expect(await screen.findByText(detail.description!)).toBeVisible();
});
it("requires a resolution note, sends the current assignment context and clears evidence after closure", async () => {
  vi.mocked(reports.decideReport).mockResolvedValue({ ...detail, status: "resolved", resolution_note: "Đã sửa lỗi lịch học.", handled_at: "2026-11-13" });
  render(<AdminReportsPage />);
  await openMine();
  await userEvent.click(screen.getByRole("button", { name: "Xem bằng chứng" }));
  expect(await screen.findByText("anh-loi.png")).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Đánh dấu đã giải quyết" }));
  expect(reports.decideReport).not.toHaveBeenCalled();
  await userEvent.type(screen.getByLabelText("Kết quả xử lý"), "Đã sửa lỗi lịch học.");
  await userEvent.click(screen.getByRole("button", { name: "Đánh dấu đã giải quyết" }));
  await waitFor(() => expect(reports.decideReport).toHaveBeenCalledWith(detail, "resolve", "Đã sửa lỗi lịch học."));
  expect(await screen.findByText("Đã ghi nhận báo cáo được giải quyết.")).toBeVisible();
  const panel = screen.getByRole("region", { name: "Chi tiết báo cáo" });
  expect(within(panel).queryByText("anh-loi.png")).not.toBeInTheDocument();
  expect(within(panel).queryByRole("button", { name: "Xem bằng chứng" })).not.toBeInTheDocument();
});
it("refreshes a stale report after a conflict without replaying the decision", async () => {
  vi.mocked(reports.decideReport).mockRejectedValue(new ApiClientError("Stale assignment", { status: 409 }));
  render(<AdminReportsPage />);
  await openMine();
  vi.mocked(reports.getAdminReport).mockResolvedValue({ ...detail, status: "dismissed", assignment_version: 8 });
  await userEvent.type(screen.getByLabelText("Kết quả xử lý"), "Đóng báo cáo trùng lặp.");
  await userEvent.click(screen.getByRole("button", { name: "Đóng báo cáo" }));
  expect(await screen.findByRole("alert")).toHaveTextContent("Báo cáo đã thay đổi");
  expect(reports.decideReport).toHaveBeenCalledTimes(1);
  await waitFor(() => expect(reports.getAdminReport).toHaveBeenCalledTimes(2));
});
