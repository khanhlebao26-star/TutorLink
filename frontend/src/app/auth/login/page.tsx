import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { AuthForm } from "@/components/auth-form";
import { AuthLayout } from "@/components/auth-layout";

export default function LoginPage() {
  return (
    <AppShell title="Đăng nhập" mode="auth">
      <AuthLayout
        title="Đăng nhập"
        description={<><strong>Chào mừng bạn trở lại TutorLink</strong>Tiếp tục hành trình học tập và giảng dạy của bạn.</>}
        illustration="student-man"
        tagline={"Cùng bạn\nphát triển\nmỗi ngày"}
      >
        <AuthForm mode="login" />
        <p className="auth-signup-prompt">
          Chưa có tài khoản? <Link href="/auth/register">Đăng ký ngay</Link>
        </p>
        <aside className="auth-login-verification-note" aria-label="Hướng dẫn xác thực email">
          <span className="auth-login-verification-icon" aria-hidden="true" />
          <div>
            <strong>Vui lòng xác thực email</strong>
            <p>Sau khi đăng ký, chúng tôi sẽ gửi một email xác thực đến địa chỉ email của bạn. Vui lòng kiểm tra hộp thư và làm theo hướng dẫn để hoàn tất tài khoản.</p>
          </div>
        </aside>
      </AuthLayout>
    </AppShell>
  );
}
