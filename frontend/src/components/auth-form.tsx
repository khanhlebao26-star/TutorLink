"use client";

import { ApiClientError, describeApiError } from "@/lib/api/client";
import { getCurrentUser, login, register } from "@/lib/data/services";
import type { RegisterRole } from "@/lib/data/types";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useRef, useState, type FormEvent } from "react";
import { Button, Field } from "./ui";

function maxBirthDate(minAge: number) {
  const date = new Date();
  const month = date.getMonth();

  date.setFullYear(date.getFullYear() - minAge);

  if (date.getMonth() !== month) {
    date.setDate(0);
  }

  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, "0"),
    String(date.getDate()).padStart(2, "0"),
  ].join("-");
}

function describedBy(id: string, hint?: string, error?: string) {
  return [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;
}

function PasswordField({
  id,
  label,
  value,
  onChange,
  error,
  autoComplete,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  autoComplete: string;
}) {
  const [visible, setVisible] = useState(false);
  return (
    <div className="auth-field">
      <Field label={label} htmlFor={id} error={error}>
        <div className="auth-password-control auth-icon-lock">
          <input
            id={id}
            type={visible ? "text" : "password"}
            placeholder={label}
            autoComplete={autoComplete}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            minLength={8}
            aria-invalid={Boolean(error)}
            aria-describedby={describedBy(id, undefined, error)}
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

export function AuthForm({ mode }: { mode: "login" | "register" }) {
  const router = useRouter();
  const submittingRef = useRef(false);
  const [fullName, setFullName] = useState("");
  const [dateOfBirth, setDateOfBirth] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [role, setRole] = useState<RegisterRole>("customer");
  const [remember, setRemember] = useState(false);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(false);
  const minAge = role === "tutor" ? 18 : 15;
  const birthDateHint = `Bạn phải từ đủ ${minAge} tuổi.`;
  const latestBirthDate = maxBirthDate(minAge);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setFieldErrors({});

    if (mode === "register" && password !== passwordConfirmation) {
      setFieldErrors({ password_confirmation: ["Mật khẩu xác nhận chưa khớp."] });
      return;
    }
    if (submittingRef.current) return;

    submittingRef.current = true;
    setSubmitting(true);
    try {
      if (mode === "login") {
        await login(email, password, remember);
        const user = await getCurrentUser();
        router.push(user.role === "admin" ? "/admin" : user.role === "tutor" ? "/profile" : "/marketplace");
        return;
      }

      const user = await register({
        full_name: fullName,
        date_of_birth: dateOfBirth,
        email,
        password,
        password_confirmation: passwordConfirmation,
        role,
      });
      setMessage(`Đăng ký thành công cho ${user.full_name}. Hãy kiểm tra email để xác minh tài khoản.`);
    } catch (reason) {
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      const invalidCredentials = mode === "login"
        && reason instanceof ApiClientError
        && reason.status === 401
        && reason.message === "Invalid credentials.";
      setError(invalidCredentials ? "Email hoặc mật khẩu không chính xác." : describeApiError(reason));
    } finally {
      submittingRef.current = false;
      setSubmitting(false);
    }
  }

  const emailError = fieldError("email");
  const passwordError = fieldError("password");
  const confirmationError = fieldError("password_confirmation");

  return (
    <form className="form-stack auth-form" onSubmit={submit} aria-busy={submitting}>
      <fieldset className="auth-fields" disabled={submitting}>
        <legend className="auth-visually-hidden">{mode === "login" ? "Thông tin đăng nhập" : "Thông tin tài khoản"}</legend>
      {mode === "register" ? (
        <>
          <div className="auth-field">
            <Field label="Họ và tên" htmlFor="full-name" error={fieldError("full_name")}>
              <div className="auth-input-icon auth-icon-person">
                <input
                  id="full-name"
                  type="text"
                  placeholder="Họ và tên"
                  autoComplete="name"
                  value={fullName}
                  onChange={(event) => setFullName(event.target.value)}
                  aria-invalid={Boolean(fieldError("full_name"))}
                  aria-describedby={describedBy("full-name", undefined, fieldError("full_name"))}
                  required
                />
              </div>
            </Field>
          </div>
        </>
      ) : null}

      <div className="auth-field">
        <Field label="Email" htmlFor="email" error={emailError}>
          <div className="auth-input-icon auth-icon-email">
            <input
              id="email"
              type="email"
              placeholder="Email"
              autoComplete="email"
              inputMode="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              aria-invalid={Boolean(emailError)}
              aria-describedby={describedBy("email", undefined, emailError)}
              required
            />
          </div>
        </Field>
      </div>

      {mode === "register" ? (
        <div className="auth-field">
          <Field
            label="Ngày sinh"
            htmlFor="date-of-birth"
            hint={birthDateHint}
            error={fieldError("date_of_birth")}
          >
            <div>
              <input
                id="date-of-birth"
                type="date"
                autoComplete="bday"
                max={latestBirthDate}
                value={dateOfBirth}
                onChange={(event) => setDateOfBirth(event.target.value)}
                aria-invalid={Boolean(fieldError("date_of_birth"))}
                aria-describedby={describedBy(
                  "date-of-birth",
                  birthDateHint,
                  fieldError("date_of_birth"),
                )}
                required
              />
            </div>
          </Field>
        </div>
      ) : null}

      <PasswordField
        id="password"
        label="Mật khẩu"
        value={password}
        onChange={setPassword}
        error={passwordError}
        autoComplete={mode === "login" ? "current-password" : "new-password"}
      />

      {mode === "register" ? (
        <>
          <PasswordField
            id="password-confirmation"
            label="Xác nhận mật khẩu"
            value={passwordConfirmation}
            onChange={setPasswordConfirmation}
            error={confirmationError}
            autoComplete="new-password"
          />
          <fieldset className="auth-role-field" aria-describedby={fieldError("role") ? "role-error" : undefined}>
            <legend>Vai trò của bạn</legend>
            <div className="auth-role-options">
              <label className="auth-role-option">
                <input type="radio" name="role" value="customer" checked={role === "customer"} onChange={() => setRole("customer")} />
                <span>Khách hàng</span>
              </label>
              <label className="auth-role-option">
                <input type="radio" name="role" value="tutor" checked={role === "tutor"} onChange={() => setRole("tutor")} />
                <span>Gia sư / Coach</span>
              </label>
            </div>
            {fieldError("role") ? <span className="auth-field-error" id="role-error" role="alert">{fieldError("role")}</span> : null}
          </fieldset>
        </>
      ) : (
        <div className="auth-form-options">
          <label className="checkbox-field">
            <input type="checkbox" checked={remember} onChange={(event) => setRemember(event.target.checked)} />
            <span>Ghi nhớ đăng nhập</span>
          </label>
          <Link className="auth-link" href="/auth/forgot-password">Quên mật khẩu?</Link>
        </div>
      )}
      </fieldset>

      {error ? <p className="inline-error" role="alert">{error}</p> : null}
      {message ? <p className="inline-success" role="status">{message}</p> : null}
      <Button type="submit" disabled={submitting}>
        {submitting ? "Đang xử lý…" : mode === "login" ? "Đăng nhập" : "Tạo tài khoản"}
      </Button>
    </form>
  );
}
