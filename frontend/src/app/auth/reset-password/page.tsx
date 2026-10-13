import Link from "next/link";
import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthLayout } from "@/components/auth-layout";
import { AuthSupportForm } from "@/components/auth-support-form";

export default function ResetPasswordPage() {
  return (
    <AppShell title="Đặt lại mật khẩu" mode="auth">
      <AuthLayout
        title="Đặt lại mật khẩu"
        description="Tạo mật khẩu mới cho tài khoản của bạn. Hãy chọn mật khẩu đủ mạnh để bảo vệ tài khoản."
        variant="support"
        illustration="reset"
        footer={<Link className="auth-back-link" href="/auth/login">←&nbsp; Quay lại đăng nhập</Link>}
      >
        <Suspense fallback={<div className="auth-support-fallback" role="status">Đang tải biểu mẫu…</div>}>
          <AuthSupportForm mode="reset" />
        </Suspense>
      </AuthLayout>
    </AppShell>
  );
}
