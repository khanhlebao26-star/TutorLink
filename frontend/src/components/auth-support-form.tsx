"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { requestPasswordReset, resendVerification, resetPassword, verifyEmail } from "@/lib/data/services";
import { Button, Field } from "./ui";

type SupportMode = "verify" | "forgot" | "reset";

export function AuthSupportForm({ mode }: { mode: SupportMode }) {
  const searchParams = useSearchParams();
  const router = useRouter();
  const [email, setEmail] = useState(searchParams.get("email") ?? "");
  const [token, setToken] = useState(searchParams.get("token") ?? "");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(
    mode === "verify" && Boolean(searchParams.get("id") && searchParams.get("hash")),
  );
  const [verified, setVerified] = useState(false);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  useEffect(() => {
    if (mode !== "verify") return;
    const id = searchParams.get("id");
    const hash = searchParams.get("hash");
    if (!id || !hash) return;
    verifyEmail(id, hash, {
      expires: searchParams.get("expires") ?? undefined,
      signature: searchParams.get("signature") ?? undefined,
    })
      .then(() => {
        setVerified(true);
        setMessage("Email đã được xác minh. Bạn có thể đăng nhập.");
      })
      .catch((reason: unknown) => setError(describeApiError(reason)))
      .finally(() => setSubmitting(false));
  }, [mode, searchParams]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setFieldErrors({});
    setSubmitting(true);
    try {
      if (mode === "verify") {
        const result = await resendVerification();
        setMessage(result);
      } else if (mode === "forgot") {
        setMessage(await requestPasswordReset(email));
      } else {
        setMessage(await resetPassword({ token, email, password, password_confirmation: passwordConfirmation }));
        setTimeout(() => router.push("/auth/login"), 900);
      }
    } catch (reason) {
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      setError(describeApiError(reason));
    } finally {
      setSubmitting(false);
    }
  }

  if (mode === "verify") {
    return (
      <div className="card form-stack">
        {submitting && !verified ? <p role="status">Đang xác minh email…</p> : null}
        {message ? <p className="inline-success" role="status">{message}</p> : null}
        {error ? <p className="inline-error" role="alert">{error}</p> : null}
        {!verified ? (
          <form className="form-stack" onSubmit={submit}>
            <p className="muted">Nếu chưa nhận được email, gửi lại thông báo xác minh cho phiên hiện tại.</p>
            <Button type="submit" disabled={submitting}>Gửi lại email xác minh</Button>
          </form>
        ) : null}
      </div>
    );
  }

  return (
    <form className="card form-stack" onSubmit={submit}>
      <Field label="Email" htmlFor="support-email" error={fieldError("email")}>
        <input id="support-email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
      </Field>
      {mode === "reset" ? (
        <>
          <Field label="Token đặt lại mật khẩu" htmlFor="reset-token" error={fieldError("token")} hint="Token thường nằm trong query string của liên kết email.">
            <input id="reset-token" value={token} onChange={(event) => setToken(event.target.value)} required />
          </Field>
          <Field label="Mật khẩu mới" htmlFor="new-password" error={fieldError("password")}>
            <input id="new-password" type="password" minLength={8} value={password} onChange={(event) => setPassword(event.target.value)} required />
          </Field>
          <Field label="Xác nhận mật khẩu mới" htmlFor="new-password-confirmation" error={fieldError("password_confirmation")}>
            <input id="new-password-confirmation" type="password" minLength={8} value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} required />
          </Field>
        </>
      ) : null}
      {error ? <p className="inline-error" role="alert">{error}</p> : null}
      {message ? <p className="inline-success" role="status">{message}</p> : null}
      <Button type="submit" disabled={submitting}>{submitting ? "Đang xử lý…" : mode === "forgot" ? "Gửi liên kết đặt lại" : "Đặt lại mật khẩu"}</Button>
    </form>
  );
}
