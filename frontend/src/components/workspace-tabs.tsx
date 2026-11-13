"use client";
import Link from "next/link";
import { useCallback, useState, type MouseEvent } from "react";
import { useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Button, Card, StatePanel } from "./ui";
import { WorkspaceForm, type FormField } from "./workspace-form";
import { WorkspaceFiles } from "./workspace-files";
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
  { name: "meeting_url", label: "Đường dẫn tham gia buổi học", hint: "Dán đường dẫn từ Google Meet, Zoom hoặc ứng dụng bạn dùng. Có thể để trống nếu chưa có." },
];
const taskFields: FormField[] = [
  { ...title, label: "Tên bài tập", hint: "Ví dụ: Ôn tập từ vựng tuần 1." },
  { name: "description", label: "Hướng dẫn làm bài", type: "textarea", hint: "Mô tả rõ học viên cần làm gì và cách nộp bài." },
  {
    name: "due_at",
    label: "Hạn nộp",
    type: "datetime-local",
    hint: "Chọn ngày và giờ cần nộp. Có thể để trống nếu chưa có hạn.",
  },
];
const paths = {
  sessions: "sessions",
  tasks: "tasks",
  progress: "progress-records",
  announcements: "announcements",
  fees: "fee-records",
};

async function readAllWorkspaceRows(path: string) {
  const first = await workspaceRead<Collection<WorkspaceRow>>(path, { page: 1, per_page: 50 });
  const lastPage = first.meta?.last_page ?? 1;
  if (lastPage <= 1) return first.data;
  const rest = await Promise.all(Array.from({ length: lastPage - 1 }, (_, index) =>
    workspaceRead<Collection<WorkspaceRow>>(path, { page: index + 2, per_page: 50 }),
  ));
  return [...first.data, ...rest.flatMap((page) => page.data)];
}

function ActivityProgress({ title, done, total, explanation }: { title: string; done: number; total: number | null; explanation: string }) {
  const percent = total && total > 0 ? Math.round(done / total * 100) : null;
  return <Card className="activity-progress-card">
    <div className="activity-progress-heading"><div><h2>{title}</h2><p>{explanation}</p></div>{percent !== null ? <strong>{percent}%</strong> : null}</div>
    {percent !== null ? <progress value={percent} max={100} aria-label={`${title}: ${percent}%`} /> : null}
    <p className="activity-progress-count"><strong>{done}</strong>{total !== null ? ` / ${total}` : ""} {title === "Buổi học" ? "buổi đã hoàn thành" : "bài đã được gia sư duyệt"}</p>
    {total === null ? <small>Chưa có tổng số buổi thống nhất nên chưa thể tính phần trăm.</small> : null}
  </Card>;
}

