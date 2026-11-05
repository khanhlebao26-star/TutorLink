"use client";
import { AppShell } from "@/components/app-shell";
import { Card } from "@/components/ui";
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
export default function Page() {
  const origin = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000")
    .replace(/\/api\/v1\/?$/, "")
    .replace(/\/$/, "");
  const host = hostDashboard();
  return (
    <AppShell role="admin" title="Giám sát">
      <section className="content-stack">
        <h1>Giám sát hệ thống</h1>
        <p>
          Các trang giám sát yêu cầu phiên Admin và quyền giám sát. Hãy đăng
          nhập ở backend nếu được yêu cầu.
        </p>
        <Card>
          <h2>Request, lỗi & truy vấn</h2>
          <a
            className="button button-outline"
            href={`${origin}/telescope`}
            target="_blank"
            rel="noopener noreferrer"
          >
            Mở Telescope
          </a>
        </Card>
        <Card>
          <h2>Hiệu năng ứng dụng</h2>
          <a
            className="button button-outline"
            href={`${origin}/pulse`}
            target="_blank"
            rel="noopener noreferrer"
          >
            Mở Pulse
          </a>
        </Card>
        <Card>
          <h2>Tài nguyên máy chủ</h2>
          {host ? (
            <a
              className="button button-outline"
              href={host}
              target="_blank"
              rel="noopener noreferrer"
            >
              Mở dashboard máy chủ
            </a>
          ) : (
            <p>Chưa cấu hình dashboard máy chủ.</p>
          )}
        </Card>
      </section>
    </AppShell>
  );
}
