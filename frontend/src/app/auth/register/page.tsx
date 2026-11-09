import { AppShell } from "@/components/app-shell";
import { AuthForm } from "@/components/auth-form";
import { AuthLayout } from "@/components/auth-layout";

export default async function RegisterPage({
  searchParams,
}: {
  searchParams: Promise<{ role?: string | string[] }>;
}) {
  const initialRole = (await searchParams).role === "tutor" ? "tutor" : "customer";

  return (
    <AppShell title="Đăng ký" mode="auth">
      <AuthLayout
        title="Đăng Ký"
        description="Tạo tài khoản TutorLink để bắt đầu hành trình học tập và giảng dạy cùng chúng tôi."
        cardSize="register"
        illustration="student-woman"
        tagline={<><span>Kết nối<br />Tri thức<br />Mở lối</span><span className="auth-slogan-accent">tương lai</span></>}
        secondaryTagline={"Học tốt hơn\nMỗi ngày"}
      >
        <AuthForm key={initialRole} mode="register" initialRole={initialRole} />
      </AuthLayout>
    </AppShell>
  );
}
