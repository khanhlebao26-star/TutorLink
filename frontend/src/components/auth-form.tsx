"use client";

import { useState, type FormEvent } from "react";
import { login, register } from "@/lib/data/services";
import type { Role } from "@/lib/data/types";
import { Button, Field } from "./ui";

export function AuthForm({ mode }: { mode: "login" | "register" }) {
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState<Role>("customer");
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setMessage("");
    setSubmitting(true);
    try {
      const user = mode === "login" ? await login(email, password) : await register({ full_name: fullName, email, password, role });
      setMessage(`${mode === "login" ? "Đăng nhập" : "Đăng ký"} thành công cho ${user.full_name}.`);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : "Không thể thực hiện thao tác.");
    } finally {
      setSubmitting(false);
    }
  }

  return <form className="card form-stack" onSubmit={submit}>{mode === "register" ? <Field label="Họ và tên" htmlFor="full-name"><input id="full-name" value={fullName} onChange={(event) => setFullName(event.target.value)} required /></Field> : null}<Field label="Email" htmlFor="email"><input id="email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} required /></Field><Field label="Mật khẩu" htmlFor="password"><input id="password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} minLength={8} required /></Field>{mode === "register" ? <Field label="Vai trò" htmlFor="role"><select id="role" value={role} onChange={(event) => setRole(event.target.value as Role)}><option value="customer">Customer</option><option value="tutor">Tutor</option></select></Field> : null}{error ? <p className="inline-error" role="alert">{error}</p> : null}{message ? <p className="inline-success" role="status">{message}</p> : null}<Button type="submit" disabled={submitting}>{submitting ? "Đang xử lý…" : mode === "login" ? "Đăng nhập" : "Tạo tài khoản"}</Button></form>;
}
