"use client";
import { ErrorActions, StatePanel } from "@/components/shared/ui";
import { describeApiError, type ApiClientError } from "@/lib/api/client";
export function ApiState({
  loading,
  error,
  reload,
}: {
  loading: boolean;
  error: ApiClientError | null;
  reload: () => void;
}) {
  if (loading) return <StatePanel kind="loading" message="Đang tải dữ liệu…" />;
  if (error)
    return (
      <StatePanel
        kind="error"
        message={describeApiError(error)}
        action={<ErrorActions login={error.status === 401} onRetry={reload} />}
      />
    );
  return null;
}