function ProgressDashboard({ id, program, tasks, sessions, notes, canWrite, onSaved }: { id: number; program: { title: string; planned_session_count: number | null }; tasks: WorkspaceRow[]; sessions: WorkspaceRow[]; notes: WorkspaceRow[]; canWrite: boolean; onSaved: () => void }) {
  const liveTasks = tasks.filter((task) => task.status !== "cancelled");
  const completedTasks = liveTasks.filter((task) => task.status === "completed").length;
  const completedSessions = sessions.filter((session) => session.status === "completed").length;
  const tutorNotes = notes.filter((row) => row.note?.trim());
  const taskPercent = liveTasks.length ? Math.round(completedTasks / liveTasks.length * 100) : null;
  const sessionPercent = program.planned_session_count ? Math.round(completedSessions / program.planned_session_count * 100) : null;
  const availablePercents = [taskPercent, sessionPercent].filter((value): value is number => value !== null);
  const noteSnapshot = availablePercents.length ? Math.round(availablePercents.reduce((sum, value) => sum + value, 0) / availablePercents.length) : 0;
  return <div className="activity-progress-page">
    <div className="activity-progress-intro"><span>TIẾN ĐỘ TỰ ĐỘNG</span><h2>{program.title}</h2><p>Tiến độ được cập nhật khi buổi học hoàn thành hoặc gia sư duyệt bài tập. Các chỉ số được hiển thị riêng, không gộp thành một điểm chung.</p></div>
    <div className="activity-progress-grid">
      <ActivityProgress title="Buổi học" done={completedSessions} total={program.planned_session_count} explanation="Tính theo số buổi đã hoàn thành trên tổng số buổi đã thống nhất." />
      <ActivityProgress title="Bài tập" done={completedTasks} total={liveTasks.length} explanation="Tính theo số bài gia sư đã duyệt trên tổng số bài tập đang áp dụng." />
    </div>
    <Card className="activity-progress-note-card">
      <div><h2>Nhận xét của gia sư</h2><p>Nhận xét giúp bạn hiểu rõ hơn về kỹ năng và mục tiêu học tập.</p></div>
      {canWrite ? <WorkspaceForm url={`/programs/${id}/progress-records`} fields={[{ name: "note", label: "Nhận xét", type: "textarea", required: true, hint: "Ghi chú này không làm thay đổi các chỉ số tiến độ tự động." }]} fixed={{ metric_name: "progress_percent", value: noteSnapshot }} label="Gửi nhận xét" onSaved={onSaved} /> : null}
      {tutorNotes.length ? tutorNotes.map((row) => <article key={row.id}><p>{row.note}</p><small>Nhận xét của gia sư</small></article>) : <p className="muted">Chưa có nhận xét từ gia sư.</p>}
    </Card>
  </div>;
}

function TaskCreateDialog({ id, programTitle, customerId, onSaved }: { id: number; programTitle: string; customerId: number; onSaved: () => void }) {
  const [open, setOpen] = useState(false);
  return <>
    <Card className="workspace-task-create">
      <div className="workspace-task-create-row"><div><h2>Giao bài tập mới</h2><p>Thêm hướng dẫn và tài liệu để học viên bắt đầu làm bài.</p></div><Button onClick={() => setOpen(true)}>＋ Giao bài tập mới</Button></div>
    </Card>
    {open ? <div className="session-detail-backdrop workspace-task-dialog-backdrop" onClick={() => setOpen(false)}>
      <section className="session-detail-dialog workspace-task-dialog" role="dialog" aria-modal="true" aria-labelledby={`task-create-title-${id}`} onClick={(event) => event.stopPropagation()}>
        <button className="session-detail-close" type="button" onClick={() => setOpen(false)} aria-label="Đóng form giao bài tập">×</button>
        <p className="session-detail-eyebrow">BÀI TẬP · {programTitle}</p>
        <h2 id={`task-create-title-${id}`}>Giao bài tập mới</h2>
        <p className="workspace-task-dialog-hint">Điền tên bài, hướng dẫn và hạn nộp nếu có. Học viên sẽ xem được bài và tài liệu trong chương trình.</p>
        <WorkspaceForm
          url={`/programs/${id}/tasks`}
          fields={taskFields}
          fixed={{ assigned_to: customerId }}
          fileUpload
          fileUploadLabel="Tải đề hoặc tài liệu hướng dẫn"
          label="Giao bài tập"
          onSaved={() => { onSaved(); setOpen(false); }}
        />
      </section>
    </div> : null}
  </>;
}

function taskStatus(status?: string | null) {
  switch (status) {
    case "todo": return { label: "Chưa bắt đầu", tone: "brand" as const };
    case "in_progress": return { label: "Đang làm", tone: "warning" as const };
    case "submitted": return { label: "Đã nộp · chờ gia sư xem", tone: "success" as const };
    case "completed": return { label: "Đã hoàn thành", tone: "success" as const };
    case "cancelled": return { label: "Đã hủy", tone: "danger" as const };
    default: return { label: "Bài tập", tone: "brand" as const };
  }
}

