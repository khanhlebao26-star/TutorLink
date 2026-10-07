"use client";

import { useCallback } from "react";
import { AppShell } from "@/components/app-shell";
import { getAdminTutorProfiles } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import type { TutorApprovalStatus } from "@/lib/data/types";
import { Badge, DataTable, ErrorActions, StatePanel } from "@/components/ui";

const labels: Record<TutorApprovalStatus, string> = {
  pending: "Đang chờ",
  active: "Đã duyệt",
  changes_requested: "Cần bổ sung",
  rejected: "Từ chối",
  cancelled: "Đã huỷ",
  expired: "Hết hạn",
};

export default function AdminPage() {
  const load = useCallback(() => getAdminTutorProfiles(), []);
  const { data, error, loading, reload } = useAsyncData(load);
  return (
    <AppShell role="admin" title="Admin shell">
      <section className="content-stack">
        <div className="section-heading">
          <div>
            <h1>Duyệt Tutor</h1>
            <p className="lead">Theo dõi các hồ sơ Tutor đang chờ duyệt.</p>
          </div>
          <Badge tone="warning">Permission backend bắt buộc</Badge>
        </div>
        {loading ? (
          <StatePanel kind="loading" message="Đang tải hồ sơ chờ duyệt…" />
        ) : error ? (
          <StatePanel
            kind="error"
            message={
              error.status === 401
                ? "Bạn cần đăng nhập để xem khu vực Admin."
                : error.message
            }
            action={
              <ErrorActions login={error.status === 401} onRetry={reload} />
            }
          />
        ) : !data?.length ? (
          <StatePanel kind="empty" message="Không có hồ sơ đang chờ duyệt." />
        ) : (
          <DataTable
            rowKey={(row) => row.profile.id}
            rows={data}
            columns={[
              {
                key: "name",
                label: "Tutor",
                render: (row) => <strong>{row.user.full_name}</strong>,
              },
              {
                key: "headline",
                label: "Chuyên môn",
                render: (row) => row.profile.headline,
              },
              {
                key: "experience",
                label: "Kinh nghiệm",
                render: (row) => `${row.profile.experience_years} năm`,
              },
              {
                key: "status",
                label: "Trạng thái",
                render: (row) => (
                  <Badge
                    tone={
                      row.profile.approval_status === "pending"
                        ? "warning"
                        : "success"
                    }
                  >
                    {labels[row.profile.approval_status]}
                  </Badge>
                ),
              },
              {
                key: "action",
                label: "Thao tác",
                render: () => (
                  <button className="text-button" type="button">
                    Mở hồ sơ
                  </button>
                ),
              },
            ]}
          />
        )}
      </section>
    </AppShell>
  );
}
