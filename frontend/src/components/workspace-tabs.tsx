"use client";
import Link from "next/link";
import { useCallback, useState } from "react";
import { AppShell, useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Button, Card, StatePanel } from "./ui";
import { WorkspaceForm, type FormField } from "./workspace-form";
import { getProgram } from "@/lib/data/collaboration";
import {
  workspaceRead,
  localTimestamp,
  feeLabel,
  type Collection,
  type WorkspaceRow,
} from "@/lib/data/workspace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/app/workspace.css";
import {
  WorkspaceNavigation,
  workspaceTabs,
  type WorkspaceTab,
} from "./workspace-navigation";
const title: FormField = {
  name: "title",
  label: "Tiêu đề",
  required: true,
  maxLength: 180,
};
const body: FormField = {
  name: "body",
  label: "Nội dung",
  type: "textarea",
  required: true,
};
const reason: FormField = {
  name: "reason",
  label: "Lý do",
  required: true,
  maxLength: 2000,
};
const sessionFields: FormField[] = [
  title,
  {
    name: "planned_starts_at",
    label: "Bắt đầu dự kiến (giờ máy của bạn)",
    type: "datetime-local",
    required: true,
  },
  {
    name: "planned_ends_at",
    label: "Kết thúc dự kiến (giờ máy của bạn)",
    type: "datetime-local",
    required: true,
  },
  {
    name: "delivery_mode",
    label: "Hình thức",
    required: true,
    options: { online: "Trực tuyến", offline: "Trực tiếp", hybrid: "Kết hợp" },
  },
  { name: "location_text", label: "Địa điểm" },
  { name: "meeting_url", label: "Liên kết HTTPS" },
];
const taskFields: FormField[] = [
  title,
  { name: "description", label: "Yêu cầu", type: "textarea" },
  {
    name: "due_at",
    label: "Hạn nộp (giờ máy của bạn)",
    type: "datetime-local",
  },
];
const paths = {
  sessions: "sessions",
  tasks: "tasks",
  progress: "progress-records",
  announcements: "announcements",
  fees: "fee-records",
};
export function WorkspaceTabPage({
  id,
  tab,
}: {
  id: number;
  tab: Exclude<WorkspaceTab, "overview">;
}) {
  return (
    <AppShell title={workspaceTabs[tab]}>
      <TabContent id={id} tab={tab} />
    </AppShell>
  );
}
function TabContent({
  id,
  tab,
}: {
  id: number;
  tab: Exclude<WorkspaceTab, "overview">;
}) {
  const actor = useActor();
  const [page, setPage] = useState(1);
  const announcementState = actor?.role === "tutor" ? "all" : "published";
  const load = useCallback(
    async () => ({
      program: await getProgram(id),
      rows: await workspaceRead<Collection<WorkspaceRow>>(
        `/programs/${id}/${paths[tab]}`,
        tab === "announcements" ? { page, state: announcementState } : { page },
      ),
    }),
    [id, tab, page, announcementState],
  );
  const result = useAsyncData(load);
  const owner = !!result.data?.program.allowed_actions.length;
  const active = result.data?.program.status === "active";
  const paused = result.data?.program.status === "paused";
  const writable = owner && (active || paused);
  const base = `/programs/${id}/${paths[tab]}`;
  return (
    <section className="content-stack workspace-page">
      <WorkspaceNavigation id={id} active={tab} />
      <h1>{workspaceTabs[tab]}</h1>
      <ApiState {...result} />
      {result.data && (
        <>
          <p className="muted">
            {result.data.program.title} · {result.data.program.status}
          </p>
          {owner && (active || (paused && tab === "announcements")) && (
            <Card>
              <details>
                <summary>Tạo mới</summary>
                <WorkspaceForm
                  url={base}
                  onSaved={result.reload}
                  label="Lưu"
                  fields={
                    tab === "sessions"
                      ? sessionFields
                      : tab === "tasks"
                        ? taskFields
                        : tab === "announcements"
                          ? [title, body]
                          : tab === "progress"
                            ? [
                                {
                                  name: "value",
                                  label: "Tiến độ (%)",
                                  type: "number",
                                  min: 0,
                                  max: 100,
                                  required: true,
                                },
                                {
                                  name: "note",
                                  label: "Ghi chú",
                                  type: "textarea",
                                },
                              ]
                            : [
                                {
                                  name: "period",
                                  label: "Tháng",
                                  type: "month",
                                  required: true,
                                },
                                {
                                  name: "amount_minor",
                                  label: "Số tiền VND",
                                  type: "number",
                                  min: 0,
                                  required: true,
                                },
                              ]
                  }
                  fixed={
                    tab === "tasks"
                      ? { assigned_to: result.data.program.customer_id }
                      : tab === "progress"
                        ? { metric_name: "progress_percent" }
                        : tab === "fees"
                          ? { currency: "VND" }
                          : {}
                  }
                />
              </details>
            </Card>
          )}
          {owner && paused && tab === "progress" && (
            <WorkspaceForm
              url={base}
              label="Ghi nhận tiến độ"
              fields={[
                {
                  name: "value",
                  label: "Tiến độ (%)",
                  type: "number",
                  required: true,
                  min: 0,
                  max: 100,
                },
              ]}
              fixed={{ metric_name: "progress_percent" }}
              onSaved={result.reload}
            />
          )}
          {!result.data.rows.data.length && (
            <StatePanel kind="empty" message="Chưa có dữ liệu trong tab này." />
          )}
          {result.data.rows.data.map((row) => (
            <Card key={`${row.id}-${row.version}`}>
              <Badge>{row.status ?? row.state ?? "Đã ghi nhận"}</Badge>
              <h2>
                {row.title ??
                  (tab === "fees" ? row.period : `Tiến độ ${row.value}%`)}
              </h2>
              <p style={{ whiteSpace: "pre-wrap" }}>
                {row.body ?? row.description ?? row.note}
              </p>
              {tab === "sessions" && (
                <>
                  <p>
                    {row.planned_starts_at &&
                      new Date(row.planned_starts_at).toLocaleString(
                        "vi-VN",
                      )}{" "}
                    →{" "}
                    {row.planned_ends_at &&
                      new Date(row.planned_ends_at).toLocaleString("vi-VN")}
                  </p>
                  <p>
                    {row.actual
                      ? `Actual #${row.actual.id} · ${row.actual.status}`
                      : "Chưa có buổi học thực tế"}
                  </p>
                  {writable && row.status === "scheduled" && (
                    <div className="form-grid">
                      <WorkspaceForm
                        url={`/sessions/${row.id}/complete`}
                        fixed={{ expected_version: row.version }}
                        label="Hoàn thành buổi học"
                        onSaved={result.reload}
                      />
                      <WorkspaceForm
                        url={`/sessions/${row.id}/cancel`}
                        fixed={{ expected_version: row.version }}
                        label="Hủy lịch học"
                        onSaved={result.reload}
                      />
                      {active && (
                        <details>
                          <summary>Chỉnh sửa lịch</summary>
                          <WorkspaceForm
                            url={`/sessions/${row.id}`}
                            method="PATCH"
                            fields={sessionFields}
                            initial={{
                              planned_starts_at: localTimestamp(
                                row.planned_starts_at,
                              ),
                              planned_ends_at: localTimestamp(
                                row.planned_ends_at,
                              ),
                              title: row.title ?? "",
                              delivery_mode: row.delivery_mode ?? "online",
                              location_text: row.location_text ?? "",
                              meeting_url: row.meeting_url ?? "",
                            }}
                            fixed={{ expected_version: row.version }}
                            label="Lưu lịch"
                            onSaved={result.reload}
                          />
                        </details>
                      )}
                    </div>
                  )}
                </>
              )}
              {tab === "tasks" && (
                <>
                  <Link
                    className="button button-outline"
                    href={`/programs/${id}/tasks/${row.id}`}
                  >
                    Bài nộp, lịch sử & phản hồi →
                  </Link>
                  {writable &&
                    !["completed", "cancelled"].includes(row.status ?? "") && (
                      <>
                        <details>
                          <summary>Chỉnh sửa</summary>
                          <WorkspaceForm
                            url={`/tasks/${row.id}`}
                            method="PATCH"
                            fields={taskFields}
                            initial={{
                              title: row.title ?? "",
                              description: row.description ?? "",
                            }}
                            fixed={{ expected_version: row.version }}
                            label="Lưu bài tập"
                            onSaved={result.reload}
                          />
                        </details>
                        <WorkspaceForm
                          url={`/tasks/${row.id}/cancel`}
                          fixed={{ expected_version: row.version }}
                          label="Hủy bài tập"
                          onSaved={result.reload}
                        />
                      </>
                    )}
                  {actor?.role === "customer" && row.status === "todo" && (
                    <WorkspaceForm
                      url={`/tasks/${row.id}/start`}
                      fixed={{ expected_version: row.version }}
                      label="Bắt đầu làm bài"
                      onSaved={result.reload}
                    />
                  )}
                </>
              )}
              {tab === "announcements" &&
                writable &&
                row.author_id === actor?.id &&
                row.state === "draft" && (
                  <>
                    <WorkspaceForm
                      url={`/announcements/${row.id}`}
                      method="PATCH"
                      fields={[title, body]}
                      initial={{ title: row.title ?? "", body: row.body ?? "" }}
                      fixed={{ expected_version: row.version }}
                      label="Lưu thông báo"
                      onSaved={result.reload}
                    />
                    <WorkspaceForm
                      url={`/announcements/${row.id}/publish`}
                      fixed={{ expected_version: row.version }}
                      label="Đăng thông báo"
                      onSaved={result.reload}
                    />
                  </>
                )}
              {tab === "progress" && writable && (
                <WorkspaceForm
                  url={`/progress-records/${row.id}`}
                  method="PATCH"
                  fields={[
                    {
                      name: "value",
                      label: "Tiến độ (%)",
                      type: "number",
                      min: 0,
                      max: 100,
                      required: true,
                    },
                    { name: "note", label: "Ghi chú", type: "textarea" },
                  ]}
                  initial={{ value: row.value ?? 0, note: row.note ?? "" }}
                  fixed={{ expected_version: row.version }}
                  label="Cập nhật tiến độ"
                  onSaved={result.reload}
                />
              )}
              {tab === "fees" && (
                <>
                  <p>
                    {new Intl.NumberFormat("vi-VN", {
                      style: "currency",
                      currency: "VND",
                    }).format(row.amount_minor ?? 0)}
                  </p>
                  <p>{feeLabel(row)}</p>
                  <p className="muted">{row.disclaimer}</p>
                  {writable && row.status === "declared" && (
                    <>
                      {row.tutor_payment_mark !== "marked_paid" && <WorkspaceForm
                        url={`/fee-records/${row.id}`}
                        method="PATCH"
                        fields={[
                          {
                            name: "amount_minor",
                            label: "Số tiền VND",
                            type: "number",
                            required: true,
                            min: 0,
                          },
                          reason,
                        ]}
                        initial={{ amount_minor: row.amount_minor ?? 0 }}
                        fixed={{ expected_version: row.version }}
                        label="Sửa khai báo"
                        onSaved={result.reload}
                      />}
                      <div className="form-grid">
                        {["mark-paid", "mark-unpaid", "void"].filter((action) =>
                          action === "void" || ((row.amount_minor ?? 0) > 0 &&
                            row.tutor_payment_mark !== (action === "mark-paid" ? "marked_paid" : "marked_unpaid")),
                        ).map((action) => (
                          <WorkspaceForm
                            key={action}
                            url={`/fee-records/${row.id}/${action}`}
                            fields={action === "void" || (action === "mark-unpaid" && row.tutor_payment_mark === "marked_paid") ? [reason] : []}
                            fixed={{ expected_version: row.version }}
                            label={
                              {
                                "mark-paid": "Đánh dấu đã trả",
                                "mark-unpaid": "Đánh dấu chưa trả",
                                void: "Hủy khai báo",
                              }[action]!
                            }
                            onSaved={result.reload}
                          />
                        ))}
                      </div>
                    </>
                  )}
                </>
              )}
            </Card>
          ))}
          <div className="button-row">
            <Button
              variant="outline"
              disabled={page === 1}
              onClick={() => setPage(page - 1)}
            >
              Trước
            </Button>
            <span>Trang {page}</span>
            <Button
              variant="outline"
              disabled={
                page >=
                (result.data.rows.meta?.last_page ??
                  Math.max(
                    1,
                    Math.ceil(
                      (result.data.rows.meta?.total ?? 0) /
                        (result.data.rows.meta?.per_page ?? 20),
                    ),
                  ))
              }
              onClick={() => setPage(page + 1)}
            >
              Sau
            </Button>
          </div>
        </>
      )}
    </section>
  );
}
