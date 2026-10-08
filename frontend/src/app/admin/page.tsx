"use client";

import { useCallback, useState } from "react";
import { AppShell } from "@/components/app-shell";
import { Badge, Button, DataTable, ErrorActions, Field, StatePanel } from "@/components/ui";
import { getAdminTutorProfile, getAdminTutorProfiles, performAdminAccountAction, performAdminTutorAction, type AdminAction } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import type { AdminTutorProfile, TutorApprovalStatus } from "@/lib/data/types";

const labels: Record<TutorApprovalStatus, string> = {
  pending: "Đang chờ",
  changes_requested: "Yêu cầu chỉnh sửa",
  active: "Đã duyệt",
  rejected: "Từ chối",
  cancelled: "Đã huỷ",
  expired: "Hết hạn",
};

const filters = ["pending", "changes_requested", "active", "rejected"] as const;

function tone(status: TutorApprovalStatus): "neutral" | "success" | "warning" {
  return status === "active" ? "success" : status === "pending" || status === "changes_requested" ? "warning" : "neutral";
}

function date(value: string | null | undefined): string {
  return value ? new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "—";
}

export default function AdminPage() {
  const [status, setStatus] = useState<(typeof filters)[number]>("pending");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<AdminTutorProfile | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [reason, setReason] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const load = useCallback(() => getAdminTutorProfiles(status), [status]);
  const { data, error, loading, reload } = useAsyncData(load);

  const openProfile = async (id: number) => {
    setSelectedId(id);
    setDetail(null);
    setDetailError(null);
    setDetailLoading(true);
    try {
      setDetail(await getAdminTutorProfile(id));
    } catch (nextError) {
      setDetailError(nextError instanceof Error ? nextError.message : "Không thể tải chi tiết hồ sơ.");
    } finally {
      setDetailLoading(false);
    }
  };

  const runAction = async (action: AdminAction | "account-suspend" | "account-restore") => {
    if (!detail || !reason.trim()) {
      setActionError("Vui lòng nhập lý do trước khi thực hiện quyết định.");
      return;
    }

    setSaving(true);
    setActionError(null);
    try {
      if (action === "account-suspend" || action === "account-restore") {
        const user = await performAdminAccountAction(detail.user.id, action === "account-suspend" ? "suspend" : "restore", reason.trim());
        setDetail({ ...detail, user });
      } else {
        setDetail(await performAdminTutorAction(detail.profile.id, action, reason.trim()));
      }
      setReason("");
      reload();
    } catch (nextError) {
      setActionError(nextError instanceof Error ? nextError.message : "Không thể thực hiện quyết định.");
    } finally {
      setSaving(false);
    }
  };

  const rows = data ?? [];

  return (
    <AppShell role="admin" title="Admin shell">
      <section className="content-stack">
        <div className="section-heading">
          <div>
            <h1>Duyệt Tutor</h1>
            <p className="lead">Theo dõi, kiểm tra và ghi nhận quyết định đối với hồ sơ Tutor.</p>
          </div>
          <Badge tone="warning">Backend kiểm quyền</Badge>
        </div>

        <Field label="Lọc trạng thái" htmlFor="admin-status">
          <select
            id="admin-status"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value as typeof status);
              setSelectedId(null);
              setDetail(null);
            }}
          >
            {filters.map((value) => <option key={value} value={value}>{labels[value]}</option>)}
          </select>
        </Field>

        {loading ? <StatePanel kind="loading" message="Đang tải hồ sơ…" /> : error ? (
          <StatePanel
            kind="error"
            message={error.status === 401 ? "Bạn cần đăng nhập để xem khu vực Admin." : error.status === 403 ? "Tài khoản không có permission Admin phù hợp." : error.message}
            action={<ErrorActions login={error.status === 401} onRetry={reload} />}
          />
        ) : !rows.length ? <StatePanel kind="empty" message="Không có hồ sơ ở trạng thái này." /> : (
          <DataTable
            rowKey={(row) => row.profile.id}
            rows={rows}
            columns={[
              { key: "name", label: "Tutor", render: (row) => <strong>{row.user.full_name}</strong> },
              { key: "headline", label: "Chuyên môn", render: (row) => row.profile.headline },
              { key: "experience", label: "Kinh nghiệm", render: (row) => `${row.profile.experience_years} năm` },
              { key: "status", label: "Trạng thái", render: (row) => <Badge tone={tone(row.profile.approval_status)}>{labels[row.profile.approval_status]}</Badge> },
              { key: "action", label: "Thao tác", render: (row) => <button className="text-button" type="button" onClick={() => openProfile(row.profile.id)}>Mở hồ sơ</button> },
            ]}
          />
        )}

        {selectedId ? (
          <section className="card detail-card" aria-live="polite">
            <div className="section-heading">
              <div><p className="eyebrow">Tutor Profile #{selectedId}</p><h2>{detail?.user.full_name ?? "Chi tiết hồ sơ"}</h2></div>
              {detail ? <Badge tone={tone(detail.profile.approval_status)}>{labels[detail.profile.approval_status]}</Badge> : null}
            </div>
            {detailLoading ? <StatePanel kind="loading" message="Đang tải chi tiết hồ sơ…" /> : detailError ? <StatePanel kind="error" message={detailError} action={<ErrorActions onRetry={() => openProfile(selectedId)} />} /> : detail ? (
              <>
                <dl className="detail-list">
                  <div><dt>Email</dt><dd>{detail.user.email}</dd></div>
                  <div><dt>Chuyên môn</dt><dd>{detail.profile.headline}</dd></div>
                  <div><dt>Kinh nghiệm</dt><dd>{detail.profile.experience_years} năm</dd></div>
                  <div><dt>Đã gửi</dt><dd>{date(detail.profile.submitted_at)}</dd></div>
                  <div><dt>Review reason</dt><dd>{detail.profile.review_reason ?? "—"}</dd></div>
                  <div><dt>Profile suspension</dt><dd>{detail.profile.suspended_at ? `Đang suspend · ${detail.profile.suspension_reason ?? ""}` : "Không"}</dd></div>
                  <div><dt>Account status</dt><dd>{detail.user.status}</dd></div>
                </dl>

                <div className="content-stack admin-documents">
                  <h3>Verification documents</h3>
                  {detail.verification_documents === null ? <p className="muted">Bạn không có permission download verification document.</p> : !detail.verification_documents.length ? <p className="muted">Hồ sơ chưa có verification document.</p> : <ul>{detail.verification_documents.map((document) => <li key={document.id}><span>{document.original_name ?? document.document_type}</span><a className="text-button" href={document.download_url ?? "#"}>Tải xuống</a></li>)}</ul>}
                </div>

                <form className="form-stack admin-actions" onSubmit={(event) => event.preventDefault()}>
                  <Field label="Lý do quyết định" htmlFor="admin-reason" hint="Bắt buộc cho mọi approve, request changes, reject, suspend và restore.">
                    <textarea id="admin-reason" rows={3} value={reason} onChange={(event) => { setReason(event.target.value); setActionError(null); }} />
                  </Field>
                  {actionError ? <p className="inline-error" role="alert">{actionError}</p> : null}
                  <div className="button-row">
                    {detail.profile.approval_status === "pending" ? <><Button disabled={saving} onClick={() => runAction("approve")}>Approve</Button><Button disabled={saving} onClick={() => runAction("request-changes")}>Request Changes</Button><Button disabled={saving} onClick={() => runAction("reject")}>Reject</Button></> : null}
                    {detail.profile.suspended_at ? <Button disabled={saving} onClick={() => runAction("restore")}>Restore profile</Button> : <Button disabled={saving} onClick={() => runAction("suspend")}>Suspend profile</Button>}
                    {detail.user.status === "suspended" ? <Button disabled={saving} onClick={() => runAction("account-restore")}>Restore account</Button> : <Button disabled={saving} onClick={() => runAction("account-suspend")}>Suspend account</Button>}
                  </div>
                </form>
              </>
            ) : null}
          </section>
        ) : null}
      </section>
    </AppShell>
  );
}
