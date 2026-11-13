"use client";

import { useActor } from "@/components/layout/app-shell";
import { ProgressDashboard } from "@/components/programs/progress/progress-dashboard";
import { SessionCalendar } from "@/components/programs/sessions/session-calendar";
import { TaskCreateDialog } from "@/components/programs/tasks/task-create-dialog";
import { taskDueLabel, taskDueTimestamp, taskListStatus } from "@/components/programs/tasks/task-list-utils";
import { WorkspaceFiles } from "@/components/programs/workspace-files";
import { WorkspaceForm } from "@/components/programs/workspace-form";
import { body, reason, taskFields, title } from "@/components/programs/workspace-form-fields";
import {
  workspaceTabs,
  type WorkspaceTab,
} from "@/components/programs/workspace-navigation";
import { ApiState } from "@/components/shared/api-state";
import { Badge, Button, Card, StatePanel } from "@/components/shared/ui";
import { getProgram } from "@/lib/data/collaboration";
import {
  feeLabel,
  localTimestamp,
  readAllWorkspaceRows,
  workspaceRead,
  type Collection,
  type WorkspaceRow,
} from "@/lib/data/workspace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/styles/programs.css";
import Link from "next/link";
import { useCallback, useState } from "react";

const paths = {
  sessions: "sessions",
  tasks: "tasks",
  progress: "progress-records",
  announcements: "announcements",
  fees: "fee-records",
};

function announcementDate(row: WorkspaceRow) {
  const value = row.published_at ?? row.created_at;
  if (!value) return "";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "" : date.toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" });
}
export function WorkspaceTabPage({
  id,
  tab,
}: {
  id: number;
  tab: Exclude<WorkspaceTab, "overview" | "quizzes">;
}) {
  return <TabContent key={`${id}:${tab}`} id={id} tab={tab} />;
}

