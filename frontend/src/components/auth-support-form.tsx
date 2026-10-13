"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { requestPasswordReset, resendVerification, resetPassword, verifyEmail } from "@/lib/data/services";
import { Button, Field } from "./ui";

type SupportMode = "verify" | "forgot" | "reset";

function describedBy(id: string, hint?: string, error?: string) {
  return [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;
}

export function AuthSupportForm({ mode }: { mode: SupportMode }) {
  const searchParams = useSearchParams();
  const router = useRouter();
  const id = searchParams.get("id");
  const hash = searchParams.get("hash");
  const expires = searchParams.get("expires") ?? undefined;
  const signature = searchParams.get("signature") ?? undefined;
  const hasLinkToken = Boolean(searchParams.get("token"));
  const [email, setEmail] = useState(searchParams.get("email") ?? "");
  const [token, setToken] = useState(searchParams.get("token") ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(mode === "verify" && Boolean(id && hash));
  const [verified, setVerified] = useState(false);
  const submittingRef = useRef(Boolean(mode === "verify" && id && hash));

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  useEffect(() => {
    if (mode !== "verify" || !id || !hash) return;
    let active = true;
    submittingRef.current = true;
    verifyEmail(id, hash, { expires, signature })
      .then(() => {
        if (!active) return;
        setVerified(true);
        setMessage("Email đã được xác minh. Bạn có thể đăng nhập.");
      })
      .catch((reason: unknown) => {
        if (active) setError(describeApiError(reason));
      })
      .finally(() => {
        if (!active) return;
        submittingRef.current = false;
        setSubmitting(false);
      });
    return () => {
      active = false;
    };
  }, [mode, id, hash, expires, signature]);

  const verificationPending = mode === "verify" && Boolean(id && hash) && !verified && !error && !message;
  const isBusy = submitting || verificationPending;

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setFieldErrors({});

    if (mode === "reset" && password !== passwordConfirmation) {
      setFieldErrors({ password_confirmation: ["Mật khẩu xác nhận chưa khớp."] });
      return;
    }
    if (submittingRef.current) return;

    submittingRef.current = true;
    setSubmitting(true);
    try {
      if (mode === "verify") {
        setMessage(await resendVerification());
      } else if (mode === "forgot") {
        setMessage(await requestPasswordReset(email));
      } else {
        setMessage(await resetPassword({ token, email, password, password_confirmation: passwordConfirmation }));
        window.setTimeout(() => router.push("/auth/login"), 1200);
      }
    } catch (reason) {
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      setError(describeApiError(reason));
    } finally {
      submittingRef.current = false;
      setSubmitting(false);
    }
  }

  if (mode === "verify") {
    return (
      <div className="auth-verify-actions">
        {isBusy ? <p className="auth-verify-copy" role="status">Đang xác minh email…</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        {email ? <p className="auth-verify-copy">Chúng tôi đã gửi email xác minh đến <strong>{email}</strong>. Vui lòng kiểm tra hộp thư và thư mục Spam.</p> : null}
        {!id || !hash ? (
          <p className="auth-verify-copy">Mở liên kết xác minh trong email để hoàn tất đăng ký. Nếu chưa nhận được email, bạn có thể gửi lại thông báo bên dưới.</p>
        ) : null}
        {verified ? (
          <Link className="button" href="/auth/login">Tiếp tục đăng nhập</Link>
        ) : (
          <form className="form-stack auth-verify-resend" onSubmit={submit} aria-busy={isBusy}>
            <Button type="submit" disabled={isBusy}>{isBusy ? "Đang xử lý…" : "Gửi lại email xác minh"}</Button>
          </form>
        )}
      </div>
    );
  }

  const emailError = fieldError("email");
  const tokenError = fieldError("token");
  const passwordError = fieldError("password");
  const confirmationError = fieldError("password_confirmation");

  return (
    <form className="form-stack auth-form" onSubmit={submit} aria-busy={submitting}>
      <fieldset className="auth-fields" disabled={submitting}>
        <legend className="auth-visually-hidden">{mode === "forgot" ? "Email tài khoản" : "Thông tin mật khẩu mới"}</legend>
      {mode === "reset" && hasLinkToken ? <input type="hidden" name="token" value={token} /> : null}
      <div className="auth-field">
        <Field label="Email" htmlFor="support-email" error={emailError}>
          <div className="auth-input-icon auth-icon-email">
            <input
              id="support-email"
              type="email"
              inputMode="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              aria-invalid={Boolean(emailError)}
              aria-describedby={describedBy("support-email", undefined, emailError)}
              required
            />
          </div>
        </Field>
      </div>
      {mode === "reset" ? (
        <>
          {!hasLinkToken ? (
            <div className="auth-field">
              <Field label="Token đặt lại mật khẩu" htmlFor="reset-token" error={tokenError} hint="Token nằm trong liên kết đặt lại mật khẩu được gửi qua email.">
                <input
                  id="reset-token"
                  autoComplete="off"
                  value={token}
                  onChange={(event) => setToken(event.target.value)}
                  aria-invalid={Boolean(tokenError)}
                  aria-describedby={describedBy("reset-token", "Token nằm trong liên kết đặt lại mật khẩu được gửi qua email.", tokenError)}
                  required
                />
              </Field>
            </div>
          ) : null}
          {hasLinkToken && tokenError ? (
            <div className="auth-token-error">
              <p className="auth-field-error" role="alert">{tokenError}</p>
              <Link className="auth-link" href={`/auth/forgot-password?email=${encodeURIComponent(email)}`}>Yêu cầu liên kết mới</Link>
            </div>
          ) : null}
          <div className="auth-field">
            <Field label="Mật khẩu mới" htmlFor="new-password" error={passwordError} hint="Mật khẩu nên có ít nhất 8 ký tự.">
              <div className="auth-input-icon auth-icon-lock">
                <input
                  id="new-password"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  aria-invalid={Boolean(passwordError)}
                  aria-describedby={describedBy("new-password", "Mật khẩu nên có ít nhất 8 ký tự.", passwordError)}
                  required
                />
              </div>
            </Field>
          </div>
          <div className="auth-field">
            <Field label="Xác nhận mật khẩu" htmlFor="new-password-confirmation" error={confirmationError}>
              <div className="auth-input-icon auth-icon-lock">
                <input
                  id="new-password-confirmation"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  aria-invalid={Boolean(confirmationError)}
                  aria-describedby={describedBy("new-password-confirmation", undefined, confirmationError)}
                  required
                />
              </div>
            </Field>
          </div>
        </>
      ) : null}
      </fieldset>
      {error ? <p className="inline-error" role="alert">{error}</p> : null}
      {message ? <p className="inline-success" role="status">{message}</p> : null}
      <Button type="submit" disabled={submitting}>
        {submitting ? "Đang xử lý…" : mode === "forgot" ? "Gửi liên kết đặt lại" : "Cập nhật mật khẩu"}
      </Button>
    </form>
  );
}
