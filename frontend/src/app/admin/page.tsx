"use client";

import "@/app/admin/admin.css";
import { VerificationDocumentPreview } from "@/components/admin/verification-document-preview";
import { AppShell } from "@/components/layout/app-shell";
import { Badge, Button, ErrorActions, Field, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { assignAdminTutorProfile, getAdminTutorProfile, getAdminTutorReviewPage, getCurrentUser, getSpecializations, performAdminAccountAction, performAdminTutorAction, type AdminAction } from "@/lib/data/services";
import type { AdminTutorProfile, AdminTutorQueueItem, TutorProfileStatus, VerificationDocument } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useRef, useState } from "react";

const labels: Record<TutorProfileStatus, string> = {
  draft: "Bản nháp", submitted: "Đang chờ", changes_requested: "Cần bổ sung",
  approved: "Đã phê duyệt", rejected: "Đã từ chối", suspended: "Đã đình chỉ",
};
const filters = ["submitted", "changes_requested", "approved", "rejected", "suspended"] as const;
const actionLabels = {
  approve: "Phê duyệt hồ sơ", "request-changes": "Yêu cầu bổ sung", reject: "Từ chối",
  suspend: "Đình chỉ hồ sơ", restore: "Khôi phục hồ sơ",
  "account-suspend": "Đình chỉ tài khoản", "account-restore": "Khôi phục tài khoản",
};
const actionOrder = ["reject", "request-changes", "approve", "suspend", "restore", "account-suspend", "account-restore"] as const;
const documentLabels: Record<string, string> = {
  identity_card: "CCCD / CMND", passport: "Hộ chiếu", degree: "Bằng cấp", certificate: "Chứng chỉ", other: "Tài liệu khác",
};

function tone(status: TutorProfileStatus): "neutral" | "success" | "warning" {
  return status === "approved" ? "success" : ["submitted", "changes_requested", "suspended"].includes(status) ? "warning" : "neutral";
}
function date(value?: string | null, withTime = true) {
  if (!value) return "Chưa cung cấp";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Chưa cung cấp";
  return new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", ...(withTime ? { hour: "2-digit", minute: "2-digit" } as const : { timeZone: "UTC" }) }).format(parsed);
}
function initials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map(part => part[0]).join("").toUpperCase();
}
function DocumentIcon() {
  return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8l-5-5Z"/><path d="M14 3v5h5M8 13h8M8 17h5"/></svg>;
}