function TabContent({
  id,
  tab,
}: {
  id: number;
  tab: Exclude<WorkspaceTab, "overview" | "quizzes">;
}) {
  const actor = useActor();
  const [page, setPage] = useState(1);
  const [taskFilter, setTaskFilter] = useState("all");
  const [taskSort, setTaskSort] = useState("due");
  const announcementState = actor?.role === "tutor" ? "all" : "published";
  const dataPage = tab === "tasks" ? 1 : page;
  const load = useCallback(
    async () => {
      const [program, rows] = await Promise.all([getProgram(id), tab === "tasks"
        ? readAllWorkspaceRows(`/programs/${id}/tasks`).then((data) => ({ data }))
        : workspaceRead<Collection<WorkspaceRow>>(
          `/programs/${id}/${paths[tab]}`,
          tab === "announcements" ? { page: dataPage, state: announcementState } : { page: dataPage },
        )]);
      const activity = tab === "progress" ? await Promise.all([
        readAllWorkspaceRows(`/programs/${id}/tasks`),
        readAllWorkspaceRows(`/programs/${id}/sessions`),
      ]) : null;
      return { program, rows, activity };
    },
    [id, tab, dataPage, announcementState],
  );
  const result = useAsyncData(load);
  const sessionRows = tab === "sessions" ? result.data?.rows.data ?? [] : [];
  const owner = !!result.data?.program.allowed_actions.length;
  const active = result.data?.program.status === "active";
  const paused = result.data?.program.status === "paused";
  const writable = owner && (active || paused);
  const base = `/programs/${id}/${paths[tab]}`;
  const allTasks = tab === "tasks" ? result.data?.rows.data ?? [] : [];
  const currentTasks = allTasks.filter((row) => row.status !== "cancelled");
  const submittedTasks = currentTasks.filter((row) => row.status === "submitted").length;
  const completedTasks = currentTasks.filter((row) => row.status === "completed").length;
  const ongoingTasks = currentTasks.filter((row) => ["todo", "in_progress"].includes(row.status ?? "")).length;
  const filteredTasks = currentTasks.filter((row) => {
    if (taskFilter === "grading") return row.status === "submitted";
    if (taskFilter === "not-submitted") return ["todo", "in_progress"].includes(row.status ?? "");
    if (taskFilter === "completed") return row.status === "completed";
    return true;
  }).sort((a, b) => taskSort === "newest"
    ? (new Date(b.created_at ?? 0).getTime() - new Date(a.created_at ?? 0).getTime())
    : taskDueTimestamp(a) - taskDueTimestamp(b));
  const taskPageSize = 5;
  const taskLastPage = Math.max(1, Math.ceil(filteredTasks.length / taskPageSize));
  const visibleTasks = filteredTasks.slice((page - 1) * taskPageSize, page * taskPageSize);
  const serverRows = result.data?.rows as Collection<WorkspaceRow> | undefined;
  return (
    <section className={`content-stack workspace-page${tab === "sessions" ? " session-workspace-page" : ""}`}>
      <h1>{tab === "tasks" ? "Bài tập & Nhiệm vụ" : workspaceTabs[tab]}</h1>
      <ApiState {...result} />
      {result.data && (
        <>
          <p className={tab === "sessions" ? "session-workspace-subtitle" : "muted"}>
            {tab === "sessions" ? <>Lịch trình học tập cho <strong>{result.data.program.title}</strong>. Theo dõi thời gian, hình thức và trạng thái từng buổi.</> : tab === "tasks" ? <>Chương trình: <strong>{result.data.program.title}</strong> · <Badge tone={active ? "success" : "warning"}>{active ? "Đang hoạt động" : result.data.program.status === "paused" ? "Tạm dừng" : result.data.program.status}</Badge></> : tab === "progress" ? <>Tiến độ của <strong>{result.data.program.title}</strong> được tính tự động từ buổi học và bài tập.</> : tab === "announcements" ? <>Cập nhật dành cho học viên trong chương trình <strong>{result.data.program.title}</strong>.</> : `${result.data.program.title} · ${result.data.program.status}`}
          </p>
          {owner && tab === "tasks" && active ? <TaskCreateDialog id={id} programTitle={result.data.program.title} customerId={result.data.program.customer_id} onSaved={result.reload} /> : null}
          {tab === "tasks" ? <>
            <div className="workspace-task-toolbar">
              <div className="workspace-task-filters" role="tablist" aria-label="Lọc bài tập">
                {[["all", "Tất cả", currentTasks.length], ["grading", "Chờ chấm điểm", submittedTasks], ["not-submitted", "Chưa nộp", ongoingTasks], ["completed", "Hoàn thành", completedTasks]].map(([key, label, count]) => <button key={key} type="button" role="tab" aria-selected={taskFilter === key} className={taskFilter === key ? "is-active" : ""} onClick={() => { setTaskFilter(String(key)); setPage(1); }}>{label}<span>{count}</span></button>)}
              </div>
              <label className="workspace-task-sort">Sắp xếp theo
                <select value={taskSort} onChange={(event) => { setTaskSort(event.target.value); setPage(1); }}>
                  <option value="due">Hạn nộp gần nhất</option>
                  <option value="newest">Mới giao gần đây</option>
                </select>
              </label>
            </div>
          </> : null}
          {owner && tab !== "sessions" && tab !== "progress" && tab !== "tasks" && (active || (paused && tab === "announcements")) && (
            <Card className={tab === "announcements" ? "workspace-announcement-create" : undefined}>
              <details>
                <summary>{tab === "announcements" ? "＋ Soạn thông báo" : "Tạo mới"}</summary>
                {tab === "announcements" ? <p className="muted workspace-announcement-hint">Thông báo được lưu thành bản nháp trước. Bạn có thể xem lại rồi đăng cho học viên.</p> : null}
                <WorkspaceForm
                  url={base}
                  onSaved={result.reload}
                  label={tab === "announcements" ? "Lưu bản nháp" : "Lưu"}
                  fields={
                    tab === "announcements"
                      ? [{ ...title, label: "Tiêu đề thông báo" }, { ...body, label: "Nội dung thông báo", hint: "Viết ngắn gọn điều học viên cần biết." }]
                      : [
                        { name: "period", label: "Tháng", type: "month", required: true },
                        { name: "amount_minor", label: "Số tiền VND", type: "number", min: 0, required: true },
                      ]
                  }
                  fixed={tab === "fees" ? { currency: "VND" } : {}}
                />
              </details>
            </Card>
          )}
          {tab === "sessions" ? <SessionCalendar id={id} rows={sessionRows} active={active} writable={writable} canCreate={owner && active} onSaved={result.reload} /> : tab === "progress" ? <ProgressDashboard id={id} program={result.data.program} tasks={result.data.activity?.[0] ?? []} sessions={result.data.activity?.[1] ?? []} notes={result.data.rows.data} canWrite={owner && (active || paused)} onSaved={result.reload} /> : !(tab === "tasks" ? currentTasks.length : result.data.rows.data.length) ? (
            <StatePanel kind="empty" message={tab === "tasks" ? owner ? "Chưa giao bài tập nào. Hãy tạo bài tập để học viên biết cần làm gì và khi nào cần nộp." : "Bạn chưa có bài tập nào cần làm. Bài tập gia sư giao sẽ xuất hiện tại đây." : tab === "announcements" ? actor?.role === "tutor" ? "Chưa có thông báo nào. Soạn bản nháp rồi đăng để học viên nhận được cập nhật." : "Chưa có thông báo từ gia sư. Các cập nhật của chương trình sẽ xuất hiện tại đây." : "Chưa có dữ liệu trong tab này."} />
          ) : tab === "tasks" && !visibleTasks.length ? <StatePanel kind="empty" message="Không có bài tập nào trong mục này." /> : (tab === "tasks" ? visibleTasks : result.data.rows.data).map((row) => (
            tab === "tasks" ? <Card key={`${row.id}-${row.version}`} className="workspace-task-list-card">
              <div className="workspace-task-list-main">
                <div className="workspace-task-list-copy">
                  <div className="workspace-task-list-meta"><Badge tone={taskListStatus(row.status).tone}>{taskListStatus(row.status).label.toUpperCase()}</Badge><span>{row.status === "completed" ? "Đã hoàn thành" : taskDueLabel(row.due_at)}</span></div>
                  <h2>{row.title ?? "Bài tập"}</h2>
                  {row.description ? <p>{row.description}</p> : null}
                  {row.file_ids?.length ? <small>{row.file_ids.length} tệp tài liệu đính kèm</small> : null}
                </div>
                <Link className={`button ${row.status === "submitted" && owner ? "button-primary" : "button-outline"}`} href={`/programs/${id}/tasks/${row.id}`}>
                  {owner ? row.status === "submitted" ? "Chấm bài" : row.status === "completed" ? "Xem bài nộp" : "Xem bài tập" : "Mở bài tập"}
                </Link>
              </div>
              {writable && !["completed", "cancelled"].includes(row.status ?? "") ? <details className="workspace-task-list-options">
                <summary>Tuỳ chọn gia sư</summary>
                <WorkspaceForm url={`/tasks/${row.id}`} method="PATCH" fields={taskFields} initial={{ title: row.title ?? "", description: row.description ?? "", due_at: localTimestamp(row.due_at ?? undefined) }} fixed={{ expected_version: row.version, file_ids: row.file_ids ?? [] }} fileUpload fileUploadLabel="Thêm đề hoặc tài liệu hướng dẫn" label="Lưu bài tập" onSaved={result.reload} />
                <WorkspaceForm url={`/tasks/${row.id}/cancel`} fixed={{ expected_version: row.version }} label="Hủy bài tập" onSaved={result.reload} />
              </details> : null}
              {actor?.role === "customer" && row.status === "todo" ? <WorkspaceForm url={`/tasks/${row.id}/start`} fixed={{ expected_version: row.version }} label="Bắt đầu làm bài" onSaved={result.reload} /> : null}
            </Card> : <Card key={`${row.id}-${row.version}`} className={tab === "announcements" ? "workspace-announcement-card" : undefined}>
              <Badge tone={tab === "announcements" ? row.state === "draft" ? "warning" : "success" : undefined}>{tab === "announcements" ? row.state === "draft" ? "Bản nháp" : "Đã đăng" : row.status ?? row.state ?? "Đã ghi nhận"}</Badge>
              <h2>
                {row.title ??
                  (tab === "fees" ? row.period : `Tiến độ ${row.value}%`)}
              </h2>
              <p style={{ whiteSpace: "pre-wrap" }}>
                {row.body ?? row.description ?? row.note}
              </p>
              {tab === "announcements" ? <>
                {announcementDate(row) ? <p className="workspace-announcement-date">{row.state === "draft" ? "Tạo lúc" : "Đăng lúc"} {announcementDate(row)}</p> : null}
                {row.files?.some((file) => file.complete) ? <div className="workspace-announcement-files"><strong>Tệp đính kèm</strong><WorkspaceFiles ids={row.files.filter((file) => file.complete).map((file) => file.id)} /></div> : null}
              </> : null}
              {tab === "announcements" &&
                writable &&
                row.author_id === actor?.id &&
                row.state === "draft" && (
                  <div className="workspace-announcement-actions">
                    <details>
                      <summary>Chỉnh sửa bản nháp</summary>
                      <WorkspaceForm
                        url={`/announcements/${row.id}`}
                        method="PATCH"
                        fields={[{ ...title, label: "Tiêu đề thông báo" }, { ...body, label: "Nội dung thông báo", hint: "Viết ngắn gọn điều học viên cần biết." }]}
                        initial={{ title: row.title ?? "", body: row.body ?? "" }}
                        fixed={{ expected_version: row.version }}
                        label="Lưu thay đổi"
                        onSaved={result.reload}
                      />
                    </details>
                    <WorkspaceForm
                      url={`/announcements/${row.id}/publish`}
                      fixed={{ expected_version: row.version }}
                      label="Đăng cho học viên"
                      onSaved={result.reload}
                    />
                  </div>
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
          {tab === "tasks" ? <p className="workspace-task-result-count">Hiển thị {(page - 1) * taskPageSize + (visibleTasks.length ? 1 : 0)}–{Math.min(page * taskPageSize, filteredTasks.length)} trong {filteredTasks.length} bài tập</p> : null}
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
                page >= (tab === "tasks" ? taskLastPage :
                  (serverRows?.meta?.last_page ??
                    Math.max(
                      1,
                      Math.ceil(
                        (serverRows?.meta?.total ?? 0) /
                        (serverRows?.meta?.per_page ?? 20),
                      ),
                    )))
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
