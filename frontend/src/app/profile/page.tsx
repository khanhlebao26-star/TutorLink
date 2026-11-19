"use client";

import "@/styles/onboarding.css";

import { AppShell } from "@/components/layout/app-shell";
import { ErrorActions, StatePanel } from "@/components/shared/ui";
import { TutorOnboardingForm } from "@/components/tutors/tutor-onboarding-form";
import { describeApiError } from "@/lib/api/client";
import { getTutorOnboarding } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { useCallback } from "react";

export default function ProfilePage() {
  const loadProfile = useCallback(() => getTutorOnboarding(), []);
  const { data, error, loading, reload } = useAsyncData(loadProfile);
  return (
    <AppShell role="tutor" title="Hồ sơ">
      <section className="content-stack onboarding-page">
        <div>
          <h1 className="onboarding-page-title">Hoàn thiện hồ sơ Tutor / Coach</h1>
          <p className="onboarding-page-subtitle">
            Cung cấp thông tin chi tiết để học viên hiểu rõ về bạn và chuyên môn của bạn.
          </p>
        </div>
        {loading ? (
          <StatePanel kind="loading" message="Đang tải hồ sơ…" />
        ) : error ? (
          <StatePanel
            kind="error"
            message={describeApiError(error)}
            action={
              <ErrorActions login={error.status === 401} onRetry={reload} />
            }
          />
        ) : data ? (
          <TutorOnboardingForm data={data} onReload={reload} />
        ) : (
          <StatePanel kind="empty" message="Chưa có hồ sơ để hiển thị." />
        )}
      </section>
    </AppShell>
  );
}
