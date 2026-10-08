import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function ResetPasswordPage() {
  return (
    <AppShell title="Đặt lại mật khẩu">
      <section className="narrow">
        <h1>Đặt lại mật khẩu</h1>
        <p className="lead">Dùng token trong email để tạo mật khẩu mới.</p>
        <Suspense fallback={<div className="card">Đang tải…</div>}>
          <AuthSupportForm mode="reset" />
        </Suspense>
        <p className="muted"><Link href="/auth/login">Quay lại đăng nhập</Link></p>
      </section>
    </AppShell>
  );
}
