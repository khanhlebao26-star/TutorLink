"use client";

import "../onboarding.css";

import { useCallback } from "react";
import { AppShell } from "@/components/app-shell";
import { TutorOnboardingForm } from "@/components/tutor-onboarding-form";
import { describeApiError } from "@/lib/api/client";
import { getTutorOnboarding } from "@/lib/data/services";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { ErrorActions, StatePanel } from "@/components/ui";

export default function ProfilePage() {
  const loadProfile = useCallback(() => getTutorOnboarding(), []);
  const { data, error, loading, reload } = useAsyncData(loadProfile);
  return (
    <AppShell role="tutor" title="Hồ sơ">
      <section className="content-stack onboarding-page">
        <div>
          <h1 className="onboarding-page-title">Hoàn thiện hồ sơ Tutor / Coach</h1>
          <p className="onboarding-page-subtitle">
            Cung cấp thông tin để học viên hiểu rõ hơn về bạn và chuyên môn của bạn.
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
