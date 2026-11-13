"use client";

import { WorkspaceForm } from "@/components/programs/workspace-form";
import { Card } from "@/components/shared/ui";
import {
  type WorkspaceRow
} from "@/lib/data/workspace";
import "@/styles/programs.css";

function ActivityProgress({ title, done, total, explanation }: { title: string; done: number; total: number | null; explanation: string }) {
  const percent = total && total > 0 ? Math.round(done / total * 100) : null;
  return <Card className="activity-progress-card">
    <div className="activity-progress-heading"><div><h2>{title}</h2><p>{explanation}</p></div>{percent !== null ? <strong>{percent}%</strong> : null}</div>
    {percent !== null ? <progress value={percent} max={100} aria-label={`${title}: ${percent}%`} /> : null}
    <p className="activity-progress-count"><strong>{done}</strong>{total !== null ? ` / ${total}` : ""} {title === "Buổi học" ? "buổi đã hoàn thành" : "bài đã được gia sư duyệt"}</p>
    {total === null ? <small>Chưa có tổng số buổi thống nhất nên chưa thể tính phần trăm.</small> : null}
  </Card>;
}

export function ProgressDashboard({ id, program, tasks, sessions, notes, canWrite, onSaved }: { id: number; program: { title: string; planned_session_count: number | null }; tasks: WorkspaceRow[]; sessions: WorkspaceRow[]; notes: WorkspaceRow[]; canWrite: boolean; onSaved: () => void }) {
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
