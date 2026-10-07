"use client";

import { useCallback } from "react";
import { AppShell } from "@/components/app-shell";
import { ProfileForm } from "@/components/profile-form";
import { getProfile } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { ErrorActions, StatePanel } from "@/components/ui";

export default function ProfilePage() {
  const loadProfile = useCallback(() => getProfile("tutor"), []);
  const { data, error, loading, reload } = useAsyncData(loadProfile);
  return <AppShell role="tutor" title="Hồ sơ"><section className="content-stack"><div><h1>Hồ sơ Tutor</h1><p className="lead">Cập nhật thông tin giới thiệu và theo dõi trạng thái hồ sơ của bạn.</p></div>{loading ? <StatePanel kind="loading" message="Đang tải hồ sơ…" /> : error ? <StatePanel kind="error" message={error.status === 401 ? "Phiên đăng nhập đã hết. Hãy đăng nhập lại." : error.message} action={<ErrorActions login={error.status === 401} onRetry={reload} />} /> : data ? <ProfileForm data={data} /> : <StatePanel kind="empty" message="Chưa có hồ sơ để hiển thị." />}</section></AppShell>;
}
