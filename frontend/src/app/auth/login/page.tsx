import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { AuthForm } from "@/components/auth-form";

export default function LoginPage() {
  return (
    <AppShell title="Đăng nhập">
      <section className="narrow">
        <h1>Đăng nhập</h1>
        <p className="lead">
          Dùng phiên cookie Sanctum khi chuyển sang API thật.
        </p>
        <AuthForm mode="login" />
        <p className="muted">
          <Link href="/auth/forgot-password">Quên mật khẩu?</Link>
          <br />
          Chưa có tài khoản? <Link href="/auth/register">Đăng ký</Link>
        </p>
      </section>
    </AppShell>
  );
}
