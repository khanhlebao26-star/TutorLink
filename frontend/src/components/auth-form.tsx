"use client";

import { useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { getCurrentUser, login, register } from "@/lib/data/services";
import type { RegisterRole } from "@/lib/data/types";
import { Button, Field } from "./ui";

function maxBirthDate() {
  const date = new Date();
  date.setFullYear(date.getFullYear() - 18);
  return date.toISOString().slice(0, 10);
}

export function AuthForm({ mode }: { mode: "login" | "register" }) {
  const router = useRouter();
  const [fullName, setFullName] = useState("");
  const [dateOfBirth, setDateOfBirth] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [role, setRole] = useState<RegisterRole>("customer");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [submitting, setSubmitting] = useState(false);

  function fieldError(name: string) {
    return fieldErrors[name]?.[0];
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setFieldErrors({});
    setSubmitting(true);
    try {
      if (mode === "login") {
        await login(email, password);
        const user = await getCurrentUser();
        router.push(user.role === "tutor" ? "/profile" : "/marketplace");
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
      setMessage(`Đăng ký thành công cho ${user.full_name}. Hãy xác minh email trước khi đăng nhập.`);
    } catch (reason) {
      if (reason instanceof ApiClientError && reason.fieldErrors) setFieldErrors(reason.fieldErrors);
      setError(describeApiError(reason));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form className="card form-stack" onSubmit={submit}>
      {mode === "register" ? (
        <>
          <Field label="Họ và tên" htmlFor="full-name" error={fieldError("full_name")}>
            <input id="full-name" value={fullName} onChange={(event) => setFullName(event.target.value)} required />
          </Field>
          <Field label="Ngày sinh" htmlFor="date-of-birth" error={fieldError("date_of_birth")} hint="Tài khoản phải đủ 18 tuổi.">
            <input id="date-of-birth" type="date" max={maxBirthDate()} value={dateOfBirth} onChange={(event) => setDateOfBirth(event.target.value)} required />
          </Field>
        </>
      ) : null}
      <Field label="Email" htmlFor="email" error={fieldError("email")}>
        <input id="email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
      </Field>
      <Field label="Mật khẩu" htmlFor="password" error={fieldError("password")}>
        <input id="password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} minLength={8} required />
      </Field>
      {mode === "register" ? (
        <>
          <Field label="Xác nhận mật khẩu" htmlFor="password-confirmation" error={fieldError("password_confirmation")}>
            <input id="password-confirmation" type="password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} minLength={8} required />
          </Field>
          <Field label="Vai trò" htmlFor="role" error={fieldError("role")}>
            <select id="role" value={role} onChange={(event) => setRole(event.target.value as RegisterRole)}>
              <option value="customer">Customer</option>
              <option value="tutor">Tutor</option>
            </select>
          </Field>
        </>
      ) : null}
      {error ? <p className="inline-error" role="alert">{error}</p> : null}
      {message ? <p className="inline-success" role="status">{message}</p> : null}
      <Button type="submit" disabled={submitting}>{submitting ? "Đang xử lý…" : mode === "login" ? "Đăng nhập" : "Tạo tài khoản"}</Button>
    </form>
  );
}
