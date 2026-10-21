"use client";

import { AppShell } from "@/components/app-shell";
import { Button, ErrorActions, Field, StatePanel } from "@/components/ui";
import { ApiClientError, describeApiError, request } from "@/lib/api/client";
import type { ApiEnvelope, Role, User, UserStatus } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useRef, useState, type FormEvent } from "react";
import "./settings.css";

const roleLabels: Record<Role, string> = { customer: "Học viên", tutor: "Gia sư / Coach", admin: "Quản trị viên" };
const statusLabels: Record<UserStatus, string> = {
  active: "Đang hoạt động",
  suspended: "Bị đình chỉ",
  deactivated: "Đã vô hiệu hóa",
};
function AccountSettingsForm({ initialUser }: { initialUser: User }) {
  const [user, setUser] = useState(initialUser);
  const [name, setName] = useState(initialUser.full_name);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<ApiClientError | null>(null);
  const [nameError, setNameError] = useState("");
  const [saved, setSaved] = useState(false);
  const saving = useRef(false);

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (saving.current) return;
    const fullName = name.trim();
    setSaved(false);
    setError(null);
    setNameError("");
    if (fullName.length < 2) {
      setNameError("Vui lòng nhập tên hiển thị có ít nhất 2 ký tự.");
      return;
    }
    saving.current = true;
    setPending(true);
    try {
      const response = await request<ApiEnvelope<User>>({ method: "PATCH", url: "/me", data: { full_name: fullName } }, true);
      setUser(response.data.data);
      setName(response.data.data.full_name);
      setSaved(true);
      window.dispatchEvent(new Event("tutorlink-account-updated"));
    } catch (reason) {
      const nextError = reason instanceof ApiClientError ? reason : new ApiClientError("Không thể lưu thông tin tài khoản. Vui lòng thử lại.");
      setError(nextError);
      setNameError(nextError.fieldErrors?.full_name?.[0] ?? "");
    } finally {
      saving.current = false;
      setPending(false);
    }
  }

  return <div className="settings-grid">
    <form className="card form-stack settings-form" onSubmit={save} aria-busy={pending}>
      <div><h2>Thông tin cá nhân</h2><p className="muted">Cập nhật tên hiển thị của bạn trên TutorLink.</p></div>
      <Field label="Tên hiển thị" htmlFor="settings-full-name" error={nameError}>
        <input id="settings-full-name" autoComplete="name" required minLength={2} maxLength={255} disabled={pending} value={name} onChange={(event) => { setName(event.target.value); setSaved(false); setNameError(""); setError(null); }} />
      </Field>
      <Field label="Email" htmlFor="settings-email" hint="Email hiện tại của tài khoản.">
        <input id="settings-email" type="email" autoComplete="email" value={user.email} readOnly />
      </Field>
      {error ? <div role="alert"><p className="inline-error">{describeApiError(error)}</p>{[401, 419].includes(error.status ?? 0) ? <p><a className="text-button" href="/auth/login" target="_blank" rel="noreferrer">Đăng nhập trong tab mới</a><span className="muted"> rồi thử lưu lại. Thông tin đang nhập được giữ tại đây.</span></p> : null}</div> : null}
      {saved ? <p className="inline-success" role="status">Đã lưu thông tin tài khoản.</p> : null}
      <div className="settings-save"><Button type="submit" disabled={pending || name.trim() === user.full_name}>{pending ? "Đang lưu…" : "Lưu thay đổi"}</Button></div>
    </form>
    <section className="card settings-account" aria-labelledby="settings-account-title">
      <h2 id="settings-account-title">Thông tin tài khoản</h2>
      <dl>
        <div><dt>Vai trò</dt><dd>{roleLabels[user.role]}</dd></div>
        <div><dt>Trạng thái</dt><dd>{statusLabels[user.status]}</dd></div>
        <div><dt>Email xác minh</dt><dd>{user.email_verified_at ? "Đã xác minh" : "Chưa xác minh"}</dd></div>
      </dl>
    </section>
  </div>;
}

export default function SettingsPage() {
  const load = useCallback(async () => (await request<ApiEnvelope<User>>({ method: "GET", url: "/me" })).data.data, []);
  const { data, error, loading, reload } = useAsyncData(load);
  return <AppShell title="Cài đặt">
    <section className="content-stack settings-page">
      <header><h1>Cài đặt tài khoản</h1><p className="lead">Quản lý tên hiển thị và xem thông tin tài khoản của bạn.</p></header>
      {loading ? <StatePanel kind="loading" message="Đang tải thông tin tài khoản…" /> : error ? <StatePanel kind="error" message={describeApiError(error)} action={<ErrorActions login={[401, 419].includes(error.status ?? 0)} onRetry={reload} />} /> : data ? <AccountSettingsForm key={data.id} initialUser={data} /> : null}
    </section>
  </AppShell>;
}
