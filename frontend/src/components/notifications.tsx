"use client";
import Link from "next/link";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { AppShell } from "./app-shell";
import { ApiState } from "./api-state";
import { WorkspaceForm } from "./workspace-form";
import { Button, StatePanel } from "./ui";
import { workspaceRead, type Collection } from "@/lib/data/workspace";
import { request } from "@/lib/api/client";
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
    <AppShell title="" hideSidebar>
      <NotificationList />
    </AppShell>
  );
}
function NotificationList() {
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<NotificationFilter>("all");
  const [olderNotifications, setOlderNotifications] = useState<Notification[]>([]);
  const [deletedIds, setDeletedIds] = useState<string[]>([]);
  const load = useCallback(
    async () => ({
      items: await workspaceRead<Collection<Notification>>("/notifications", {
        page,
        unread: "0",
      }),
      count: await workspaceRead<{ data: { count: number } }>(
        "/notifications/unread-count",
      ),
    }),
    [page],
  );
  const result = useAsyncData(load);
  const currentNotifications = result.data?.items.data;
  const notifications = useMemo(() => {
    const uniqueNotifications = new Map([...olderNotifications, ...(currentNotifications ?? [])].map((notification) => [notification.id, notification]));
    return [...uniqueNotifications.values()];
  }, [olderNotifications, currentNotifications]);
  const filteredNotifications = notifications.filter((notification) => !deletedIds.includes(notification.id) && matchesNotificationFilter(notification, filter));
  const todayKey = new Date().toDateString();
  const todayItems = filteredNotifications.filter((item) => new Date(item.created_at).toDateString() === todayKey);
  const olderItems = filteredNotifications.filter((item) => new Date(item.created_at).toDateString() !== todayKey);
  const lastPage = result.data?.items.meta?.last_page ?? 1;
  const unreadCount = result.data?.count.data.count ?? 0;

  function selectFilter(nextFilter: NotificationFilter) {
    setOlderNotifications([]);
    setPage(1);
    setFilter(nextFilter);
  }
  return (
    <section className="content-stack notifications-page">
      <header className="notification-heading">
        <nav className="notification-breadcrumb" aria-label="Đường dẫn"><Link href="/">Trang chủ</Link><span aria-hidden="true">/</span><strong>Thông báo</strong></nav>
        <h1>Thông báo</h1>
        <p>Cập nhật mới nhất về tin nhắn, chương trình, bài tập và trạng thái hồ sơ của bạn.</p>
        {result.data && result.data.count.data.count > 0 && (
          <div className="notification-mark-all">
            <WorkspaceForm
              url="/notifications/read-all"
              label="Đánh dấu tất cả đã đọc"
              onSaved={result.reload}
            />
          </div>
        )}
      </header>
      <nav className="notification-filters" aria-label="Lọc thông báo">
        {notificationFilters.map(({ key, label }) => <button aria-pressed={filter === key} className={filter === key ? "is-active" : ""} key={key} onClick={() => selectFilter(key)} type="button">
          {label}{key === "unread" && unreadCount > 0 ? <span>{unreadCount}</span> : null}
        </button>)}
      </nav>
      <ApiState {...result} />
      {result.data && (
        <>
          {filteredNotifications.length === 0 && <StatePanel kind="empty" message="Bạn chưa có thông báo phù hợp." />}
          {todayItems.length > 0 && <NotificationGroup title={`Hôm nay (${todayItems.length})`} items={todayItems} onRead={result.reload} onDelete={(id) => setDeletedIds((current) => [...current, id])} />}
          {olderItems.length > 0 && <NotificationGroup title="Trước đó" items={olderItems} onRead={result.reload} onDelete={(id) => setDeletedIds((current) => [...current, id])} />}
          {page < lastPage ? <div className="notification-load-more"><Button variant="outline" disabled={result.loading} onClick={() => { setOlderNotifications((current) => [...current, ...(currentNotifications ?? [])]); setPage((current) => current + 1); }}>Tải thêm thông báo cũ <span aria-hidden="true">⌄</span></Button></div> : null}
        </>
      )}
    </section>
  );
}

type NotificationFilter = "all" | "unread" | "program" | "profile" | "system";

const notificationFilters: Array<{ key: NotificationFilter; label: string }> = [
  { key: "all", label: "Tất cả" },
  { key: "unread", label: "Chưa đọc" },
  { key: "program", label: "Khóa học & Lịch học" },
  { key: "profile", label: "Hồ sơ & Tài khoản" },
  { key: "system", label: "Hệ thống" },
];

function matchesNotificationFilter(notification: Notification, filter: NotificationFilter) {
  if (filter === "all") return true;
  if (filter === "unread") return notification.read_at === null;
  if (filter === "program") return notification.type.startsWith("invitation_") || notification.type.startsWith("task_") || notification.type.startsWith("program_") || notification.type === "announcement_published" || notification.type === "fee_record_changed";
  if (filter === "profile") return notification.type.startsWith("tutor_profile_") || notification.type.startsWith("listing_moderation_");
  return notification.type.startsWith("report_");
}

