"use client";

import { AppShell } from "@/components/app-shell";
import { Badge, Button, DataTable, ErrorActions, Field, StatePanel } from "@/components/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { assignAdminTutorProfile, getAdminTutorProfile, getAdminTutorProfiles, getAdminTutorQueue, getCurrentUser, performAdminAccountAction, performAdminTutorAction, type AdminAction } from "@/lib/data/services";
import type { AdminTutorProfile, AdminTutorQueueItem, TutorProfileStatus } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useRef, useState } from "react";

const labels: Record<TutorProfileStatus, string> = {
  draft: "Bản nháp",
  submitted: "Đang chờ",
  changes_requested: "Yêu cầu chỉnh sửa",
  approved: "Đã duyệt",
  rejected: "Từ chối",
  suspended: "Đình chỉ hồ sơ",
};

const filters = [
  "submitted",
  "changes_requested",
  "approved",
  "rejected",
  "suspended",
] as const;

function tone(
  status: TutorProfileStatus,
): "neutral" | "success" | "warning" {
  if (status === "approved") return "success";

  if (
    status === "submitted"
    || status === "changes_requested"
    || status === "suspended"
  ) {
    return "warning";
  }

  return "neutral";
}

function date(value: string | null | undefined): string {
  return value ? new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "—";
}

