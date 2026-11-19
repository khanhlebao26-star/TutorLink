"use client";

import "@/app/admin/admin.css";
import "@/app/admin/reports/reports.css";
import { AppShell, useActor } from "@/components/layout/app-shell";
import { Badge, Button, ErrorActions, Field, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError, request } from "@/lib/api/client";
import { claimReport, decideReport, getAdminReport, getAdminReports, getReportEvidence, reportReasonLabels, reportResourceLabels, reportStatusLabels, type AdminReport, type ReportEvidence, type ReportQueueItem } from "@/lib/data/admin-reports";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useRef, useState } from "react";

function formatDate(value?: string | null) {
  return value && !Number.isNaN(new Date(value).getTime()) ? new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" }).format(new Date(value)) : "Chưa có thông tin";
}
function ReportStatus({ status }: { status: ReportQueueItem["status"] }) {
  return <Badge tone={status === "resolved" ? "success" : status === "open" ? "warning" : "neutral"}>{reportStatusLabels[status]}</Badge>;
}

export default function AdminReportsPage() {
  return <AppShell role="admin" title=""><AdminReportsContent /></AppShell>;
}

function AdminReportsContent() {
  const actor = useActor();
  const [scope, setScope] = useState<"unassigned" | "mine">("unassigned");
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState("all");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<AdminReport | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState("");
  const [evidence, setEvidence] = useState<ReportEvidence | null>(null);
  const [evidenceLoading, setEvidenceLoading] = useState(false);
  const [evidenceError, setEvidenceError] = useState("");
  const [note, setNote] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [pending, setPending] = useState(false);
  const detailVersion = useRef(0);
  const mutationLock = useRef(false);
  const load = useCallback(() => getAdminReports(scope, page), [scope, page]);
  const result = useAsyncData(load);

  function clearDetail() {
    detailVersion.current++;
    setSelectedId(null); setDetail(null); setDetailLoading(false); setDetailError("");
    setEvidence(null); setEvidenceLoading(false); setEvidenceError(""); setNote(""); setError(""); setMessage("");
  }
  async function openReport(id: number) {
    clearDetail();
    const version = detailVersion.current;
    setSelectedId(id); setDetailLoading(true);
    try {
      const next = await getAdminReport(id);
      if (version === detailVersion.current) setDetail(next);
    } catch (reason) {
      if (version === detailVersion.current) setDetailError(describeApiError(reason));
    } finally { if (version === detailVersion.current) setDetailLoading(false); }
  }
  async function claim(item: ReportQueueItem) {
    if (!actor || mutationLock.current) return;
    mutationLock.current = true; setPending(true); setError("");
    try {
      await claimReport(item, actor.id);
      setScope("mine"); setPage(1); setStatus("all");
      await openReport(item.id);
      result.reload();
    } catch (reason) { setError(describeApiError(reason)); result.reload(); }
    finally { mutationLock.current = false; setPending(false); }
  }
  async function loadEvidence() {
    if (!detail || evidenceLoading) return;
    const version = detailVersion.current;
    setEvidenceError(""); setEvidenceLoading(true);
    try {
      const next = await getReportEvidence(detail.id);
      if (version === detailVersion.current) setEvidence(next);
    } catch (reason) {
      if (version === detailVersion.current) setEvidenceError(describeApiError(reason));
    } finally { if (version === detailVersion.current) setEvidenceLoading(false); }
  }
  async function decide(action: "resolve" | "dismiss") {
    if (!detail || mutationLock.current) return;
    if (!note.trim()) { setError("Vui lòng nhập kết quả xử lý trước khi đóng báo cáo."); return; }
    mutationLock.current = true; setPending(true); setError(""); setMessage("");
    try {
      setDetail(await decideReport(detail, action, note.trim()));
      setEvidence(null); setEvidenceError(""); setNote("");
      setMessage(action === "resolve" ? "Đã ghi nhận báo cáo được giải quyết." : "Đã đóng báo cáo kèm kết quả xử lý.");
      result.reload();
    } catch (reason) {
      setError(reason instanceof ApiClientError && reason.status === 409 ? "Báo cáo đã thay đổi. Thông tin đã được tải lại; hãy kiểm tra trước khi xử lý tiếp." : describeApiError(reason));
      setEvidence(null);
      try { setDetail(await getAdminReport(detail.id)); } catch { setDetail(null); }
    } finally { mutationLock.current = false; setPending(false); }
  }
  const rows = (result.data?.data ?? []).filter(item => status === "all" || item.status === status);
  const total = result.data?.meta?.total ?? 0;

  return <section className="admin-reports-page">
    <header className="admin-reports-heading"><h1>Quản lý báo cáo</h1><p>Xem xét phản ánh từ người dùng và cập nhật kết quả xử lý.</p></header>
    <div className="tutor-review">
      <aside className="tutor-review-queue" aria-label="Danh sách báo cáo">
        <div className="tutor-review-tabs" role="group" aria-label="Phạm vi báo cáo">{(["unassigned", "mine"] as const).map(value => <button key={value} type="button" aria-pressed={scope === value} disabled={pending} onClick={() => { if (value === scope) return; clearDetail(); setScope(value); setPage(1); setStatus("all"); }}>{value === "unassigned" ? "Chưa phân công" : "Báo cáo của tôi"}</button>)}</div>
        {scope === "mine" ? <label className="tutor-review-status"><span className="landing-sr-only">Lọc báo cáo trong trang</span><select value={status} onChange={event => setStatus(event.target.value)}><option value="all">Tất cả trạng thái</option><option value="open">Chờ xử lý</option><option value="resolved">Đã giải quyết</option><option value="dismissed">Đã đóng</option></select></label> : <p className="tutor-review-privacy">Nhận báo cáo để xem nội dung và bằng chứng từ người dùng.</p>}
        {error && !detail ? <p className="inline-error" role="alert">{error}</p> : null}
        <div className="tutor-review-list" aria-busy={result.loading}>
          {result.loading ? <ReportsSkeleton /> : result.error ? <StatePanel kind="error" message={describeApiError(result.error)} action={<ErrorActions login={result.error.status === 401} onRetry={result.reload} />} /> : !rows.length ? <div className="tutor-review-list-empty"><strong>Chưa có báo cáo</strong><p>Không có báo cáo phù hợp trong danh sách này.</p></div> : rows.map(item => "resource_type" in item ? <button key={item.id} className="tutor-review-person admin-report-item" type="button" disabled={pending} aria-pressed={selectedId === item.id} onClick={() => openReport(item.id)}><span className="tutor-review-person-copy"><strong>Báo cáo #{item.id}</strong><span>{reportResourceLabels[item.resource_type] ?? item.resource_type} · {reportReasonLabels[item.reason_code] ?? item.reason_code}</span></span><ReportStatus status={item.status} /></button> : <div key={item.id} className="tutor-review-unassigned"><div><strong>Báo cáo #{item.id}</strong><p><ReportStatus status={item.status} /></p></div><Button variant="outline" disabled={pending} onClick={() => claim(item)}>{pending ? "Đang xử lý…" : "Nhận báo cáo"}</Button></div>)}
        </div>
        {result.data ? <div className="tutor-review-pagination"><span>{total} báo cáo</span><div><Button variant="secondary" disabled={pending || result.loading || page <= 1} onClick={() => setPage(value => value - 1)}>Trước</Button><span>{page}</span><Button variant="secondary" disabled={pending || result.loading || page * 10 >= total} onClick={() => setPage(value => value + 1)}>Sau</Button></div></div> : null}
      </aside>
      <section className="tutor-review-detail" aria-label="Chi tiết báo cáo" aria-busy={detailLoading}>
        {detailLoading ? <ReportsSkeleton /> : detailError ? <StatePanel kind="error" message={detailError} action={<ErrorActions onRetry={() => selectedId && openReport(selectedId)} />} /> : detail ? <>
          <div className="admin-report-detail-header"><div><span className="admin-report-eyebrow">Báo cáo #{detail.id}</span><h2>{reportReasonLabels[detail.reason_code] ?? detail.reason_code}</h2><p>Gửi ngày {formatDate(detail.created_at)}</p></div><ReportStatus status={detail.status} /></div>
          <section className="tutor-review-section"><h3>Đối tượng được báo cáo</h3><p>{reportResourceLabels[detail.resource_type] ?? detail.resource_type}{detail.resource_type !== "system" ? ` #${detail.resource_id}` : ""}</p></section>
          <section className="tutor-review-section"><h3>Nội dung phản ánh</h3><p className="tutor-review-bio">{detail.description || "Người gửi chưa bổ sung mô tả."}</p></section>
          {detail.status === "open" ? <section className="tutor-review-section"><div className="admin-report-evidence-heading"><h3>Bằng chứng & thông tin liên quan</h3><Button variant="secondary" disabled={pending || evidenceLoading} onClick={loadEvidence}>{evidenceLoading ? "Đang tải…" : evidence ? "Tải lại bằng chứng" : "Xem bằng chứng"}</Button></div>
            {evidenceError ? <p className="inline-error" role="alert">{evidenceError}</p> : null}
            {evidence ? <EvidenceContent key={detail.id} evidence={evidence} /> : <p className="tutor-review-muted">Xem thông tin liên quan và các tệp do người dùng cung cấp.</p>}
          </section> : null}
          {detail.resolution_note ? <section className="tutor-review-section"><h3>Kết quả xử lý</h3><p className="tutor-review-bio">{detail.resolution_note}</p><p className="tutor-review-muted">Cập nhật: {formatDate(detail.handled_at)}</p></section> : null}
          {detail.status === "open" ? <form className="tutor-review-decision" onSubmit={event => event.preventDefault()}><Field htmlFor="report-resolution-note" label="Kết quả xử lý" hint="Nội dung này sẽ hiển thị cho người gửi báo cáo." error={error || undefined}><textarea id="report-resolution-note" rows={4} maxLength={2000} disabled={pending} value={note} onChange={event => { setNote(event.target.value); setError(""); }} placeholder="Ghi kết quả xem xét hoặc lý do đóng báo cáo…" /></Field><div className="tutor-review-action-row"><Button variant="secondary" disabled={pending || evidenceLoading} onClick={() => decide("dismiss")}>Đóng báo cáo</Button><Button disabled={pending || evidenceLoading} onClick={() => decide("resolve")}>{pending ? "Đang lưu…" : "Đánh dấu đã giải quyết"}</Button></div></form> : error ? <p className="inline-error" role="alert">{error}</p> : null}
          {message ? <p className="inline-success" role="status">{message}</p> : null}
        </> : <div className="tutor-review-placeholder"><h2>Chọn báo cáo để xem chi tiết</h2><p>{scope === "unassigned" ? "Nhận báo cáo từ danh sách bên trái để bắt đầu xử lý." : "Nội dung phản ánh và kết quả xử lý sẽ hiển thị tại đây."}</p></div>}
      </section>
    </div>
  </section>;
}

function EvidenceContent({ evidence }: { evidence: ReportEvidence }) {
  const [downloading, setDownloading] = useState<number | null>(null);
  const [error, setError] = useState("");
  async function download(file: NonNullable<ReportEvidence["attachments"]>[number]) {
    if (downloading !== null) return;
    setDownloading(file.id); setError("");
    try {
      const response = await request<Blob>({ url: `/files/${file.id}/download`, responseType: "blob" });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement("a");
      link.href = url; link.download = file.name || `bang-chung-${file.id}`;
      document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
    } catch (reason) { setError(describeApiError(reason)); }
    finally { setDownloading(null); }
  }
  if (evidence.code === "evidence_unavailable") return <p className="tutor-review-muted">Bằng chứng không còn khả dụng.</p>;
  const resource = evidence.resource;
  return <div className="admin-report-evidence">
    {resource ? <div className="admin-report-resource"><strong>{resource.title || resource.headline || resource.name || "Thông tin đối tượng"}</strong>{resource.description || resource.bio || resource.comment ? <p>{resource.description || resource.bio || resource.comment}</p> : null}{resource.rating !== undefined ? <p>Đánh giá: {resource.rating}/5</p> : null}</div> : null}
    {evidence.messages?.map(item => <blockquote key={item.id}><p>{item.body || "Tin nhắn không có nội dung văn bản."}</p><small>{formatDate(item.created_at)}</small></blockquote>)}
    {evidence.attachments?.length ? <ul className="admin-report-files">{evidence.attachments.map(file => <li key={file.id}><span>{file.name || `Tệp #${file.id}`}</span><Button variant="outline" disabled={downloading !== null} onClick={() => download(file)}>{downloading === file.id ? "Đang tải…" : "Tải bằng chứng"}</Button></li>)}</ul> : <p className="tutor-review-muted">Không có ảnh hoặc tệp bằng chứng đính kèm.</p>}
    {error ? <p className="inline-error" role="alert">{error}</p> : null}
    {evidence.moderation_history?.length ? <details><summary>Lịch sử xử lý đối tượng</summary>{evidence.moderation_history.map(item => <p key={item.id}>{formatDate(item.created_at)} · {({ "review.hide": "Ẩn đánh giá", "review.restore": "Khôi phục đánh giá", "listing.hide": "Ẩn dịch vụ", "listing.restore": "Khôi phục dịch vụ" } as Record<string, string>)[item.action] || item.action}{item.reason ? ` · ${item.reason}` : ""}</p>)}</details> : null}
  </div>;
}
function ReportsSkeleton() {
  return <div className="tutor-review-skeleton" role="status" aria-label="Đang tải báo cáo"><span/><span/><span/></div>;
}
