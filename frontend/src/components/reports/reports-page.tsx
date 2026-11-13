"use client";

import { AppShell, useActor } from "@/components/layout/app-shell";
import { ApiState } from "@/components/shared/api-state";
import { Button, Card } from "@/components/shared/ui";
import { workspaceRead, type Collection } from "@/lib/data/workspace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/styles/reports.css";
import { useCallback, useMemo, useState } from "react";

import { ReportSubmissionForm } from "@/components/reports/report-submission-form";

interface Report {
  id: number;
  resource_type: string;
  resource_id: number;
  reason_code: string;
  description: string | null;
  status: string;
  resolution_note: string | null;
  created_at?: string | null;
  handled_at?: string | null;
}

const resourceLabels: Record<string, string> = {
  program: "Chương trình học",
  message: "Tin nhắn",
  review: "Đánh giá",
  listing: "Dịch vụ",
  tutor_profile: "Hồ sơ gia sư",
  system: "Hệ thống TutorLink",
};

const reasonLabels: Record<string, string> = {
  spam: "Spam hoặc quảng cáo",
  harassment: "Quấy rối",
  inappropriate_content: "Nội dung không phù hợp",
  fraud_suspected: "Nghi ngờ gian lận",
  misleading_information: "Thông tin sai lệch",
  privacy_violation: "Vi phạm quyền riêng tư",
  bug_report: "Báo lỗi kỹ thuật",
  feature_request: "Góp ý chức năng",
  performance_issue: "Vấn đề hiệu năng",
  usability_issue: "Khó sử dụng",
  other: "Lý do khác",
};

const statusLabels: Record<string, string> = {
  open: "Đang chờ xử lý",
  reviewing: "Đang xem xét",
  pending: "Đang chờ xử lý",
  resolved: "Đã giải quyết",
  dismissed: "Đã đóng",
};

function formatDate(value?: string | null) {
  if (!value) return "Ngày gửi chưa có";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? "Ngày gửi chưa có"
    : new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric" }).format(date);
}

function statusTone(status: string) {
  if (status === "resolved") return "resolved";
  if (status === "dismissed") return "closed";
  return "pending";
}

function ReportRowsLoading() {
  return <div className="reports-inline-loading" role="status" aria-label="Đang tải danh sách báo cáo">
    <div className="reports-inline-loading-status"><span className="reports-loading-spinner" aria-hidden="true" /><span><strong>Đang tải báo cáo</strong><small>Đang đồng bộ trạng thái mới nhất…</small></span></div>
    {[0, 1, 2].map((item) => <div className="reports-loading-row" key={item} aria-hidden="true">
      <i className="reports-skeleton reports-skeleton-row-icon" />
      <span><i className="reports-skeleton reports-skeleton-row-title" /><i className="reports-skeleton reports-skeleton-row-meta" /><i className="reports-skeleton reports-skeleton-row-description" /></span>
      <i className="reports-skeleton reports-skeleton-row-action" />
    </div>)}
  </div>;
}

export function ReportsPage() {
  return <AppShell title=""><ReportsContent /></AppShell>;
}