export default function AdminPage() {
  const detailRequest = useRef(0);
  const [scope, setScope] = useState<"unassigned" | "mine">("unassigned");
  const [status, setStatus] = useState<(typeof filters)[number]>("submitted");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [detail, setDetail] = useState<AdminTutorProfile | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState<string | null>(null);
  const [reason, setReason] = useState("");
  const [actionMessage, setActionMessage] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [claimingId, setClaimingId] = useState<number | null>(null);
  const [claimError, setClaimError] = useState<string | null>(null);
  const load = useCallback(
    async () => scope === "unassigned" ? await getAdminTutorQueue(status) : await getAdminTutorProfiles(status),
    [scope, status],
  );
  const { data, error, loading, reload } = useAsyncData(load);

  const openProfile = async (id: number) => {
    const version = ++detailRequest.current;
    setReason("");
    setActionError(null);
    setActionMessage("");
    setSelectedId(id);
    setDetail(null);
    setDetailError(null);
    setDetailLoading(true);
    try {
      const result = await getAdminTutorProfile(id);
      if (version === detailRequest.current) setDetail(result);
    } catch (nextError) {
      if (version === detailRequest.current) setDetailError(describeApiError(nextError));
    } finally {
      if (version === detailRequest.current) setDetailLoading(false);
    }
  };

  const runAction = async (action: AdminAction | "account-suspend" | "account-restore") => {
    if (!detail || !reason.trim()) {
      setActionError("Vui lòng nhập lý do trước khi thực hiện quyết định.");
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
        try {
          setDetail(await getAdminTutorProfile(detail.profile.id));
        } catch {
          setActionMessage("Đã cập nhật trạng thái tài khoản. Chưa tải được quyền thao tác mới; hãy tải lại hồ sơ.");
        }
      } else {
        setDetail(await performAdminTutorAction(detail.profile.id, action, reason.trim(), detail));
      }
      setReason("");
      reload();
    } catch (nextError) {
      if(nextError instanceof ApiClientError && [403,404].includes(nextError.status??0))setDetail(null);
      setActionError(describeApiError(nextError));
      try { setDetail(await getAdminTutorProfile(detail.profile.id)); } catch { setDetail(null); }
    } finally {
      setSaving(false);
    }
  };

  const rows = scope === "mine" ? (data as AdminTutorProfile[] | null) ?? [] : [];
  const queueRows = scope === "unassigned" ? (data as AdminTutorQueueItem[] | null) ?? [] : [];

  const claimProfile = async (item: AdminTutorQueueItem) => {
    setClaimingId(item.id);
    setClaimError(null);
    try {
      const admin = await getCurrentUser();
      await assignAdminTutorProfile(item.id, admin.id, item.assignment_version);
      setScope("mine");
      await openProfile(item.id);
    } catch (nextError) {
      setClaimError(describeApiError(nextError));
      reload();
    } finally {
      setClaimingId(null);
    }
  };

  return (
    <AppShell role="admin" title="Admin shell">
      <section className="content-stack">
        <div className="section-heading">
          <div>
            <h1>Duyệt Tutor</h1>
            <p className="lead">Theo dõi, kiểm tra và ghi nhận quyết định đối với hồ sơ Tutor.</p>
          </div>
        </div>

        <div className="button-row" role="group" aria-label="Phạm vi hồ sơ Tutor">
          <Button disabled={saving || claimingId !== null} variant={scope === "unassigned" ? "primary" : "secondary"} onClick={() => {
            detailRequest.current++;
            setSelectedId(null);
            setDetail(null);
            setClaimError(null);
            setScope("unassigned");
          }}>Chưa phân công</Button>
          <Button disabled={saving || claimingId !== null} variant={scope === "mine" ? "primary" : "secondary"} onClick={() => {
            detailRequest.current++;
            setSelectedId(null);
            setDetail(null);
            setClaimError(null);
            setScope("mine");
          }}>Hồ sơ của tôi</Button>
        </div>

        <Field label="Lọc trạng thái" htmlFor="admin-status">
          <select
            disabled={saving}
            id="admin-status"
            value={status}
            onChange={(event) => {
              detailRequest.current++;
              setDetailLoading(false);
              setDetailError(null);
              setReason("");
              setActionError(null);
              setClaimError(null);
              setStatus(event.target.value as typeof status);
              setSelectedId(null);
              setDetail(null);
            }}
          >
            {filters.map((value) => <option key={value} value={value}>{labels[value]}</option>)}
          </select>
        </Field>

        {scope === "unassigned" ? <p className="muted">Hồ sơ trong hàng đợi chưa hiển thị thông tin cá nhân. Nhận hồ sơ để đưa vào danh sách xử lý của bạn.</p> : null}
        {claimError ? <p className="inline-error" role="alert">{claimError}</p> : null}

        {loading ? <StatePanel kind="loading" message="Đang tải hồ sơ…" /> : error ? (
          <StatePanel
            kind="error"
            message={error.status === 401 ? "Bạn cần đăng nhập để xem khu vực Admin." : error.status === 403 ? "Tài khoản không có permission Admin phù hợp." : error.message}
            action={<ErrorActions login={error.status === 401} onRetry={reload} />}
          />
        ) : scope === "unassigned" && !queueRows.length ? <StatePanel kind="empty" message="Không có hồ sơ chưa phân công ở trạng thái này." /> : scope === "mine" && !rows.length ? <StatePanel kind="empty" message="Bạn chưa có hồ sơ được phân công ở trạng thái này." /> : scope === "unassigned" ? (
          <DataTable
            rowKey={(row) => row.id}
            rows={queueRows}
            columns={[
              { key: "id", label: "Mã hồ sơ", render: (row) => <strong>#{row.id}</strong> },
              { key: "submitted", label: "Ngày gửi", render: (row) => date(row.submitted_at) },
              { key: "status", label: "Trạng thái", render: (row) => <Badge tone={tone(row.status)}>{labels[row.status]}</Badge> },
              { key: "action", label: "Thao tác", render: (row) => <Button disabled={claimingId !== null} onClick={() => claimProfile(row)}>{claimingId === row.id ? "Đang nhận…" : "Nhận hồ sơ"}</Button> },
            ]}
          />
        ) : (
          <DataTable
            rowKey={(row) => row.profile.id}
            rows={rows}
            columns={[
              { key: "name", label: "Tutor", render: (row) => <strong>{row.user.full_name}</strong> },
              { key: "headline", label: "Chuyên môn", render: (row) => row.profile.headline },
              { key: "experience", label: "Kinh nghiệm", render: (row) => `${row.profile.experience_years} năm` },
              { key: "status", label: "Trạng thái", render: (row) => <Badge tone={tone(row.profile.status)}>{labels[row.profile.status]}</Badge> },
              { key: "action", label: "Thao tác", render: (row) => <button className="text-button" type="button" disabled={saving} onClick={() => openProfile(row.profile.id)}>Mở hồ sơ</button> },
            ]}
          />
        )}

        {selectedId ? (
          <section className="card detail-card" aria-live="polite">
            <div className="section-heading">
              <div><p className="eyebrow">Tutor Profile #{selectedId}</p><h2>{detail?.user.full_name ?? "Chi tiết hồ sơ"}</h2></div>
              {detail ? <Badge tone={tone(detail.profile.status)}>{labels[detail.profile.status]}</Badge> : null}
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
                  {actionMessage ? <div role="status"><p className="muted">{actionMessage}</p><Button disabled={saving} onClick={() => openProfile(detail.profile.id)}>Tải lại hồ sơ</Button></div> : null}
                  <div className="button-row">
                    {detail.allowed_actions.map((action) => <Button key={action} disabled={saving} onClick={() => runAction(action)}>{{
                      approve: "Approve", "request-changes": "Request Changes", reject: "Reject", suspend: "Suspend profile", restore: "Restore profile",
                      "account-suspend": "Suspend account", "account-restore": "Restore account",
                    }[action]}</Button>)}
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
