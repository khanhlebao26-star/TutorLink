"use client";

import { WorkspaceForm } from "@/components/programs/workspace-form";
import { sessionFields } from "@/components/programs/workspace-form-fields";
import { Badge } from "@/components/shared/ui";
import {
  localTimestamp,
  type WorkspaceRow
} from "@/lib/data/workspace";
import "@/styles/programs.css";

export function SessionCreateDialog({
  id,
  start,
  end,
  onSaved,
  onClose,
}: {
  id: number;
  start: Date;
  end: Date;
  onSaved: () => void;
  onClose: () => void;
}) {
  return <div className="session-detail-backdrop" onClick={onClose}>
    <section className="session-detail-dialog session-create-dialog" role="dialog" aria-modal="true" aria-labelledby="session-create-title" onClick={(event) => event.stopPropagation()}>
      <button className="session-detail-close" type="button" onClick={onClose} aria-label="Đóng form lên lịch">×</button>
      <p className="session-detail-eyebrow">LỊCH BUỔI HỌC</p>
      <h2 id="session-create-title">Lên lịch buổi học</h2>
      <p className="session-create-hint">Ngày và giờ được điền theo ô bạn chọn trên lịch. Bạn có thể điều chỉnh trước khi lưu.</p>
      <WorkspaceForm
        url={`/programs/${id}/sessions`}
        fields={sessionFields}
        initial={{ planned_starts_at: localTimestamp(start.toISOString()), planned_ends_at: localTimestamp(end.toISOString()) }}
        label="Lưu buổi học"
        onSaved={onSaved}
      />
    </section>
  </div>;
}

export function SessionDetailDialog({
  row,
  active,
  writable,
  onSaved,
  onClose,
}: {
  row: WorkspaceRow;
  active: boolean;
  writable: boolean;
  onSaved: () => void;
  onClose: () => void;
}) {
  const start = row.planned_starts_at ? new Date(row.planned_starts_at) : null;
  const end = row.planned_ends_at ? new Date(row.planned_ends_at) : null;
  const validStart = start && !Number.isNaN(start.getTime()) ? start : null;
  const validEnd = end && !Number.isNaN(end.getTime()) ? end : null;
  const status = row.status ?? "scheduled";
  const statusLabel = status === "scheduled" ? "Đã lên lịch" : status === "completed" ? "Đã hoàn thành" : status === "cancelled" ? "Đã hủy" : status;
  const statusTone = status === "completed" ? "success" : status === "cancelled" ? "danger" : "brand";
  const deliveryLabel = row.delivery_mode === "online" ? "Trực tuyến" : row.delivery_mode === "offline" ? "Trực tiếp" : row.delivery_mode === "hybrid" ? "Kết hợp" : "Chưa xác định hình thức";

  return <div className="session-detail-backdrop" onClick={onClose}>
    <section className="session-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="session-detail-title" onClick={(event) => event.stopPropagation()}>
      <button className="session-detail-close" type="button" onClick={onClose} aria-label="Đóng thông tin buổi học">×</button>
      <p className="session-detail-eyebrow">CHI TIẾT BUỔI HỌC · #{row.id}</p>
      <div className="session-detail-title-row"><h2 id="session-detail-title">{row.title || "Buổi học"}</h2><Badge tone={statusTone}>{statusLabel}</Badge></div>
      <dl className="session-detail-facts">
        <div><dt>Thời gian</dt><dd>{validStart ? validStart.toLocaleDateString("vi-VN", { weekday: "long", day: "numeric", month: "long", year: "numeric" }) : "Chưa xếp lịch"}{validStart ? ` · ${validStart.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })}` : ""}{validEnd ? ` – ${validEnd.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })}` : ""}</dd></div>
        <div><dt>Hình thức</dt><dd>{deliveryLabel}</dd></div>
        {row.location_text ? <div><dt>Địa điểm</dt><dd>{row.location_text}</dd></div> : null}
        {row.actual ? <div><dt>Kết quả</dt><dd>{row.actual.status === "completed" ? "Đã hoàn thành" : row.actual.status}</dd></div> : null}
      </dl>
      {row.meeting_url ? <a className="button button-outline session-detail-meeting" href={row.meeting_url} target="_blank" rel="noreferrer">Tham gia buổi học ↗</a> : null}
      {writable && status === "scheduled" ? <div className="session-detail-actions">
        <WorkspaceForm url={`/sessions/${row.id}/complete`} fixed={{ expected_version: row.version }} label="Hoàn thành buổi học" onSaved={onSaved} />
        <details>
          <summary>Thao tác khác</summary>
          <div className="workspace-session-more-actions">
            <WorkspaceForm url={`/sessions/${row.id}/cancel`} fixed={{ expected_version: row.version }} label="Hủy lịch học" onSaved={onSaved} />
            {active ? <details className="workspace-session-edit">
              <summary>Chỉnh sửa lịch</summary>
              <WorkspaceForm
                url={`/sessions/${row.id}`}
                method="PATCH"
                fields={sessionFields}
                initial={{
                  planned_starts_at: localTimestamp(row.planned_starts_at),
                  planned_ends_at: localTimestamp(row.planned_ends_at),
                  title: row.title ?? "",
                  delivery_mode: row.delivery_mode ?? "online",
                  location_text: row.location_text ?? "",
                  meeting_url: row.meeting_url ?? "",
                }}
                fixed={{ expected_version: row.version }}
                label="Lưu lịch"
                onSaved={onSaved}
              />
            </details> : null}
          </div>
        </details>
      </div> : null}
    </section>
  </div>;
}
