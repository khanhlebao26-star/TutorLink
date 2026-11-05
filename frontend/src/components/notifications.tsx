"use client";
import Link from "next/link";
import { useCallback, useState } from "react";
import { AppShell } from "./app-shell";
import { ApiState } from "./api-state";
import { WorkspaceForm } from "./workspace-form";
import { Button, Card, StatePanel } from "./ui";
import { workspaceRead, type Collection } from "@/lib/data/workspace";
import { useAsyncData } from "@/lib/hooks/use-async-data";
interface Notification {
  id: string;
  type: string;
  read_at: string | null;
  created_at: string;
  deep_link: string | null;
}
const notificationLabels: Record<string, string> = {
  listing_moderation_hidden: "Dịch vụ đã được ẩn",
  listing_moderation_restored: "Dịch vụ được khôi phục",
  tutor_profile_submitted: "Hồ sơ đã gửi xét duyệt",
  tutor_profile_approved: "Hồ sơ đã được duyệt",
  tutor_profile_changes_requested: "Hồ sơ cần bổ sung",
  tutor_profile_rejected: "Kết quả xét duyệt hồ sơ",
  tutor_profile_suspended: "Hồ sơ bị đình chỉ",
  tutor_profile_restored: "Hồ sơ được khôi phục",
  invitation_received: "Bạn có lời mời học mới",
  invitation_accepted: "Lời mời đã được chấp nhận",
  invitation_rejected: "Lời mời đã bị từ chối",
  invitation_cancelled: "Lời mời đã hủy",
  invitation_expired: "Lời mời đã hết hạn",
  task_assigned: "Bạn có bài tập mới",
  task_submitted: "Có bài nộp mới",
  task_feedback_received: "Bài nộp đã có phản hồi",
  task_revision_requested: "Bài nộp cần chỉnh sửa",
  announcement_published: "Program có thông báo mới",
  fee_record_changed: "Khai báo học phí đã thay đổi",
  program_paused: "Program đã tạm dừng",
  program_resumed: "Program đã tiếp tục",
  program_completed: "Program đã hoàn thành",
  program_cancelled: "Program đã hủy",
  report_resolved: "Báo cáo đã được giải quyết",
  report_dismissed: "Báo cáo đã được đóng",
};
export function Notifications() {
  return (
    <AppShell title="Thông báo">
      <NotificationList />
    </AppShell>
  );
}
function NotificationList() {
  const [page, setPage] = useState(1),
    [unread, setUnread] = useState(false);
  const load = useCallback(
    async () => ({
      items: await workspaceRead<Collection<Notification>>("/notifications", {
        page,
        unread: unread ? "1" : "0",
      }),
      count: await workspaceRead<{ data: { count: number } }>(
        "/notifications/unread-count",
      ),
    }),
    [page, unread],
  );
  const result = useAsyncData(load);
  return (
    <section className="content-stack">
      <h1>Thông báo</h1>
      <label>
        <input
          type="checkbox"
          checked={unread}
          onChange={(e) => {
            setUnread(e.target.checked);
            setPage(1);
          }}
        />{" "}
        Chỉ chưa đọc
      </label>
      <ApiState {...result} />
      {result.data && (
        <>
          <p>{result.data.count.data.count} chưa đọc</p>
          <WorkspaceForm
            url="/notifications/read-all"
            label="Đánh dấu tất cả đã đọc"
            onSaved={result.reload}
          />
          {result.data.items.data.length === 0 && (
            <StatePanel kind="empty" message="Không có thông báo." />
          )}
          {result.data.items.data.map((n) => (
            <Card key={`${n.id}-${n.read_at}`}>
              <h2>{notificationLabels[n.type] ?? "Thông báo mới"}</h2>
              <p>
                {n.read_at ? "Đã đọc" : "Chưa đọc"} ·{" "}
                {new Date(n.created_at).toLocaleString("vi-VN")}
              </p>
              {n.deep_link &&
              n.deep_link.startsWith("/") &&
              !n.deep_link.startsWith("//") ? (
                <Link className="button button-outline" href={n.deep_link}>
                  Mở nội dung →
                </Link>
              ) : (
                <p className="muted">
                  Nội dung không còn khả dụng hoặc bạn không còn quyền truy cập.
                </p>
              )}
              {!n.read_at && (
                <WorkspaceForm
                  url={`/notifications/${n.id}/read`}
                  method="PATCH"
                  label="Đánh dấu đã đọc"
                  onSaved={result.reload}
                />
              )}
            </Card>
          ))}
          <div className="button-row">
            <Button disabled={page === 1} onClick={() => setPage(page - 1)}>
              Trước
            </Button>
            <span>Trang {page}</span>
            <Button
              disabled={page >= (result.data.items.meta?.last_page ?? 1)}
              onClick={() => setPage(page + 1)}
            >
              Sau
            </Button>
          </div>
        </>
      )}
    </section>
  );
}
