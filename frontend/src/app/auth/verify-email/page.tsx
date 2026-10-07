import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function VerifyEmailPage() {
  return (
    <AppShell title="Xác minh email">
      <section className="narrow">
        <h1>Xác minh email</h1>
        <p className="lead">Mở liên kết trong email hoặc gửi lại thông báo xác minh.</p>
        <Suspense fallback={<div className="card">Đang tải…</div>}>
          <AuthSupportForm mode="verify" />
        </Suspense>
        <p className="muted"><Link href="/auth/login">Quay lại đăng nhập</Link></p>
      </section>
    </AppShell>
  );
}
