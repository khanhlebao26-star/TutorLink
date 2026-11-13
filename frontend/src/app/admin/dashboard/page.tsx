"use client";

import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { Button, ErrorActions, StatePanel } from "@/components/ui";
import { describeApiError } from "@/lib/api/client";
import { getAdminDashboard, type DashboardQueues } from "@/lib/data/admin-dashboard";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "./dashboard.css";

const number = (value: number) => new Intl.NumberFormat("vi-VN").format(value);
const profileStatuses = { submitted: "Đang chờ duyệt", changes_requested: "Cần bổ sung", approved: "Đã phê duyệt", rejected: "Đã từ chối", suspended: "Đã tạm ngưng" };
const reportStatuses = { open: "Chờ xử lý", resolved: "Đã giải quyết", dismissed: "Đã đóng" };

function QueueSummary({ title, queues, statuses, href }: { title: string; queues: DashboardQueues; statuses: Record<string, string>; href: string }) {
  return <section className="admin-dashboard-panel">
    <header><h2>{title}</h2><Link href={href}>Mở quản lý <span aria-hidden="true">→</span></Link></header>
    <div className="admin-dashboard-table-wrap"><table>
      <caption className="landing-sr-only">Thống kê {title.toLowerCase()} theo phân công</caption>
      <thead><tr><th scope="col">Trạng thái</th>{queues.unassigned && <th scope="col">Chưa phân công</th>}{queues.mine && <th scope="col">Của tôi</th>}</tr></thead>
      <tbody>{Object.entries(statuses).map(([key, label]) => <tr key={key}><th scope="row">{label}</th>{queues.unassigned && <td>{number(queues.unassigned[key] ?? 0)}</td>}{queues.mine && <td>{number(queues.mine[key] ?? 0)}</td>}</tr>)}</tbody>
    </table></div>
  </section>;
}

export default function AdminDashboardPage() {
  return <AppShell role="admin" title=""><DashboardContent /></AppShell>;
}

function DashboardContent() {
  const result = useAsyncData(getAdminDashboard);
  const data = result.data;
  const profiles = data?.resources.tutor_profiles;
  const reports = data?.resources.reports;
  const metrics = [
    { title: "Hồ sơ chờ phân công", value: profiles?.unassigned ? profiles.unassigned.submitted ?? 0 : undefined, href: "/admin", icon: "▤", tone: "pink" },
    { title: "Hồ sơ tôi chờ duyệt", value: profiles?.mine ? profiles.mine.submitted ?? 0 : undefined, href: "/admin", icon: "✓", tone: "purple" },
    { title: "Báo cáo chờ phân công", value: reports?.unassigned ? reports.unassigned.open ?? 0 : undefined, href: "/admin/reports", icon: "⚑", tone: "orange" },
    { title: "Báo cáo tôi cần xử lý", value: reports?.mine ? reports.mine.open ?? 0 : undefined, href: "/admin/reports", icon: "≡", tone: "blue" },
  ].filter(metric => metric.value !== undefined);

  return <section className="admin-dashboard">
    <header className="admin-dashboard-heading"><div><p className="eyebrow">QUẢN TRỊ · TỔNG QUAN</p><h1>Dashboard quản trị</h1><p>Theo dõi hồ sơ gia sư và các báo cáo cần xử lý.</p></div><Button variant="secondary" disabled={result.loading} onClick={result.reload}>↻ Làm mới</Button></header>
    {result.loading ? <div className="admin-dashboard-skeleton" role="status" aria-label="Đang tải dashboard"><div>{[0, 1, 2, 3].map(i => <span key={i} />)}</div><span /></div> : result.error ? <StatePanel kind="error" message={describeApiError(result.error)} action={<ErrorActions onRetry={result.reload} />} /> : data ? <>
      <p className="admin-dashboard-updated">Cập nhật lúc {new Intl.DateTimeFormat("vi-VN", { hour: "2-digit", minute: "2-digit", second: "2-digit" }).format(new Date(data.updatedAt))} · Số liệu trong phạm vi quyền và phân công của bạn.</p>
      {metrics.length > 0 && <div className="admin-dashboard-metrics">{metrics.map(metric => <Link className="admin-dashboard-metric" href={metric.href} key={metric.title}><span className={`admin-dashboard-icon is-${metric.tone}`} aria-hidden="true">{metric.icon}</span><div><span>{metric.title}</span><strong>{number(metric.value!)}</strong></div><span className="admin-dashboard-arrow" aria-hidden="true">↗</span></Link>)}</div>}
      <div className="admin-dashboard-panels">
        {profiles && <QueueSummary title="Hồ sơ gia sư" queues={profiles} statuses={profileStatuses} href="/admin" />}
        {reports && <QueueSummary title="Báo cáo người dùng" queues={reports} statuses={reportStatuses} href="/admin/reports" />}
      </div>
      {data.resources.reviews && <section className="admin-dashboard-panel"><header><h2>Đánh giá</h2></header><dl className="admin-dashboard-extra"><div><dt>Đang hiển thị</dt><dd>{number(data.resources.reviews.visible ?? 0)}</dd></div><div><dt>Đã ẩn</dt><dd>{number(data.resources.reviews.hidden ?? 0)}</dd></div></dl></section>}
      {data.resources.notification_outbox && <section className="admin-dashboard-panel"><header><h2>Thông báo hệ thống</h2></header><dl className="admin-dashboard-extra"><div><dt>Chờ gửi</dt><dd>{number(data.resources.notification_outbox.pending)}</dd></div><div><dt>Gửi lỗi</dt><dd>{number(data.resources.notification_outbox.failed)}</dd></div></dl></section>}
      {!Object.keys(data.resources).length && <StatePanel kind="empty" message="Tài khoản chưa được cấp quyền xem thống kê các khu vực quản trị." />}
      <aside className="admin-dashboard-monitoring"><div><h2>Giám sát hệ thống</h2><p>Mở Telescope và Pulse để theo dõi hoạt động và hiệu năng ứng dụng.</p></div><Link className="button button-secondary" href="/monitoring">Mở giám sát <span aria-hidden="true">→</span></Link></aside>
    </> : null}
  </section>;
}
