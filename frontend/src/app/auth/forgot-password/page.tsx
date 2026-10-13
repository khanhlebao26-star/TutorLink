import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthLayout } from "@/components/auth-layout";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function ForgotPasswordPage() {
  return (
    <AppShell title="Quên mật khẩu" mode="auth">
      <AuthLayout
        title="Quên mật khẩu?"
        description="Nhập email của bạn để nhận liên kết đặt lại mật khẩu. Chúng tôi sẽ gửi hướng dẫn vào hộp thư."
        variant="support"
        illustration="forgot"
        footer={<Link className="auth-back-link" href="/auth/login">←&nbsp; Quay lại đăng nhập</Link>}
      >
        <Suspense fallback={<div className="auth-support-fallback" role="status">Đang tải biểu mẫu…</div>}>
          <AuthSupportForm mode="forgot" />
        </Suspense>
      </AuthLayout>
    </AppShell>
  );
}