function NotificationGroup({ title, items, onRead, onDelete }: { title: string; items: Notification[]; onRead: () => void; onDelete: (id: string) => void }) {
  const [openMenu, setOpenMenu] = useState<string | null>(null);
  const openMenuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!openMenu) return;
    function closeOnOutsideClick(event: MouseEvent) {
      if (event.target instanceof Node && !openMenuRef.current?.contains(event.target)) setOpenMenu(null);
    }
    function closeOnEscape(event: KeyboardEvent) {
      if (event.key === "Escape") setOpenMenu(null);
    }
    document.addEventListener("mousedown", closeOnOutsideClick);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("mousedown", closeOnOutsideClick);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [openMenu]);

  return <section className="notification-group" aria-label={title}>
    <h2>{title}</h2>
    <div className="notification-group-list">
      {items.map((notification) => {
        const validLink = Boolean(notification.deep_link && notification.deep_link.startsWith("/") && !notification.deep_link.startsWith("//"));
        return <article className={`notification-row ${notification.read_at ? "is-read" : "is-unread"}`} key={`${notification.id}-${notification.read_at}`}>
          <span className={`notification-unread-dot ${notification.read_at ? "is-hidden" : ""}`} aria-hidden="true" />
          <NotificationIcon type={notification.type} />
          <div className="notification-row-copy">
            <h3>{notificationLabels[notification.type] ?? "Thông báo mới"}</h3>
            <p>{notificationDescriptions[notification.type] ?? "Bạn có cập nhật mới trong tài khoản TutorLink."}</p>
            {validLink ? <Link className="notification-open-link" href={notification.deep_link!}>Xem nội dung <span aria-hidden="true">→</span></Link> : null}
          </div>
          <time className="notification-time" dateTime={notification.created_at}>{formatNotificationTime(notification.created_at)}</time>
          <div className="notification-row-action">
            {notification.read_at ? <span className="notification-read-label">Đã đọc</span> : <WorkspaceForm url={`/notifications/${notification.id}/read`} method="PATCH" label="Đánh dấu đã đọc" onSaved={onRead} />}
          </div>
          <div className="notification-more-wrapper" ref={openMenu === notification.id ? openMenuRef : null}>
            <button aria-expanded={openMenu === notification.id} aria-haspopup="menu" aria-label={`Thao tác với thông báo: ${notificationLabels[notification.type] ?? "Thông báo mới"}`} aria-controls={`notification-menu-${notification.id}`} className="notification-more" onClick={() => setOpenMenu((current) => current === notification.id ? null : notification.id)} type="button">⋮</button>
            {openMenu === notification.id ? <div className="notification-more-menu" id={`notification-menu-${notification.id}`} role="menu">
              <button className="notification-delete-action" onClick={async () => {
                if (!window.confirm("Bạn có chắc muốn xóa thông báo này?")) return;
                try {
                  await request({ method: "DELETE", url: `/notifications/${encodeURIComponent(notification.id)}` }, true);
                  onDelete(notification.id);
                  setOpenMenu(null);
                  onRead();
                } catch {
                  setOpenMenu(null);
                  window.alert("Không thể xóa thông báo. Vui lòng thử lại.");
                }
              }} role="menuitem" type="button">Xóa thông báo</button>
            </div> : null}
          </div>
        </article>;
      })}
    </div>
  </section>;
}

const notificationDescriptions: Record<string, string> = {
  invitation_received: "Bạn vừa nhận được lời mời tham gia một chương trình học.",
  invitation_accepted: "Lời mời của bạn đã được chấp nhận. Chương trình học đã sẵn sàng.",
  invitation_rejected: "Có cập nhật mới về lời mời tham gia chương trình.",
  invitation_cancelled: "Một lời mời tham gia chương trình đã được hủy.",
  invitation_expired: "Lời mời tham gia chương trình đã hết hạn phản hồi.",
  task_assigned: "Một bài tập mới đã được thêm vào chương trình của bạn.",
  task_submitted: "Có bài tập mới được nộp trong chương trình.",
  task_feedback_received: "Bài tập của bạn đã nhận được phản hồi.",
  task_revision_requested: "Vui lòng xem góp ý và cập nhật bài tập của bạn.",
  announcement_published: "Có thông báo mới trong chương trình học của bạn.",
  program_completed: "Chương trình học của bạn đã hoàn thành.",
  program_paused: "Chương trình học của bạn hiện đang tạm dừng.",
  program_resumed: "Chương trình học của bạn đã được tiếp tục.",
};

function formatNotificationTime(value: string) {
  const date = new Date(value);
  const time = date.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  if (date.toDateString() === new Date().toDateString()) return time;
  if (date.toDateString() === yesterday.toDateString()) return `Hôm qua, ${time}`;
  return date.toLocaleDateString("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric" });
}

function NotificationIcon({ type }: { type: string }) {
  const kind = type.startsWith("invitation_") ? "invitation" : type.startsWith("task_") ? "task" : type.startsWith("program_") || type === "announcement_published" ? "program" : type.startsWith("report_") ? "report" : "profile";
  return <span className={`notification-icon notification-icon-${kind}`} aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{kind === "invitation" ? <><rect x="5" y="7" width="14" height="12" rx="2"/><path d="m8 7 4 4 4-4M12 4v4"/></> : kind === "task" ? <><path d="M8 4h7l4 4v12H8z"/><path d="M15 4v5h4M11 13h5m-5 3h5"/></> : kind === "program" ? <><rect x="4" y="6" width="16" height="14" rx="2"/><path d="M8 3v6m8-6v6M4 11h16"/></> : kind === "report" ? <><path d="M12 3 21 19H3L12 3Z"/><path d="M12 9v4m0 3h.01"/></> : <><path d="M20 7 10 17l-5-5"/><circle cx="12" cy="12" r="9"/></>}</svg></span>;
}
