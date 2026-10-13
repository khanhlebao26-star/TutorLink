import { Suspense } from "react";
import { AppShell } from "@/components/app-shell";
import { AuthLayout } from "@/components/auth-layout";
import { AuthSupportForm } from "@/components/auth-support-form";

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
