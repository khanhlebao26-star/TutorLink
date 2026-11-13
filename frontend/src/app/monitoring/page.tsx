"use client";

import { AppShell } from "@/components/app-shell";
import { Card } from "@/components/ui";
import "./monitoring.css";

function hostDashboard() {
  const value = process.env.NEXT_PUBLIC_HOST_DASHBOARD_URL;
  if (!value) return null;
  try {
    const url = new URL(value);
    return !url.username &&
      !url.password &&
      (url.protocol === "https:" ||
        (url.protocol === "http:" &&
          (url.hostname === "localhost" || url.hostname === "127.0.0.1")))
      ? url.href
      : null;
  } catch {
    return null;
  }
}

function MonitorIcon({ kind }: { kind: "telescope" | "pulse" | "server" | "shield" }) {
  if (kind === "telescope") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="m16 16 4 4M8.5 11h5M11 8.5v5" /></svg>;
  if (kind === "pulse") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12h4l2.2-6 4.3 12 2.3-6H21" /></svg>;
  if (kind === "server") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="16" height="6" rx="1.5" /><rect x="4" y="14" width="16" height="6" rx="1.5" /><path d="M8 7h.01M8 17h.01M12 7h5M12 17h5" /></svg>;
  return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 19 6v5c0 4.5-3 7.7-7 10-4-2.3-7-5.5-7-10V6l7-3Z" /><path d="m9 12 2 2 4-4" /></svg>;
}

export default function Page() {
  const origin = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000")
    .replace(/\/api\/v1\/?$/, "")
    .replace(/\/$/, "");
  const host = hostDashboard();

  return (
    <AppShell role="admin" title="Giám sát">
      <section className="content-stack monitoring-page">
        <header className="monitoring-hero">
          <div className="monitoring-hero-copy">
            <p className="monitoring-eyebrow">QUẢN TRỊ · GIÁM SÁT HỆ THỐNG</p>
            <h1>Giám sát hệ thống</h1>
            <p className="monitoring-lead">Truy cập nhanh các công cụ theo dõi request, hiệu năng ứng dụng và hạ tầng máy chủ.</p>
          </div>
          <div className="monitoring-hero-mark" aria-label="Khu vực dành cho quản trị viên">
            <span><MonitorIcon kind="shield" /></span>
            <strong>Trung tâm<br />giám sát</strong>
            <small>Chỉ dành cho Admin</small>
          </div>
          <div className="monitoring-hero-footer">
            <span><i aria-hidden="true" /> Các dashboard được bảo vệ bởi cấu hình quyền truy cập backend.</span>
            <span>TutorLink · Admin</span>
          </div>
        </header>

        <div className="monitoring-card-grid">
          <Card className="monitoring-tool-card monitoring-telescope-card">
            <div className="monitoring-tool-top"><span className="monitoring-tool-icon"><MonitorIcon kind="telescope" /></span><span className="monitoring-tool-source">LARAVEL TELESCOPE</span></div>
            <div className="monitoring-tool-copy"><h2>Request, lỗi & truy vấn</h2><p>Xem chi tiết request, lỗi ứng dụng và các truy vấn database được ghi nhận.</p></div>
            <div className="monitoring-tool-tags"><span>Request</span><span>Exceptions</span><span>Queries</span></div>
            <a className="button button-primary monitoring-tool-action" href={`${origin}/telescope`} target="_blank" rel="noopener noreferrer">Mở Telescope <span aria-hidden="true">↗</span></a>
          </Card>

          <Card className="monitoring-tool-card monitoring-pulse-card">
            <div className="monitoring-tool-top"><span className="monitoring-tool-icon"><MonitorIcon kind="pulse" /></span><span className="monitoring-tool-source">LARAVEL PULSE</span></div>
            <div className="monitoring-tool-copy"><h2>Hiệu năng ứng dụng</h2><p>Theo dõi request chậm, truy vấn chậm và lỗi đã được Pulse ghi nhận.</p></div>
            <div className="monitoring-tool-tags"><span>Slow requests</span><span>Slow queries</span><span>Errors</span></div>
            <a className="button button-outline monitoring-tool-action" href={`${origin}/pulse`} target="_blank" rel="noopener noreferrer">Mở Pulse Realtime <span aria-hidden="true">↗</span></a>
          </Card>

          <Card className="monitoring-tool-card monitoring-server-card">
            <div className="monitoring-tool-top"><span className="monitoring-tool-icon"><MonitorIcon kind="server" /></span><span className="monitoring-tool-source">SERVER & INFRA</span></div>
            <div className="monitoring-tool-copy"><h2>Tài nguyên máy chủ</h2><p>Dashboard riêng để theo dõi CPU, bộ nhớ và dung lượng lưu trữ.</p></div>
            {host ? <div className="monitoring-server-status"><i aria-hidden="true" /> Đã cấu hình dashboard máy chủ</div> : <div className="monitoring-server-status is-unconfigured"><i aria-hidden="true" /> Chưa cấu hình dashboard máy chủ</div>}
            {host ? <a className="button button-dark monitoring-tool-action" href={host} target="_blank" rel="noopener noreferrer">Mở dashboard máy chủ <span aria-hidden="true">↗</span></a> : <p className="monitoring-server-hint">Có thể kết nối dashboard hạ tầng ở giai đoạn sau.</p>}
          </Card>
        </div>

        <aside className="monitoring-footnote"><span className="monitoring-footnote-icon"><MonitorIcon kind="shield" /></span><p><strong>Lưu ý về quyền truy cập</strong><span>Telescope, Pulse và dashboard máy chủ có thể yêu cầu đăng nhập hoặc quyền riêng ở backend.</span></p><span className="monitoring-version">TUTORLINK · ADMIN</span></aside>
      </section>
    </AppShell>
  );
}
