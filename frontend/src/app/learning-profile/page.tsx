"use client";

import "@/app/learning-profile/learning-profile.css";
import { AppShell, useActor } from "@/components/layout/app-shell";
import { Invitations } from "@/components/programs/invitations";
import { Programs } from "@/components/programs/program-list";
import { Button, ErrorActions, Field, StatePanel } from "@/components/shared/ui";
import { describeApiError, request } from "@/lib/api/client";
import type { ApiEnvelope } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback, useEffect, useState, type FormEvent } from "react";

interface LearningProfile {
  subjects_of_interest: string | null;
  learning_level: string | null;
  learning_goals: string | null;
  preferred_schedule: string | null;
  preferred_mode: "online" | "offline" | "either" | null;
  city: string | null;
  district: string | null;
}

function LearningProfileForm({ initial, onSaved }: { initial: LearningProfile; onSaved: () => void }) {
  const [values, setValues] = useState(initial);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  function update<K extends keyof LearningProfile>(field: K, value: LearningProfile[K]) {
    setValues((current) => ({ ...current, [field]: value }));
    setSaved(false);
    setError("");
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPending(true);
    setError("");
    setSaved(false);
    try {
      const response = await request<ApiEnvelope<LearningProfile>>({ method: "PUT", url: "/me/learning-profile", data: values }, true);
      setValues(response.data.data);
      setSaved(true);
      onSaved();
    } catch (reason) {
      setError(describeApiError(reason, "Không thể lưu hồ sơ học tập."));
    } finally {
      setPending(false);
    }
  }

  return <form className="card form-stack learning-profile-form" onSubmit={submit} aria-busy={pending}>
    <header className="learning-profile-section-heading"><span aria-hidden="true">✦</span><div><h2>Nhu cầu học tập</h2><p>Thông tin này giúp bạn ghi lại mục tiêu và điều kiện học mong muốn.</p></div></header>
    <Field label="Môn học hoặc kỹ năng quan tâm" htmlFor="learning-subjects" hint="Ví dụ: Tiếng Anh giao tiếp, Toán lớp 9, Guitar.">
      <input id="learning-subjects" maxLength={500} value={values.subjects_of_interest ?? ""} onChange={(event) => update("subjects_of_interest", event.target.value)} placeholder="Bạn muốn học môn hoặc kỹ năng nào?" disabled={pending} />
    </Field>
    <Field label="Trình độ hiện tại" htmlFor="learning-level">
      <select id="learning-level" value={values.learning_level ?? ""} onChange={(event) => update("learning_level", event.target.value || null)} disabled={pending}>
        <option value="">Chọn trình độ</option><option value="Chưa xác định">Chưa xác định</option><option value="Mới bắt đầu">Mới bắt đầu</option><option value="Cơ bản">Cơ bản</option><option value="Trung cấp">Trung cấp</option><option value="Nâng cao">Nâng cao</option>
      </select>
    </Field>
    <Field label="Mục tiêu học tập" htmlFor="learning-goals" hint="Nêu điều bạn muốn đạt được hoặc vấn đề bạn muốn cải thiện.">
      <textarea id="learning-goals" maxLength={2000} value={values.learning_goals ?? ""} onChange={(event) => update("learning_goals", event.target.value)} placeholder="Ví dụ: tự tin giao tiếp tiếng Anh trong công việc…" disabled={pending} />
    </Field>
    <Field label="Hình thức học mong muốn" htmlFor="learning-mode">
      <select id="learning-mode" value={values.preferred_mode ?? ""} onChange={(event) => update("preferred_mode", (event.target.value || null) as LearningProfile["preferred_mode"])} disabled={pending}>
        <option value="">Chưa chọn</option><option value="online">Trực tuyến</option><option value="offline">Gặp trực tiếp</option><option value="either">Linh hoạt cả hai</option>
      </select>
    </Field>
    <Field label="Thời gian học thuận tiện" htmlFor="learning-schedule" hint="Ví dụ: tối các ngày trong tuần hoặc sáng cuối tuần.">
      <input id="learning-schedule" maxLength={500} value={values.preferred_schedule ?? ""} onChange={(event) => update("preferred_schedule", event.target.value)} placeholder="Bạn thường rảnh vào thời gian nào?" disabled={pending} />
    </Field>
    <div className="learning-profile-location">
      <Field label="Tỉnh / thành phố" htmlFor="learning-city">
        <input id="learning-city" maxLength={120} value={values.city ?? ""} onChange={(event) => update("city", event.target.value)} placeholder="Ví dụ: TP. Hồ Chí Minh" disabled={pending} />
      </Field>
      <Field label="Quận / huyện" htmlFor="learning-district">
        <input id="learning-district" maxLength={120} value={values.district ?? ""} onChange={(event) => update("district", event.target.value)} placeholder="Không bắt buộc" disabled={pending} />
      </Field>
    </div>
    <aside className="learning-profile-privacy"><span aria-hidden="true">ⓘ</span><p><strong>Thông tin riêng tư</strong><br />Hồ sơ học tập hiện chỉ hiển thị với bạn trong tài khoản này.</p></aside>
    {error ? <p className="inline-error" role="alert">{error}</p> : null}
    {saved ? <p className="inline-success" role="status">Đã lưu hồ sơ học tập.</p> : null}
    <div className="settings-save"><Button type="submit" disabled={pending}>{pending ? "Đang lưu…" : "Lưu hồ sơ học tập"}</Button></div>
  </form>;
}

