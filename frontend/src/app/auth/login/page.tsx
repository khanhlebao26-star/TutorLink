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
      </AuthLayout>
    </AppShell>
  );
}
