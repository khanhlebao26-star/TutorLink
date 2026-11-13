"use client";

import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { Button, ErrorActions, Field, StatePanel } from "@/components/ui";
import { ApiClientError, describeApiError, request } from "@/lib/api/client";
import type { ApiEnvelope, Role, User, UserStatus } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useRef, useState, type FormEvent } from "react";
import { LogoutButton } from "@/components/logout-button";
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

  const initials = user.full_name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi-VN") || "TL";
  const birthDate = user.date_of_birth
    ? new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric", timeZone: "UTC" }).format(new Date(`${user.date_of_birth.slice(0, 10)}T00:00:00Z`))
    : "Chưa cập nhật";

  return <div className="settings-content-stack">
    <form className="card form-stack settings-form" onSubmit={save} aria-busy={pending}>
      <div className="settings-card-heading"><h2>Thông tin cá nhân</h2></div>
      <div className="settings-avatar-row">
        <span className="settings-field-label">Ảnh đại diện</span>
        <div className="settings-avatar" aria-label={`Ảnh đại diện ${user.full_name}`}>{initials}</div>
        <div className="settings-avatar-note"><span className="settings-info-icon" aria-hidden="true">i</span><span><strong>Ảnh đại diện giúp mọi người dễ nhận diện bạn hơn</strong><small>Ảnh đại diện có thể được cập nhật trong hồ sơ TutorLink.</small></span></div>
      </div>
      <div className="settings-form-row">
        <span className="settings-field-label">Họ và tên</span>
        <Field label="Họ và tên" htmlFor="settings-full-name" error={nameError}>
          <input id="settings-full-name" autoComplete="name" required minLength={2} maxLength={255} disabled={pending} value={name} onChange={(event) => { setName(event.target.value); setSaved(false); setNameError(""); setError(null); }} />
        </Field>
      </div>
      <div className="settings-form-row">
        <span className="settings-field-label">Email</span>
        <Field label="Email" htmlFor="settings-email" hint="Không thể thay đổi email tại đây." >
          <input id="settings-email" type="email" autoComplete="email" value={user.email} readOnly />
        </Field>
      </div>
      <div className="settings-form-row">
        <span className="settings-field-label">Ngày sinh</span>
        <Field label="Ngày sinh" htmlFor="settings-birth-date" hint="Không thể thay đổi ngày sinh tại đây.">
          <input id="settings-birth-date" value={birthDate} readOnly />
        </Field>
      </div>
      <div className="settings-form-row">
        <span className="settings-field-label">Vai trò tài khoản</span>
        <Field label="Vai trò tài khoản" htmlFor="settings-role" hint="Vai trò tài khoản được cố định.">
          <input id="settings-role" value={roleLabels[user.role]} readOnly />
        </Field>
      </div>
      {error ? <div role="alert"><p className="inline-error">{describeApiError(error)}</p>{[401, 419].includes(error.status ?? 0) ? <p><a className="text-button" href="/auth/login" target="_blank" rel="noreferrer">Đăng nhập trong tab mới</a><span className="muted"> rồi thử lưu lại. Thông tin đang nhập được giữ tại đây.</span></p> : null}</div> : null}
      {saved ? <p className="inline-success" role="status">Đã lưu thông tin tài khoản.</p> : null}
      <div className="settings-save"><Button type="submit" disabled={pending || name.trim() === user.full_name}>{pending ? "Đang lưu…" : "Lưu thay đổi"}</Button></div>
    </form>
    <section className="card settings-logout" aria-label="Đăng xuất">
      <span className="settings-logout-icon" aria-hidden="true">↪</span>
      <div><h2>Đăng xuất</h2><p className="muted">Đăng xuất khỏi tài khoản của bạn trên thiết bị này.</p></div>
      <LogoutButton />
    </section>
    <p className="settings-account-status">Trạng thái tài khoản: <strong>{statusLabels[user.status]}</strong> · Email {user.email_verified_at ? "đã xác minh" : "chưa xác minh"}</p>
  </div>;
}

function AdminAccountDetails({ user }: { user: User }) {
  return <div className="settings-content-stack">
    <section className="card settings-admin-account" aria-labelledby="admin-account-heading">
      <div className="settings-card-heading"><h2 id="admin-account-heading">Thông tin tài khoản quản trị</h2><p className="muted">Thông tin nhận diện quản trị viên trong hoạt động xét duyệt và xử lý báo cáo.</p></div>
      <dl className="settings-admin-details">
        <div><dt>Họ và tên</dt><dd>{user.full_name}</dd></div>
        <div><dt>Email</dt><dd>{user.email}</dd></div>
        <div><dt>Vai trò tài khoản</dt><dd>{roleLabels[user.role]}</dd></div>
        <div><dt>Trạng thái tài khoản</dt><dd>{statusLabels[user.status]}</dd></div>
      </dl>
    </section>
    <section className="card settings-admin-security" aria-labelledby="admin-security-heading"><div><h2 id="admin-security-heading">Bảo mật tài khoản</h2><p className="muted">Cập nhật mật khẩu đăng nhập của bạn.</p></div><Link className="button button-outline" href="/settings/password">Đổi mật khẩu</Link></section>
    <section className="card settings-logout" aria-label="Đăng xuất"><span className="settings-logout-icon" aria-hidden="true">↪</span><div><h2>Đăng xuất</h2><p className="muted">Đăng xuất khỏi tài khoản trên thiết bị này.</p></div><LogoutButton /></section>
  </div>;
}

export default function SettingsPage() {
  const load = useCallback(async () => (await request<ApiEnvelope<User>>({ method: "GET", url: "/me" })).data.data, []);
  const { data, error, loading, reload } = useAsyncData(load);
  return <AppShell title="">
    <section className="content-stack settings-page">
      <nav className="settings-breadcrumb" aria-label="Đường dẫn"><Link href="/">Trang chủ</Link><span aria-hidden="true">/</span><span>Cài đặt</span><span aria-hidden="true">/</span><strong>Cài đặt tài khoản</strong></nav>
      <header className="settings-page-heading"><h1>Cài đặt tài khoản</h1><p className="muted">{data?.role === "admin" ? "Xem thông tin tài khoản và quản lý bảo mật." : "Quản lý thông tin cá nhân của bạn."}</p></header>
      {loading ? <StatePanel kind="loading" message="Đang tải thông tin tài khoản…" /> : error ? <StatePanel kind="error" message={describeApiError(error)} action={<ErrorActions login={[401, 419].includes(error.status ?? 0)} onRetry={reload} />} /> : data ? data.role === "admin" ? <AdminAccountDetails user={data} /> : <AccountSettingsForm key={data.id} initialUser={data} /> : null}
    </section>
  </AppShell>;
}
