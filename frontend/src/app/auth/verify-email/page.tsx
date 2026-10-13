import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthLayout } from "@/components/auth-layout";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function VerifyEmailPage() {
  return (
    <AppShell title="Xác minh email" mode="auth">
      <AuthLayout
        title="Xác minh email"
        description="Kiểm tra hộp thư đến và mở liên kết trong email để hoàn tất xác minh tài khoản."
        variant="support"
        illustration="verification"
        footer={<Link className="auth-back-link" href="/auth/login">←&nbsp; Quay lại đăng nhập</Link>}
      >
        <Suspense fallback={<div className="auth-support-fallback" role="status">Đang kiểm tra liên kết…</div>}>
          <AuthSupportForm mode="verify" />
        </Suspense>
      </AuthLayout>
    </AppShell>
  );
}
