import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function ForgotPasswordPage() {
  return (
    <AppShell title="Quên mật khẩu">
      <section className="narrow">
        <h1>Quên mật khẩu</h1>
        <p className="lead">Nhập email để nhận liên kết đặt lại mật khẩu.</p>
        <Suspense fallback={<div className="card">Đang tải…</div>}>
          <AuthSupportForm mode="forgot" />
        </Suspense>
        <p className="muted"><Link href="/auth/login">Quay lại đăng nhập</Link></p>
      </section>
    </AppShell>
  );
}
