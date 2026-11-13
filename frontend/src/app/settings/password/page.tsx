"use client";

import "@/app/settings/settings.css";
import { AppShell } from "@/components/layout/app-shell";
import { Button, Field } from "@/components/shared/ui";
import { ApiClientError, describeApiError, request } from "@/lib/api/client";
import Link from "next/link";
import { useState, type FormEvent } from "react";

type PasswordField = "current_password" | "password" | "password_confirmation";

export default function ChangePasswordPage() {
  const [values, setValues] = useState<Record<PasswordField, string>>({ current_password: "", password: "", password_confirmation: "" });
  const [errors, setErrors] = useState<Partial<Record<PasswordField, string>>>({});
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [pending, setPending] = useState(false);

  function updateField(field: PasswordField, value: string) {
    setValues((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: undefined }));
    setMessage("");
    setError("");
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setErrors({});
    setMessage("");
    setError("");
    if (values.password !== values.password_confirmation) {
      setErrors({ password_confirmation: "Mật khẩu xác nhận chưa khớp." });
      return;
    }
    setPending(true);
    try {
      await request({ method: "PUT", url: "/me/password", data: values }, true);
      setValues({ current_password: "", password: "", password_confirmation: "" });
      setMessage("Mật khẩu đã được cập nhật.");
    } catch (reason) {
      if (reason instanceof ApiClientError) {
        const fieldErrors = reason.fieldErrors ?? {};
        setErrors({
          current_password: fieldErrors.current_password?.[0],
          password: fieldErrors.password?.[0],
          password_confirmation: fieldErrors.password_confirmation?.[0],
        });
        setError(describeApiError(reason));
      } else {
        setError("Không thể đổi mật khẩu lúc này. Vui lòng thử lại.");
      }
    } finally {
      setPending(false);
    }
  }

  return <AppShell title="">
    <section className="content-stack settings-page">
      <nav className="settings-breadcrumb" aria-label="Đường dẫn"><Link href="/">Trang chủ</Link><span aria-hidden="true">/</span><Link href="/settings">Cài đặt tài khoản</Link><span aria-hidden="true">/</span><strong>Đổi mật khẩu</strong></nav>
      <header className="settings-page-heading"><h1>Đổi mật khẩu</h1><p className="muted">Cập nhật mật khẩu để bảo vệ tài khoản TutorLink của bạn.</p></header>
      <form className="card form-stack settings-form password-change-form" onSubmit={submit} aria-busy={pending}>
        <div className="settings-card-heading"><h2>Mật khẩu mới</h2><p className="muted">Nhập mật khẩu hiện tại, sau đó chọn mật khẩu mới có ít nhất 8 ký tự.</p></div>
        <Field label="Mật khẩu hiện tại" htmlFor="current-password" error={errors.current_password}>
          <input id="current-password" type="password" autoComplete="current-password" required disabled={pending} value={values.current_password} onChange={(event) => updateField("current_password", event.target.value)} />
        </Field>
        <Field label="Mật khẩu mới" htmlFor="new-password" hint="Tối thiểu 8 ký tự." error={errors.password}>
          <input id="new-password" type="password" autoComplete="new-password" minLength={8} required disabled={pending} value={values.password} onChange={(event) => updateField("password", event.target.value)} />
        </Field>
        <Field label="Xác nhận mật khẩu mới" htmlFor="new-password-confirmation" error={errors.password_confirmation}>
          <input id="new-password-confirmation" type="password" autoComplete="new-password" minLength={8} required disabled={pending} value={values.password_confirmation} onChange={(event) => updateField("password_confirmation", event.target.value)} />
        </Field>
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        <div className="settings-save"><Button type="submit" disabled={pending}>{pending ? "Đang cập nhật…" : "Cập nhật mật khẩu"}</Button></div>
      </form>
    </section>
  </AppShell>;
}
