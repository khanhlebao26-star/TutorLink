"use client";

import Link from "next/link";
import type { Overview, Program } from "@/lib/data/collaboration";
import { deliveryLabels, formatListingPrice } from "@/lib/data/marketplace";
import { Badge, Card } from "./ui";

function OverviewIcon({ name }: { name: "book" | "calendar" | "task" | "chart" | "goal" }) {
  const paths = {
    book: <><path d="M12 5C8 3 5 3 3 4v15c3-1 6-1 9 1 3-2 6-2 9-1V4c-2-1-5-1-9 1Z" /><path d="M12 5v15" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="3" /><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2" /></>,
    task: <><rect x="5" y="4" width="14" height="18" rx="3" /><path d="M9 3h6v4H9zM9 13l2 2 4-4m-6 7h6" /></>,
    chart: <><path d="M4 4v16h16M8 15l4-4 3 2 5-6m-5 0h5v5" /></>,
    goal: <><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /><path d="m12 12 8-8m-4 0h4v4" /></>,
  };
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>;
}

const labels: Record<Program["status"], string> = { draft: "Bản nháp", active: "Đang hoạt động", paused: "Tạm dừng", completed: "Đã hoàn thành", cancelled: "Đã hủy" };

export function ProgramOverview({ program: p, overview: o }: { program: Program; overview: Overview | null }) {
  const totalTasks = o ? o.task_counts.todo + o.task_counts.in_progress + o.task_counts.submitted + o.task_counts.completed : 0;
  const nextSession = o?.next_session;
  const progress = o?.latest_progress;
  return (
    <div className="program-overview">
      <header className="program-overview-hero">
        <span className="program-overview-hero-icon"><OverviewIcon name="book" /></span>
        <div>
          <Badge tone={p.status === "active" || p.status === "completed" ? "success" : "brand"}>{labels[p.status]}</Badge>
          <h1>{p.title}</h1>
          <p>Không gian học tập của bạn · Cùng theo dõi từng bước tiến bộ.</p>
        </div>
        {p.allowed_actions.includes("edit") && <Link className="button button-outline" href={`/programs/${p.id}/edit`}>Chỉnh sửa & gửi lời mời</Link>}
      </header>

      {!o && <div className="program-overview-draft"><OverviewIcon name="book" /><p>Workspace sẽ mở sau khi học viên chấp nhận lời mời.</p></div>}
      <div className="program-overview-stats">
        <Link className="program-overview-stat" href={`/programs/${p.id}/tasks`}>
          <span className="program-overview-icon is-blue"><OverviewIcon name="task" /></span>
          <div><span>Bài tập hoàn thành</span><strong>{o ? o.task_counts.completed : "—"}<small>{o ? ` / ${totalTasks} bài tập` : "Chưa bắt đầu"}</small></strong></div><b aria-hidden="true">↗</b>
        </Link>
        <Link className="program-overview-stat" href={`/programs/${p.id}/tasks`}>
          <span className="program-overview-icon is-pink"><OverviewIcon name="task" /></span>
          <div><span>Bài tập đã nộp</span><strong>{o ? o.task_counts.submitted : "—"}<small>Chờ phản hồi</small></strong></div><b aria-hidden="true">↗</b>
        </Link>
        <Link className="program-overview-stat" href={`/programs/${p.id}/progress`}>
          <span className="program-overview-icon is-purple"><OverviewIcon name="chart" /></span>
          <div><span>Tiến độ mới nhất</span><strong>{progress ? `${progress.value}%` : "—"}<small>{progress ? "Đã ghi nhận" : "Chưa ghi nhận"}</small></strong></div><b aria-hidden="true">↗</b>
        </Link>
      </div>

      <div className="program-overview-grid">
        <div className="program-overview-main">
          <Card className="program-overview-card">
            <h2><span className="program-overview-icon is-pink"><OverviewIcon name="goal" /></span>Mục tiêu chương trình</h2>
            <p className="program-overview-goal">{p.goal || "Chưa cập nhật mục tiêu học tập."}</p>
            <dl className="program-overview-facts">
              <div><dt>Trình độ</dt><dd>{p.level || "Chưa cập nhật"}</dd></div>
              <div><dt>Hình thức học</dt><dd>{deliveryLabels[p.delivery_mode]}</dd></div>
              <div><dt>Số buổi dự kiến</dt><dd>{p.planned_session_count != null ? `${p.planned_session_count} buổi` : "Chưa thống nhất"}</dd></div>
            </dl>
          </Card>
          <Card className="program-overview-card">
            <div className="program-overview-card-heading"><h2><span className="program-overview-icon is-purple"><OverviewIcon name="chart" /></span>Hành trình tiến bộ</h2><Link href={`/programs/${p.id}/progress`}>Xem tiến độ →</Link></div>
            {progress ? <>
              <div className="program-overview-progress-label"><span>Tiến độ được ghi nhận gần nhất</span><strong>{progress.value}%</strong></div>
              <progress className="program-overview-progress" value={progress.value} max={100} aria-label="Tiến độ chương trình" />
              <p className="program-overview-progress-note">{progress.note || "Tiếp tục theo dõi các cập nhật trong tab Tiến độ."}</p>
            </> : <div className="program-overview-empty"><span className="program-overview-icon is-purple"><OverviewIcon name="chart" /></span><div><h3>Hành trình của bạn bắt đầu từ đây</h3><p>Tiến độ sẽ xuất hiện khi tutor cập nhật kết quả học tập của bạn.</p></div></div>}
          </Card>
          <Card className="program-overview-card">
            <div className="program-overview-card-heading"><h2>Thông báo mới nhất</h2><Link href={`/programs/${p.id}/announcements`}>Xem tất cả →</Link></div>
            {o?.latest_announcement ? <div className="program-overview-announcement"><span className="program-overview-announcement-dot" /><div><h3>{o.latest_announcement.title}</h3><p>{o.latest_announcement.body}</p></div></div> : <p className="program-overview-no-announcement">Chưa có thông báo mới. Các cập nhật của chương trình sẽ hiển thị tại đây.</p>}
          </Card>
        </div>
        <aside className="program-overview-aside" aria-label="Lịch học và học phí">
          <Card className="program-overview-card program-overview-next">
            <p className="program-overview-kicker"><OverviewIcon name="calendar" />BUỔI HỌC TIẾP THEO</p>
            {nextSession ? <><h2>{nextSession.title || "Buổi học sắp tới"}</h2><p className="program-overview-session-date">{new Date(nextSession.planned_starts_at).toLocaleString("vi-VN", { timeZone: p.timezone, weekday: "long", day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" })}</p><p className="program-overview-next-note">Múi giờ: {p.timezone}</p></> : <><h2>Chưa có lịch sắp tới</h2><p className="program-overview-next-note">{o?.capabilities.can_create_session ? "Thêm buổi học để học viên theo dõi lịch và chuẩn bị trước khi bắt đầu." : "Lịch sẽ được hiển thị khi tutor tạo buổi học cho chương trình."}</p></>}
            <Link className="button button-primary" href={`/programs/${p.id}/sessions`}>{o?.capabilities.can_create_session && !nextSession ? "Quản lý buổi học" : "Xem lịch buổi học"}<span aria-hidden="true">→</span></Link>
          </Card>
          <Card className="program-overview-card program-overview-plan">
            <h2>Lịch học đã thống nhất</h2>
            <p>{p.schedule_note || "Chưa thống nhất lịch học."}</p>
            <small>Múi giờ: {p.timezone}</small>
            <dl><div><dt>Địa điểm</dt><dd>{p.location_text || (p.delivery_mode === "online" ? "Trực tuyến" : "Chưa thống nhất")}</dd></div>{p.meeting_url && <div><dt>Liên kết buổi học</dt><dd>{p.meeting_url}</dd></div>}</dl>
          </Card>
          <Card className="program-overview-card program-overview-fee">
            <p className="program-overview-kicker">HỌC PHÍ THỎA THUẬN</p>
            <strong>{formatListingPrice(p.agreed_fee_minor, p.currency, p.fee_unit)}</strong>
            <Link href={`/programs/${p.id}/fees`}>Theo dõi học phí <span aria-hidden="true">→</span></Link>
          </Card>
        </aside>
      </div>
    </div>
  );
}