export default function LearningProfilePage() {
  return <AppShell role="customer" title="Hồ sơ học tập" hideSidebar>
    <LearningProfileContent />
  </AppShell>;
}

function LearningProfileContent() {
  const actor = useActor();
  const [activeTab, setActiveTab] = useState<"profile" | "programs" | "invitations">("profile");
  useEffect(() => {
    const tab = new URLSearchParams(window.location.search).get("tab");
    if (tab === "programs" || tab === "invitations") setActiveTab(tab);
  }, []);
  function selectTab(tab: "profile" | "programs" | "invitations") {
    setActiveTab(tab);
    const url = new URL(window.location.href);
    if (tab === "profile") url.searchParams.delete("tab");
    else url.searchParams.set("tab", tab);
    window.history.replaceState(null, "", url);
  }
  const load = useCallback(async () => (await request<ApiEnvelope<LearningProfile>>({ method: "GET", url: "/me/learning-profile" })).data.data, []);
  const { data, error, loading, reload } = useAsyncData(load);
  const initials = actor?.full_name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase("vi-VN") || "HV";
  const tabs = [
    { id: "profile" as const, label: "Hồ sơ học tập" },
    { id: "programs" as const, label: "Program của tôi" },
    { id: "invitations" as const, label: "Lời mời học" },
  ];
  return <section className="content-stack learning-profile-page">
      <section className="tutor-workspace-profile learning-profile-header" aria-label="Hồ sơ học tập">
        <div className="tutor-profile-cover" aria-hidden="true" />
        <div className="tutor-profile-identity">
          <div className="tutor-profile-avatar" aria-label={`Ảnh đại diện ${actor?.full_name ?? "học viên"}`}>{initials}</div>
          <div className="tutor-profile-name"><p>HỒ SƠ HỌC VIÊN</p><h1>{actor?.full_name ?? "Học viên TutorLink"}</h1><span>Học viên · TutorLink</span></div>
        </div>
        <nav className="tutor-profile-tabs learning-profile-tabs" role="tablist" aria-label="Nội dung hồ sơ học tập">
        {tabs.map((tab) => <button key={tab.id} id={`learning-tab-${tab.id}`} type="button" role="tab" aria-selected={activeTab === tab.id} aria-controls={`learning-panel-${tab.id}`} className={activeTab === tab.id ? "is-active" : ""} onClick={() => selectTab(tab.id)}>{tab.label}</button>)}
        </nav>
      </section>
      <div id={`learning-panel-${activeTab}`} role="tabpanel" aria-labelledby={`learning-tab-${activeTab}`} className="learning-profile-tab-panel">
        {activeTab === "profile" ? <>
          <header className="learning-profile-section-title"><div><h2>Thông tin học tập</h2><p>Ghi lại mục tiêu, môn học và điều kiện học phù hợp với bạn.</p></div></header>
          {loading ? <StatePanel kind="loading" message="Đang tải hồ sơ học tập…" /> : error ? <StatePanel kind="error" message={describeApiError(error)} action={<ErrorActions login={[401, 419].includes(error.status ?? 0)} onRetry={reload} />} /> : data ? <LearningProfileForm key="learning-profile" initial={data} onSaved={reload} /> : null}
        </> : activeTab === "programs" ? <Programs embedded /> : <Invitations embedded onAccepted={() => selectTab("programs")} />}
      </div>
  </section>;
}
