import { AuthLayout } from "@/components/auth/auth-layout";
import { AuthSupportForm } from "@/components/auth/auth-support-form";
import { AppShell } from "@/components/layout/app-shell";
import { Suspense } from "react";

export default function VerifyEmailPage() {
  return (
    <AppShell title="Xác minh email" mode="auth">
      <AuthLayout
        title="Xác minh email"
        variant="support"
        illustration="verification"
      >
        <Suspense fallback={<div className="auth-support-fallback" role="status">Đang kiểm tra liên kết…</div>}>
          <AuthSupportForm mode="verify" />
        </Suspense>
      </AuthLayout>
    </AppShell>
  );
}
