import { request } from "@/lib/api/client";
import type { ApiEnvelope } from "@/lib/data/types";
import type { Collection } from "@/lib/data/workspace";

export interface ReportQueueItem {
  id: number;
  status: "open" | "resolved" | "dismissed";
  assigned_admin_id: number | null;
  assignment_version: number;
}
export interface AdminReport extends ReportQueueItem {
  resource_type: string;
  resource_id: number;
  reason_code: string;
  description: string | null;
  resolution_note: string | null;
  handled_at: string | null;
  created_at: string | null;
}
export interface ReportEvidence {
  code?: string;
  resource_type?: string;
  resource?: { id?: number; name?: string; title?: string; headline?: string; description?: string; bio?: string; comment?: string; status?: string; rating?: number };
  messages?: Array<{ id: number; body: string | null; created_at: string }>;
  attachments?: Array<{ id: number; name: string | null; mime_type: string; size_bytes: number }>;
  moderation_history?: Array<{ id: number; action: string; reason: string | null; created_at: string }>;
}

export async function getAdminReports(scope: "mine" | "unassigned", page: number) {
  return (await request<Collection<AdminReport | ReportQueueItem>>({ url: "/admin/reports", params: { scope, page, per_page: 10 } })).data;
}
export async function getAdminReport(id: number) {
  return (await request<ApiEnvelope<AdminReport>>({ url: `/admin/reports/${id}` })).data.data;
}
export async function getReportEvidence(id: number) {
  return (await request<ApiEnvelope<ReportEvidence>>({ url: `/admin/reports/${id}/evidence` })).data.data;
}
export async function claimReport(item: ReportQueueItem, adminId: number) {
  await request({ method: "PUT", url: `/admin/reports/${item.id}/assignment`, data: {
    assigned_admin_id: adminId, assignment_version: item.assignment_version, reason: "Nhận báo cáo để xem xét và xử lý.",
  } }, true);
}
export async function decideReport(report: AdminReport, action: "resolve" | "dismiss", note: string) {
  return (await request<ApiEnvelope<AdminReport>>({ method: "POST", url: `/admin/reports/${report.id}/${action}`, data: {
    assignment_version: report.assignment_version, resolution_note: note,
  } }, true)).data.data;
}

export const reportResourceLabels: Record<string, string> = {
  program: "Chương trình học", message: "Tin nhắn", review: "Đánh giá", listing: "Dịch vụ", tutor_profile: "Hồ sơ gia sư", system: "Hệ thống TutorLink",
};
export const reportReasonLabels: Record<string, string> = {
  spam: "Spam hoặc quảng cáo", harassment: "Quấy rối", inappropriate_content: "Nội dung không phù hợp", fraud_suspected: "Nghi ngờ gian lận",
  misleading_information: "Thông tin sai lệch", privacy_violation: "Vi phạm quyền riêng tư", bug_report: "Báo lỗi kỹ thuật",
  feature_request: "Góp ý chức năng", performance_issue: "Vấn đề hiệu năng", usability_issue: "Khó sử dụng", other: "Lý do khác",
};
export const reportStatusLabels = { open: "Chờ xử lý", resolved: "Đã giải quyết", dismissed: "Đã đóng" };
