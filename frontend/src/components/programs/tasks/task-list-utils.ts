import type { WorkspaceRow } from "@/lib/data/workspace";

export function taskDueLabel(value?: string | null) {
  if (!value) return "Chưa đặt hạn nộp";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "Chưa đặt hạn nộp" : `Hạn nộp ${date.toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" })}`;
}

export function taskListStatus(status?: string | null) {
  switch (status) {
    case "submitted": return { label: "Chờ gia sư chấm", tone: "brand" as const };
    case "completed": return { label: "Đã hoàn thành", tone: "success" as const };
    case "in_progress": return { label: "Đang làm", tone: "warning" as const };
    case "cancelled": return { label: "Đã hủy", tone: "danger" as const };
    default: return { label: "Chưa nộp bài", tone: "brand" as const };
  }
}

export function taskDueTimestamp(row: WorkspaceRow) {
  if (!row.due_at) return Number.POSITIVE_INFINITY;
  const timestamp = new Date(row.due_at).getTime();
  return Number.isNaN(timestamp) ? Number.POSITIVE_INFINITY : timestamp;
}
