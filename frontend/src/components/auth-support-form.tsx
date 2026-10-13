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

function SupportPasswordField({
  id,
  label,
  value,
  onChange,
  error,
  hint,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  hint?: string;
}) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="auth-field">
      <Field label={label} htmlFor={id} error={error} hint={hint}>
        <div className="auth-password-control auth-icon-lock">
          <input
            id={id}
            type={visible ? "text" : "password"}
            placeholder={`Nhập ${label.toLowerCase()}`}
            autoComplete="new-password"
            minLength={8}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            aria-invalid={Boolean(error)}
            aria-describedby={describedBy(id, hint, error)}
            required
          />
          <button
            className="auth-password-toggle"
            type="button"
            aria-label={visible ? "Ẩn mật khẩu" : "Hiện mật khẩu"}
            aria-pressed={visible}
            onClick={() => setVisible((current) => !current)}
          >
            {visible ? (
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 3l18 18M10.6 10.7a2 2 0 002.7 2.7M9.9 5.3A11 11 0 0112 5c5 0 8.5 4.4 9 7-.2 1.1-.9 2.2-1.9 3.2M6.2 6.3C4.4 7.5 3.3 9.3 3 12c.5 2.6 4 7 9 7 1 0 2-.2 2.8-.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            ) : <span className="auth-eye-icon" aria-hidden="true" />}
          </button>
        </div>
      </Field>
    </div>
  );
}

export function AuthSupportForm({ mode }: { mode: SupportMode }) {
  const searchParams = useSearchParams();
  const router = useRouter();
  const id = searchParams.get("id");
  const hash = searchParams.get("hash");
  const expires = searchParams.get("expires") ?? undefined;
  const signature = searchParams.get("signature") ?? undefined;
  const hasLinkToken = Boolean(searchParams.get("token"));
  const hasLinkEmail = Boolean(searchParams.get("email"));
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
    if (verified) {
      return (
        <div className="auth-verify-actions">
          <p className="inline-success" role="status">Email đã được xác minh. Bạn có thể đăng nhập.</p>
          <div className="auth-card-footer auth-verify-next">
            <Link className="button" href="/auth/login">Tiếp tục đăng nhập</Link>
          </div>
        </div>
      );
    }

    return (
      <div className="auth-verify-actions">
        {isBusy ? <p className="auth-verify-copy" role="status">Đang xác minh email…</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        <p className="auth-verify-copy">
          Chúng tôi đã gửi email xác minh đến <strong>{email || "địa chỉ email của bạn"}</strong>. Vui lòng kiểm tra hộp thư (bao gồm cả thư mục Spam) và nhấp vào liên kết trong email để hoàn tất xác minh.
        </p>
        <div className="auth-verify-notice">
          <span className="auth-verify-notice-icon" aria-hidden="true">✉</span>
          <div>
            <strong>Chưa nhận được email?</strong>
            <p>Email có thể mất vài phút để đến hộp thư. Bạn có thể gửi lại email xác minh.</p>
          </div>
        </div>
        <form className="form-stack auth-verify-resend" onSubmit={submit} aria-busy={isBusy}>
          <Button type="submit" disabled={isBusy}>{isBusy ? "Đang xử lý…" : "Gửi lại email xác minh"}</Button>
        </form>
        <div className="auth-card-footer auth-verify-next">
          <Link className="button" href="/auth/login">Tiếp tục</Link>
        </div>
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
      {mode === "reset" && hasLinkEmail && !emailError ? <input type="hidden" name="email" value={email} /> : null}
      {mode === "forgot" || !hasLinkEmail || Boolean(emailError) ? (
        <div className="auth-field">
          <Field label="Email" htmlFor="support-email" error={emailError}>
            <div className="auth-input-icon auth-icon-email">
              <input
                id="support-email"
                type="email"
                placeholder="Nhập email của bạn"
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
      ) : null}
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
          <SupportPasswordField id="new-password" label="Mật khẩu mới" value={password} onChange={setPassword} error={passwordError} hint="Mật khẩu nên có ít nhất 8 ký tự." />
          <SupportPasswordField id="new-password-confirmation" label="Xác nhận mật khẩu" value={passwordConfirmation} onChange={setPasswordConfirmation} error={confirmationError} />
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