function ReportsContent() {
  const actor = useActor();
  const audienceLabel = actor?.role === "tutor" ? "Gia sư" : "Học viên";
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState("all");
  const [formOpen, setFormOpen] = useState(false);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const load = useCallback(
    () => workspaceRead<Collection<Report>>("/me/reports", { page, per_page: 50 }),
    [page],
  );
  const result = useAsyncData(load);
  const reports = result.data?.data ?? [];
  const total = result.data?.meta?.total ?? reports.length;
  const openCount = reports.filter((report) => !["resolved", "dismissed"].includes(report.status)).length;
  const closedCount = reports.filter((report) => ["resolved", "dismissed"].includes(report.status)).length;
  const filteredReports = useMemo(() => {
    const normalizedQuery = query.trim().toLocaleLowerCase("vi");
    return reports.filter((report) => {
      const matchesStatus = statusFilter === "all" || (statusFilter === "open" ? !["resolved", "dismissed"].includes(report.status) : report.status === statusFilter);
      const haystack = [
        report.id,
        report.resource_id,
        resourceLabels[report.resource_type] ?? report.resource_type,
        reasonLabels[report.reason_code] ?? report.reason_code,
        report.description,
      ].join(" ").toLocaleLowerCase("vi");
      return matchesStatus && (!normalizedQuery || haystack.includes(normalizedQuery));
    });
  }, [query, reports, statusFilter]);
  const lastPage = result.data?.meta?.last_page ?? Math.max(1, Math.ceil(total / 50));

  return (
    <section className="reports-page">
      <div className="reports-heading">
        <div>
          <p className="reports-eyebrow"><span /> {audienceLabel} · Báo cáo của tôi</p>
          <h1>Báo cáo của tôi</h1>
          <p className="reports-subtitle">Theo dõi các vấn đề bạn đã gửi và cập nhật kết quả xử lý.</p>
        </div>
        <Button className="reports-create-button" onClick={() => setFormOpen(true)}>
          <span aria-hidden="true">＋</span> Tạo báo cáo
        </Button>
      </div>

      <div className="reports-summary" aria-label="Tổng quan báo cáo">
        <Card className="reports-summary-card reports-summary-total">
          <span className="reports-summary-icon" aria-hidden="true">▤</span>
          <span className="reports-summary-copy"><small>Tổng báo cáo</small><strong>{result.loading ? "—" : total}</strong></span>
          <span className="reports-summary-note">Tất cả báo cáo đã gửi</span>
        </Card>
        <Card className="reports-summary-card reports-summary-open">
          <span className="reports-summary-icon" aria-hidden="true">◷</span>
          <span className="reports-summary-copy"><small>Đang xử lý</small><strong>{result.loading ? "—" : openCount}</strong></span>
          <span className="reports-summary-note">Trong danh sách gần đây</span>
        </Card>
        <Card className="reports-summary-card reports-summary-closed">
          <span className="reports-summary-icon" aria-hidden="true">✓</span>
          <span className="reports-summary-copy"><small>Đã có kết quả</small><strong>{result.loading ? "—" : closedCount}</strong></span>
          <span className="reports-summary-note">Trong danh sách gần đây</span>
        </Card>
      </div>

      <Card className="reports-panel">
        <div className="reports-list-heading">
          <div>
            <h2>Báo cáo gần đây</h2>
            <p>{total ? `Bạn có ${total} báo cáo trong hệ thống.` : "Các báo cáo bạn gửi sẽ xuất hiện tại đây."}</p>
          </div>
          <div className="reports-filters">
            <label className="reports-search">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.2 4.2" /></svg>
              <span className="reports-sr-only">Tìm báo cáo</span>
              <input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Tìm theo mã báo cáo, đối tượng hoặc lý do…" />
            </label>
            <label className="reports-status-filter">
              <span className="reports-sr-only">Lọc trạng thái</span>
              <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
                <option value="all">Tất cả trạng thái</option>
                <option value="open">Đang xử lý</option>
                <option value="resolved">Đã giải quyết</option>
                <option value="dismissed">Đã đóng</option>
              </select>
            </label>
          </div>
        </div>

        {result.loading ? <ReportRowsLoading /> : <ApiState {...result} />}

        {result.data && filteredReports.length === 0 && (
          <div className="reports-empty">
            <span className="reports-empty-icon" aria-hidden="true">◎</span>
            <h3>{reports.length === 0 ? "Bạn chưa gửi báo cáo nào" : "Không tìm thấy báo cáo phù hợp"}</h3>
            <p>{reports.length === 0 ? "Nếu gặp nội dung hoặc hành vi cần được xem xét, bạn có thể gửi báo cáo để đội ngũ TutorLink hỗ trợ." : "Thử đổi từ khóa hoặc trạng thái lọc."}</p>
          </div>
        )}

        {filteredReports.length > 0 ? <div className="reports-list">
          {filteredReports.map((report) => {
            const expanded = expandedId === report.id;
            return <article className={`report-row${expanded ? " is-expanded" : ""}`} key={report.id}>
              <div className="report-row-icon" aria-hidden="true">{report.resource_type === "message" ? "✉" : report.resource_type === "review" ? "☆" : "▤"}</div>
              <div className="report-row-main">
                <div className="report-row-title-line">
                  <h3>Báo cáo #{report.id}</h3>
                  <span className={`report-status is-${statusTone(report.status)}`}><i />{statusLabels[report.status] ?? "Đang cập nhật"}</span>
                </div>
                <p className="report-row-target">{resourceLabels[report.resource_type] ?? report.resource_type}</p>
                <div className="report-row-meta"><span>{reasonLabels[report.reason_code] ?? report.reason_code}</span><span>{formatDate(report.created_at)}</span></div>
                {expanded ? <div className="report-detail">
                  <div><strong>Mô tả vấn đề</strong><p>{report.description || "Không có mô tả bổ sung."}</p></div>
                  <div><strong>Kết quả xử lý</strong><p>{report.resolution_note || "Đội ngũ TutorLink sẽ cập nhật tại đây khi có kết quả."}</p></div>
                  {report.handled_at ? <small>Cập nhật lần cuối: {formatDate(report.handled_at)}</small> : null}
                </div> : null}
              </div>
              <Button className="report-row-action" variant="ghost" aria-expanded={expanded} onClick={() => setExpandedId(expanded ? null : report.id)}>
                {expanded ? "Thu gọn" : "Chi tiết"}<span aria-hidden="true">{expanded ? "⌃" : "⌄"}</span>
              </Button>
            </article>;
          })}
        </div> : null}

        {result.data && filteredReports.length > 0 && lastPage > 1 ? <div className="reports-pagination">
          <span>Trang {page} / {lastPage}</span>
          <div>
            <Button variant="outline" disabled={page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}>← Trước</Button>
            <Button variant="outline" disabled={page >= lastPage} onClick={() => setPage((current) => Math.min(lastPage, current + 1))}>Tiếp →</Button>
          </div>
        </div> : null}
      </Card>

      <aside className="reports-help-note">
        <span aria-hidden="true">ⓘ</span>
        <p><strong>Báo cáo của bạn được bảo mật.</strong> Thông tin chỉ được chia sẻ với đội ngũ phụ trách để xem xét và hỗ trợ xử lý.</p>
      </aside>

      {formOpen ? <div className="reports-dialog-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget) setFormOpen(false); }}>
        <section className="reports-dialog" role="dialog" aria-modal="true" aria-labelledby="reports-dialog-title">
          <div className="reports-dialog-heading">
            <div><p className="reports-eyebrow"><span /> Hỗ trợ cộng đồng</p><h2 id="reports-dialog-title">Gửi báo cáo</h2><p>Mô tả vấn đề để đội ngũ TutorLink có thể xem xét.</p></div>
            <button className="reports-dialog-close" type="button" aria-label="Đóng" onClick={() => setFormOpen(false)}>×</button>
          </div>
          <ReportSubmissionForm onSaved={() => { setFormOpen(false); setPage(1); result.reload(); }} />
        </section>
      </div> : null}
    </section>
  );
}