export default function AdminPage() {
  const detailRequest = useRef(0);
  const [scope, setScope] = useState<"unassigned" | "mine">("unassigned");
  const [status, setStatus] = useState<(typeof filters)[number]>("submitted");
  const [page, setPage] = useState(1);
  const [query, setQuery] = useState("");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<AdminTutorProfile | null>(null);
  const [previewDocument, setPreviewDocument] = useState<VerificationDocument | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [reason, setReason] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [claimingId, setClaimingId] = useState<number | null>(null);
  const [claimError, setClaimError] = useState<string | null>(null);
  const load = useCallback(() => getAdminTutorReviewPage(scope, status, page), [scope, status, page]);
  const { data, error, loading, reload } = useAsyncData(load);
  const { data: specializations } = useAsyncData(getSpecializations);
  const busy = saving || claimingId !== null;

  const clearSelection = () => {
    setPreviewDocument(null);
    detailRequest.current++;
    setSelectedId(null);
    setDetail(null);
    setDetailLoading(false);
    setDetailError(null);
    setReason("");
    setActionError(null);
    setActionMessage("");
    setClaimError(null);
  };
  const openProfile = async (id: number) => {
    setPreviewDocument(null);
    const version = ++detailRequest.current;
    setSelectedId(id);
    setDetail(null);
    setDetailLoading(true);
    setDetailError(null);
    setReason("");
    setActionError(null);
    setActionMessage("");
    try {
      const result = await getAdminTutorProfile(id);
      if (version === detailRequest.current) setDetail(result);
    } catch (nextError) {
      if (version === detailRequest.current) setDetailError(describeApiError(nextError));
    } finally {
      if (version === detailRequest.current) setDetailLoading(false);
    }
  };
  const claimProfile = async (item: AdminTutorQueueItem) => {
    setClaimingId(item.id);
    setClaimError(null);
    try {
      const admin = await getCurrentUser();
      await assignAdminTutorProfile(item.id, admin.id, item.assignment_version);
      setScope("mine");
      setPage(1);
      setQuery("");
      await openProfile(item.id);
    } catch (nextError) {
      setClaimError(describeApiError(nextError));
      reload();
    } finally {
      setClaimingId(null);
    }
  };
  const runAction = async (action: AdminAction | "account-suspend" | "account-restore") => {
    if (!detail) return;
    if (reason.trim().length < 2 || reason.trim().length > 2000) {
      setActionError("Vui lòng nhập ghi chú xét duyệt từ 2 đến 2.000 ký tự trước khi thực hiện.");
      return;
    }
    if (!detail.allowed_actions.includes(action)) return;
    setSaving(true);
    setActionError(null);
    setActionMessage("");
    try {
      if (action === "account-suspend" || action === "account-restore") {
        const user = await performAdminAccountAction(detail.user.id, action === "account-suspend" ? "suspend" : "restore", reason.trim());
        setDetail({ ...detail, user, allowed_actions: [] });
        try { setDetail(await getAdminTutorProfile(detail.profile.id)); }
        catch { setActionMessage("Đã cập nhật tài khoản. Tải lại hồ sơ để xem quyền thao tác mới."); }
      } else {
        setDetail(await performAdminTutorAction(detail.profile.id, action, reason.trim(), detail));
      }
      setReason("");
      setActionMessage(previous => previous || "Đã lưu quyết định xét duyệt.");
      reload();
    } catch (nextError) {
      if (nextError instanceof ApiClientError && [403, 404].includes(nextError.status ?? 0)) setDetail(null);
      setActionError(describeApiError(nextError));
      try { setDetail(await getAdminTutorProfile(detail.profile.id)); } catch { setDetail(null); }
    } finally { setSaving(false); }
  };

  const search = query.trim().toLocaleLowerCase("vi");
  const items = (data?.items ?? []).filter(item => {
    const text = "profile" in item ? `${item.user.full_name} ${item.user.email} ${item.profile.headline} ${item.profile.id}` : String(item.id);
    return text.toLocaleLowerCase("vi").includes(search);
  });
  const revision = detail?.revision ?? detail?.profile.revision;
  const reviewedProfile = detail ? { ...detail.profile, ...(revision?.status === "submitted" ? revision.payload : {}) } : null;
  const specialization = specializations?.find(item => item.id === reviewedProfile?.specialization_id)?.name;

  return (
    <AppShell role="admin" title="">
      <section className="tutor-review" aria-label="Duyệt hồ sơ Tutor">
        <aside className="tutor-review-queue" aria-label="Danh sách hồ sơ">
          <div className="tutor-review-tabs" role="group" aria-label="Phạm vi hồ sơ Tutor">
            {(["unassigned", "mine"] as const).map(value => <button key={value} type="button" aria-pressed={scope === value} disabled={busy} onClick={() => {
              if (scope === value) return;
              clearSelection(); setScope(value); setPage(1); setQuery("");
            }}>{value === "unassigned" ? "Chưa phân công" : "Hồ sơ của tôi"}</button>)}
          </div>
          <div className="tutor-review-filters">
            <label className="tutor-review-search"><span className="landing-sr-only">Tìm trong danh sách hiện tại</span>
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
              <input type="search" placeholder={scope === "mine" ? "Tìm tên hoặc email trong trang…" : "Tìm mã hồ sơ trong trang…"} value={query} onChange={event => setQuery(event.target.value)} />
            </label>
            <label className="tutor-review-status"><span className="landing-sr-only">Lọc trạng thái</span>
              <select disabled={busy} value={status} onChange={event => { clearSelection(); setStatus(event.target.value as typeof status); setPage(1); setQuery(""); }}>
                {filters.map(value => <option key={value} value={value}>{labels[value]}</option>)}
              </select>
            </label>
          </div>
          {scope === "unassigned" ? <p className="tutor-review-privacy">Nhận hồ sơ để xem thông tin gia sư và bắt đầu xét duyệt.</p> : null}
          {claimError ? <p className="inline-error" role="alert">{claimError}</p> : null}
          <div className="tutor-review-list" aria-busy={loading}>
            {loading ? <ReviewSkeleton /> : error ? <StatePanel kind="error" message={error.status === 403 ? "Tài khoản chưa có quyền xem danh sách này." : error.message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} /> : !items.length ? <div className="tutor-review-list-empty" role="status"><strong>{search ? "Không tìm thấy hồ sơ" : "Chưa có hồ sơ"}</strong><p>{search ? "Thử tên, email hoặc mã khác trong trang này." : "Không có hồ sơ ở trạng thái đã chọn."}</p></div> : items.map(item => "profile" in item ? (
              <button key={item.profile.id} className="tutor-review-person" type="button" disabled={busy} aria-pressed={selectedId === item.profile.id} onClick={() => openProfile(item.profile.id)}>
                <span className="tutor-review-avatar" aria-hidden="true">{initials(item.user.full_name)}</span>
                <span className="tutor-review-person-copy"><strong>{item.user.full_name}</strong><span>{item.profile.headline || "Hồ sơ gia sư"}</span></span>
                <Badge tone={tone(item.profile.status)}>{labels[item.profile.status]}</Badge>
              </button>
            ) : <div className="tutor-review-unassigned" key={item.id}>
              <span className="tutor-review-file-icon"><DocumentIcon /></span>
              <div><strong>Hồ sơ #{item.id}</strong><p>Gửi: {date(item.submitted_at)}</p><Badge tone={tone(item.status)}>{labels[item.status]}</Badge></div>
              <Button variant="outline" disabled={busy} onClick={() => claimProfile(item)}>{claimingId === item.id ? "Đang nhận…" : "Nhận hồ sơ"}</Button>
            </div>)}
          </div>
          {data ? <div className="tutor-review-pagination"><span>{data.total} hồ sơ</span><div>
            <Button variant="secondary" disabled={busy || loading || page <= 1} onClick={() => { setQuery(""); setPage(value => value - 1); }}>Trước</Button>
            <span aria-live="polite">{page}</span>
            <Button variant="secondary" disabled={busy || loading || page * data.perPage >= data.total} onClick={() => { setQuery(""); setPage(value => value + 1); }}>Sau</Button>
          </div></div> : null}
        </aside>

        <section className="tutor-review-detail" aria-label="Chi tiết hồ sơ" aria-busy={detailLoading}>
          {detailLoading ? <ReviewSkeleton /> : detailError ? <StatePanel kind="error" message={detailError} action={<ErrorActions onRetry={() => selectedId && openProfile(selectedId)} />} /> : detail && reviewedProfile ? <>
            <header className="tutor-review-profile-header">
              <span className="tutor-review-avatar tutor-review-avatar-large" aria-hidden="true">{initials(detail.user.full_name)}</span>
              <div className="tutor-review-identity"><div><h1>{detail.user.full_name}</h1><Badge tone={tone(detail.profile.status)}>{labels[detail.profile.status]}</Badge></div>
                <p>{reviewedProfile.headline || "Hồ sơ gia sư"}</p>
                <div className="tutor-review-profile-meta"><span>Hồ sơ #{selectedId} · Gửi ngày {date(reviewedProfile.submitted_at)}</span><Badge tone={detail.user.email_verified_at ? "success" : "warning"}>{detail.user.email_verified_at ? "✓ Email đã xác minh" : "Email chưa xác minh"}</Badge></div>
              </div>
            </header>
            {revision?.status === "submitted" ? <p className="tutor-review-notice">Đang xem thông tin cập nhật gia sư gửi xét duyệt (lần {revision.revision_no}).</p> : null}
            <section className="tutor-review-section"><h2>Thông tin cá nhân</h2><dl className="tutor-review-fields">
              <div><dt>Họ và tên</dt><dd>{detail.user.full_name}</dd></div><div><dt>Email</dt><dd>{detail.user.email}</dd></div>
              <div><dt>Ngày sinh</dt><dd>{date(detail.user.date_of_birth, false)}</dd></div><div><dt>Trạng thái tài khoản</dt><dd>{{ active: "Đang hoạt động", suspended: "Đã đình chỉ", deactivated: "Đã ngừng hoạt động" }[detail.user.status]}</dd></div>
            </dl></section>
            <section className="tutor-review-section"><h2>Chuyên môn & kinh nghiệm</h2><dl className="tutor-review-fields">
              <div><dt>Chuyên môn</dt><dd>{specialization || "Chưa cung cấp"}</dd></div><div><dt>Kinh nghiệm giảng dạy</dt><dd>{reviewedProfile.experience_years} năm</dd></div>
              <div className="tutor-review-field-full"><dt>Bằng cấp & chứng chỉ</dt><dd>{reviewedProfile.qualification_claims?.length ? <ul className="tutor-review-qualifications">{reviewedProfile.qualification_claims.map((claim, index) => <li key={`${claim.type}-${index}`}><span>{claim.type === "degree" ? "Bằng cấp" : "Chứng chỉ"}</span>{claim.name}</li>)}</ul> : "Chưa cung cấp"}</dd></div>
            </dl></section>
            <section className="tutor-review-section"><h2>Giới thiệu bản thân</h2><p className="tutor-review-bio">{reviewedProfile.bio || "Gia sư chưa cung cấp phần giới thiệu."}</p></section>
            <section className="tutor-review-section"><h2>Tài liệu xác minh</h2>
              {detail.verification_documents === null ? <p className="tutor-review-muted">Tài khoản của bạn chưa có quyền xem tài liệu xác minh.</p> : !detail.verification_documents.length ? <p className="tutor-review-muted">Chưa có tài liệu xác minh được tải lên.</p> : <ul className="tutor-review-documents">{detail.verification_documents.map(document => <li key={document.id}>
                <span className="tutor-review-file-icon"><DocumentIcon /></span><div><strong>{document.original_name || `Tài liệu #${document.id}`}</strong><span>{documentLabels[document.document_type] || "Tài liệu xác minh"}{document.size_bytes ? ` · ${(document.size_bytes / 1_000_000).toLocaleString("vi-VN", { maximumFractionDigits: 1 })} MB` : ""}</span></div>
                <button className="tutor-review-document-view" type="button" onClick={() => setPreviewDocument(document)} aria-label={`Xem ${document.original_name || "tài liệu"}`}><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Xem</button>
              </li>)}</ul>}
            </section>
            {detail.profile.review_reason || detail.profile.suspended_at ? <section className="tutor-review-section tutor-review-history"><h2>Thông tin xét duyệt</h2>{detail.profile.review_reason ? <p>{detail.profile.review_reason}</p> : null}{detail.profile.suspended_at ? <p>Đình chỉ ngày {date(detail.profile.suspended_at)} · {detail.profile.suspension_reason}</p> : null}</section> : null}
            <form className="tutor-review-decision" onSubmit={event => event.preventDefault()}>
              {detail.allowed_actions.length ? <Field label="Ghi chú xét duyệt" htmlFor="admin-reason" hint="Nhập lý do cho quyết định của bạn (2–2.000 ký tự)." error={actionError ?? undefined}>
                <textarea rows={4} maxLength={2000} disabled={saving} placeholder="Nhập lý do khi phê duyệt, yêu cầu bổ sung hoặc từ chối…" value={reason} onChange={event => { setReason(event.target.value); setActionError(null); }} />
              </Field> : <p className="tutor-review-muted">Hiện bạn không có thao tác xét duyệt khả dụng cho hồ sơ này.</p>}
              {!detail.allowed_actions.length && actionError ? <p className="inline-error" role="alert">{actionError}</p> : null}
              {actionMessage ? <p className="inline-success" role="status">{actionMessage}</p> : null}
              <div className="tutor-review-action-row">{actionOrder.filter(action => detail.allowed_actions.includes(action)).map(action => <Button key={action} className={action === "reject" || action === "account-suspend" || action === "suspend" ? "tutor-review-destructive" : undefined} variant={action === "approve" ? "primary" : action === "reject" ? "ghost" : "secondary"} disabled={saving} onClick={() => runAction(action)}>{saving ? "Đang xử lý…" : actionLabels[action]}</Button>)}</div>
            </form>
          </> : <div className="tutor-review-placeholder"><span className="tutor-review-placeholder-icon"><DocumentIcon /></span><h2>Chọn hồ sơ để xem chi tiết</h2><p>{scope === "unassigned" ? "Nhận một hồ sơ từ danh sách bên trái để bắt đầu xét duyệt." : "Thông tin và tài liệu của gia sư sẽ hiển thị tại đây."}</p>{actionError ? <p className="inline-error" role="alert">{actionError}</p> : null}</div>}
        </section>
      </section>
      {previewDocument ? <VerificationDocumentPreview key={previewDocument.file_id} file={previewDocument} onClose={() => setPreviewDocument(null)} /> : null}
    </AppShell>
  );
}

function ReviewSkeleton() {
  return <div className="tutor-review-skeleton" role="status" aria-label="Đang tải hồ sơ"><span/><span/><span/><span/></div>;
}
