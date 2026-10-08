import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { AuthForm } from "@/components/auth-form";

export default function RegisterPage() {
  return (
    <AppShell title="Đăng ký">
      <section className="narrow">
        <h1>Tạo tài khoản</h1>
        <p className="lead">Mỗi tài khoản chọn một role trong MVP.</p>
        <AuthForm mode="register" />
        <p className="muted">
          Đã có tài khoản? <Link href="/auth/login">Đăng nhập</Link>
        </p>
      </section>
    </AppShell>
  );
}
