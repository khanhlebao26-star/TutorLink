import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { AuthForm } from "@/components/auth-form";
import { AuthLayout } from "@/components/auth-layout";

export default function RegisterPage() {
  return (
    <AppShell title="Đăng ký" mode="auth">
      <AuthLayout
        title="Đăng ký"
        description="Tạo tài khoản TutorLink để bắt đầu hành trình học tập và giảng dạy cùng chúng tôi."
        illustration="student-woman"
        tagline={"Kết nối tri thức\nmở lối tương lai"}
        footer={
          <p>
            Đã có tài khoản? <Link href="/auth/login">Đăng nhập</Link>
          </p>
        }
      >
        <AuthForm mode="register" />
      </AuthLayout>
    </AppShell>
  );
}