function taskDueLabel(value?: string | null) {
  if (!value) return "Chưa đặt hạn nộp";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Chưa đặt hạn nộp" : `Hạn nộp ${date.toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" })}`;
}

function taskListStatus(status?: string | null) {
  switch (status) {
    case "submitted": return { label: "Chờ gia sư chấm", tone: "brand" as const };
    case "completed": return { label: "Đã hoàn thành", tone: "success" as const };
    case "in_progress": return { label: "Đang làm", tone: "warning" as const };
    case "cancelled": return { label: "Đã hủy", tone: "danger" as const };
    default: return { label: "Chưa nộp bài", tone: "brand" as const };
  }
}

function taskDueTimestamp(row: WorkspaceRow) {
  if (!row.due_at) return Number.POSITIVE_INFINITY;
  const timestamp = new Date(row.due_at).getTime();
  return Number.isNaN(timestamp) ? Number.POSITIVE_INFINITY : timestamp;
}

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

const calendarHours = Array.from({ length: 17 }, (_, index) => index + 6);
const calendarHourHeight = 56;
const weekdayLabels = ["CN", "T2", "T3", "T4", "T5", "T6", "T7"];

function weekStartOf(date: Date) {
  const start = new Date(date);
  start.setDate(start.getDate() - start.getDay());
  start.setHours(0, 0, 0, 0);
  return start;
}

function sameCalendarDay(left: Date, right: Date) {
  return left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate();
}

function SessionCalendar({
  id,
  rows,
  active,
  writable,
  canCreate,
  onSaved,
}: {
  id: number;
  rows: WorkspaceRow[];
  active: boolean;
  writable: boolean;
  canCreate: boolean;
  onSaved: () => void;
}) {
  const [weekStart, setWeekStart] = useState(() => weekStartOf(new Date()));
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [newSlot, setNewSlot] = useState<{ start: Date; end: Date } | null>(null);
  const days = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(weekStart);
    date.setDate(date.getDate() + index);
    return date;
  });
  const selected = rows.find((row) => row.id === selectedId) ?? null;
  const weekEnd = days[6];
  const weekTitle = weekStart.getMonth() === weekEnd.getMonth()
    ? weekStart.toLocaleDateString("vi-VN", { month: "long", year: "numeric" })
    : `${weekStart.toLocaleDateString("vi-VN", { month: "short" })} – ${weekEnd.toLocaleDateString("vi-VN", { month: "short", year: "numeric" })}`;
  const notScheduled = rows.filter((row) => !row.planned_starts_at);

  function moveWeek(amount: number) {
    setWeekStart((current) => {
      const next = new Date(current);
      next.setDate(next.getDate() + amount * 7);
      return next;
    });
  }

  function openNewSession(day: Date, hour: number, event: MouseEvent<HTMLButtonElement>) {
    if (!canCreate) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    const pointerOffset = Math.max(0, Math.min(bounds.height, event.clientY - bounds.top));
    const minutes = Math.min(30, Math.round(pointerOffset / bounds.height * 2) * 30);
    const start = new Date(day);
    start.setHours(hour, minutes, 0, 0);
    const end = new Date(start.getTime() + 60 * 60 * 1000);
    setNewSlot({ start, end });
  }

  return <div className="session-calendar-wrap">
    <div className="session-calendar-toolbar">
      <div className="session-calendar-controls">
        <button type="button" className="session-calendar-today" onClick={() => setWeekStart(weekStartOf(new Date()))}>Hôm nay</button>
        <button type="button" className="session-calendar-step" aria-label="Tuần trước" onClick={() => moveWeek(-1)}>‹</button>
        <button type="button" className="session-calendar-step" aria-label="Tuần sau" onClick={() => moveWeek(1)}>›</button>
        <h2>{weekTitle.charAt(0).toLocaleUpperCase("vi-VN") + weekTitle.slice(1)}</h2>
      </div>
      <span className="session-calendar-view-label">Tuần</span>
    </div>
    <div className="session-calendar-scroll">
      <div className="session-calendar-grid" style={{ gridTemplateColumns: "58px repeat(7, minmax(96px, 1fr))" }}>
        <div className="session-calendar-day-head session-calendar-time-spacer" />
        {days.map((day, index) => <div key={day.toISOString()} className={`session-calendar-day-head${sameCalendarDay(day, new Date()) ? " is-today" : ""}`}>
          <span>{weekdayLabels[index]}</span><strong>{day.getDate()}</strong>
        </div>)}
        <div className="session-calendar-time-rail" style={{ height: calendarHours.length * calendarHourHeight }}>
          {calendarHours.map((hour) => <span key={hour} style={{ height: calendarHourHeight }}>{`${String(hour).padStart(2, "0")}:00`}</span>)}
        </div>
        {days.map((day) => {
          const dayEvents = rows.flatMap((row) => {
            if (!row.planned_starts_at) return [];
            const start = new Date(row.planned_starts_at);
            if (Number.isNaN(start.getTime()) || !sameCalendarDay(start, day)) return [];
            const parsedEnd = row.planned_ends_at ? new Date(row.planned_ends_at) : null;
            const end = parsedEnd && !Number.isNaN(parsedEnd.getTime()) ? parsedEnd : new Date(start.getTime() + 60 * 60 * 1000);
            const startOffset = (start.getHours() - 6) * 60 + start.getMinutes();
            const endOffset = (end.getHours() - 6) * 60 + end.getMinutes();
            const top = Math.max(0, startOffset / 60 * calendarHourHeight);
            const bottom = Math.min(calendarHours.length * calendarHourHeight, endOffset / 60 * calendarHourHeight);
            if (bottom <= 0 || top >= calendarHours.length * calendarHourHeight) return [];
            return [{ row, start, end, top: Math.max(0, top), height: Math.max(36, bottom - Math.max(0, top)) }];
          });
          return <div key={day.toISOString()} className={`session-calendar-day-column${sameCalendarDay(day, new Date()) ? " is-today" : ""}`} style={{ height: calendarHours.length * calendarHourHeight }}>
            {canCreate ? calendarHours.map((hour) => <button key={`slot-${hour}`} type="button" className="session-calendar-slot" style={{ top: (hour - 6) * calendarHourHeight, height: calendarHourHeight }} aria-label={`Lên lịch vào ${day.toLocaleDateString("vi-VN", { weekday: "long", day: "numeric", month: "long" })} lúc ${String(hour).padStart(2, "0")}:00`} onClick={(event) => openNewSession(day, hour, event)} />) : null}
            {calendarHours.map((hour) => <div key={hour} className="session-calendar-hour-line" style={{ top: (hour - 6) * calendarHourHeight }} />)}
            {dayEvents.map(({ row, start, end, top, height }) => <button key={row.id} type="button" className={`session-calendar-event is-${row.status ?? "scheduled"}`} style={{ top, height }} onClick={(event) => { event.stopPropagation(); setSelectedId(row.id); }} aria-label={`Mở thông tin ${row.title || "buổi học"}, ${start.toLocaleString("vi-VN")}`}>
              <strong>{start.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })} {row.title || "Buổi học"}</strong>
              <span>{end.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })}</span>
            </button>)}
          </div>;
        })}
      </div>
    </div>
    {notScheduled.length ? <div className="session-calendar-unscheduled"><strong>Chưa xếp lịch</strong>{notScheduled.map((row) => <button key={row.id} type="button" onClick={() => setSelectedId(row.id)}>{row.title || `Buổi học #${row.id}`} <span>Chọn để xem chi tiết →</span></button>)}</div> : null}
    {selected ? <SessionDetailDialog row={selected} active={active} writable={writable} onSaved={onSaved} onClose={() => setSelectedId(null)} /> : null}
    {newSlot ? <SessionCreateDialog id={id} start={newSlot.start} end={newSlot.end} onSaved={() => { onSaved(); setNewSlot(null); }} onClose={() => setNewSlot(null)} /> : null}
  </div>;
}

function SessionCreateDialog({
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

function SessionDetailDialog({
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
