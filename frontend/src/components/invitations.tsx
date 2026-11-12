"use client";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Button, Card, StatePanel } from "./ui";
import { describeApiError } from "@/lib/api/client";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { getCurrentUser } from "@/lib/data/services";
import {
  listInvitations,
  getInvitation,
  decideInvitation,
  mutationKey,
  type Invitation,
} from "@/lib/data/collaboration";
import { formatListingPrice, deliveryLabels } from "@/lib/data/marketplace";
import "@/app/workspace.css";
const statuses: Record<string, string> = {
  pending: "Chờ phản hồi",
  accepted: "Đã chấp nhận",
  rejected: "Đã từ chối",
  cancelled: "Đã hủy",
  expired: "Đã hết hạn",
};
function InvitationIcon({ name }: { name: "book" | "target" | "calendar" | "pin" | "clock" | "check" }) {
  const paths = {
    book: <><path d="M12 5c-3-2-6-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V4c-3-1-6-1-9 1Z" /><path d="M12 5v15" /></>,
    target: <><circle cx="12" cy="12" r="8" /><circle cx="12" cy="12" r="4" /><path d="m12 12 8-8m-4 0h4v4" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="3" /><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2" /></>,
    pin: <><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z" /><circle cx="12" cy="10" r="2" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    check: <><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z" /><path d="m8 12 3 3 5-6" /></>,
  };
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name]}</svg>;
}
export function Invitations() {
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<string>("pending");
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState("");
  const router = useRouter();
  const actionKey = useRef<string | null>(null);
  const load = useCallback(() => listInvitations(page), [page]);
  const result = useAsyncData(load);
  const invitations = result.data?.data ?? [];
  const filters = [
    { value: "pending", label: "Đang chờ" },
    { value: "accepted", label: "Đã chấp nhận" },
    { value: "rejected", label: "Đã từ chối" },
    { value: "expired", label: "Đã hết hạn" },
    { value: "cancelled", label: "Đã hủy" },
  ];
  const counts = invitations.reduce<Record<string, number>>((acc, invitation) => {
    acc[invitation.status] = (acc[invitation.status] ?? 0) + 1;
    return acc;
  }, {});
  const visibleInvitations = invitations.filter((invitation) => invitation.status === filter);
  const selectedInvitation = visibleInvitations.find((invitation) => invitation.id === selectedId)
    ?? visibleInvitations[0]
    ?? null;
  const chooseFilter = (value: string) => {
    setFilter(value);
    setSelectedId(null);
    setActionError("");
  };
  async function respond(invitation: Invitation, action: "accept" | "reject") {
    if (busy) return;
    setBusy(true);
    setActionError("");
    actionKey.current ??= mutationKey();
    try {
      await decideInvitation(invitation.id, action, actionKey.current);
      actionKey.current = null;
      if (action === "accept") router.push(`/programs/${invitation.program_id}`);
      else result.reload();
    } catch (error) {
      setActionError(describeApiError(error));
    } finally {
      setBusy(false);
    }
  }
  return (
    <AppShell title="Lời mời học">
      <section className="content-stack workspace-page invitations-page">
        <header className="workspace-heading invitations-heading">
          <div>
            <p className="invitations-eyebrow">KHỞI ĐẦU HÀNH TRÌNH HỌC TẬP</p>
            <h1>Lời mời Program</h1>
            <p className="muted">Xem các lời mời tham gia chương trình từ gia sư và bắt đầu hành trình học cùng TutorLink.</p>
          </div>
        </header>
        <ApiState {...result} />
        {result.data && (
          <>
            <nav className="invitation-filters" aria-label="Lọc trạng thái lời mời">
              {filters.map(({ value, label }) => (
                <button key={value} type="button" className={filter === value ? "is-active" : ""} aria-pressed={filter === value} onClick={() => chooseFilter(value)}>
                  {label}<span>{counts[value] ?? 0}</span>
                </button>
              ))}
            </nav>
            {invitations.length === 0 ? (
              <StatePanel kind="empty" message="Bạn chưa có lời mời học nào. Khi gia sư gửi lời mời, nội dung sẽ xuất hiện tại đây." />
            ) : visibleInvitations.length === 0 ? (
              <StatePanel kind="empty" message={`Hiện chưa có lời mời ${statuses[filter]?.toLowerCase() ?? "ở trạng thái này"}.`} />
            ) : (
              <div className="invitation-inbox">
                <aside className="invitation-list" aria-label="Danh sách lời mời">
                  {visibleInvitations.map((invitation) => (
                    <button key={invitation.id} type="button" className={`invitation-list-item ${selectedInvitation?.id === invitation.id ? "is-selected" : ""}`} onClick={() => { setSelectedId(invitation.id); setActionError(""); }}>
                      <span className="invitation-avatar">G</span>
                      <span className="invitation-list-copy"><strong>Gia sư</strong><span>{invitation.offer_snapshot.offer.title}</span><small>Hết hạn ngày {new Date(invitation.expires_at).toLocaleDateString("vi-VN")}</small></span>
                      <Badge tone={invitation.status === "pending" ? "warning" : invitation.status === "accepted" ? "success" : "brand"}>{statuses[invitation.status]}</Badge>
                    </button>
                  ))}
                </aside>
                {selectedInvitation && <InvitationPreview invitation={selectedInvitation} busy={busy} error={actionError} onRespond={respond} />}
              </div>
            )}
            <div className="button-row">
              <Button
                variant="outline"
                disabled={page === 1}
                onClick={() => setPage((p) => p - 1)}
              >
                Trước
              </Button>
              <span>Trang {page}</span>
              <Button
                variant="outline"
                disabled={page >= (result.data.meta.last_page ?? Math.max(1,Math.ceil(result.data.meta.total/result.data.meta.per_page)))}
                onClick={() => setPage((p) => p + 1)}
              >
                Sau
              </Button>
            </div>
          </>
        )}
      </section>
    </AppShell>
  );
}

function InvitationPreview({
  invitation,
  busy,
  error,
  onRespond,
}: {
  invitation: Invitation;
  busy: boolean;
  error: string;
  onRespond: (invitation: Invitation, action: "accept" | "reject") => void;
}) {
  const offer = invitation.offer_snapshot.offer;
  const sourceListing = invitation.offer_snapshot.source_listing;
  const pending = invitation.status === "pending" && new Date(invitation.expires_at).getTime() > Date.now();
  return (
    <article className="invitation-preview">
      <header className="invitation-tutor-header">
        <div className="invitation-tutor-summary">
          <span className="invitation-tutor-avatar">{sourceListing?.title.slice(0, 1).toLocaleUpperCase() ?? "G"}</span>
          <div><h2>Gia sư TutorLink <span aria-label="Hồ sơ đã xác minh">✓</span></h2><p>Gia sư · Đang đồng hành cùng bạn</p><div className="invitation-rating"><span>★</span> Hồ sơ gia sư</div></div>
        </div>
        <Link className="button button-outline invitation-profile-link" href={`/invitations/${invitation.id}`}>Chi tiết lời mời ↗</Link>
      </header>
      <Link className="invitation-service-banner" href={`/invitations/${invitation.id}`}>
        <span className="invitation-service-icon" aria-hidden="true">▤</span>
        <span><small>DỊCH VỤ ĐỀ XUẤT</small><strong>{sourceListing?.title ?? offer.title}</strong><span>{offer.goal || "Lộ trình học được thiết kế theo mục tiêu của bạn."}</span></span>
        <span className="invitation-service-arrow" aria-hidden="true">›</span>
      </Link>
      <section className="invitation-program-details">
        <h3>Thông tin chương trình</h3>
        <dl>
          <div><dt>▢ <span>Tên chương trình</span></dt><dd>{offer.title}</dd></div>
          <div><dt>☆ <span>Mục tiêu</span></dt><dd>{offer.goal || "Chưa có thông tin"}</dd></div>
          <div><dt>♙ <span>Hình thức học</span></dt><dd>{deliveryLabels[offer.delivery_mode]}</dd></div>
          <div><dt>⌖ <span>Địa điểm / Ghi chú</span></dt><dd>{offer.location_text || offer.meeting_url || "Trao đổi sau khi chấp nhận"}</dd></div>
          <div><dt>▦ <span>Lịch học</span></dt><dd>{offer.schedule_note || "Hai bên sẽ thống nhất sau"}{offer.timezone ? ` (${offer.timezone})` : ""}</dd></div>
          <div><dt>☆ <span>Học phí thỏa thuận</span></dt><dd>{formatListingPrice(offer.agreed_fee_minor, offer.currency, offer.fee_unit)}</dd></div>
          <div><dt>◷ <span>Lời mời hết hạn</span></dt><dd>{new Date(invitation.expires_at).toLocaleString("vi-VN")}{pending ? <strong className="invitation-expiry">Còn thời hạn</strong> : null}</dd></div>
        </dl>
      </section>
      {error ? <p role="alert" className="field-error invitation-action-error">{error}</p> : null}
      {pending ? <div className="invitation-actions"><Button disabled={busy} onClick={() => onRespond(invitation, "accept")}>✓ &nbsp; Chấp nhận lời mời</Button><Button variant="outline" disabled={busy} onClick={() => onRespond(invitation, "reject")}>× &nbsp; Từ chối</Button></div> : invitation.status === "accepted" ? <Link className="button button-primary invitation-workspace-link" href={`/programs/${invitation.program_id}`}>Mở workspace →</Link> : <div className="invitation-status-note"><Badge tone="brand">{statuses[invitation.status]}</Badge><span>Lời mời này không còn chờ phản hồi.</span></div>}
    </article>
  );
}
export function InvitationDetail({ id }: { id: number }) {
  const [now, setNow] = useState(() => Date.now());
  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 1000);
    return () => clearInterval(timer);
  }, []);
  const router = useRouter();
  const load = useCallback(
    async () => ({
      invitation: await getInvitation(id),
      user: await getCurrentUser(),
    }),
    [id],
  );
  const result = useAsyncData(load);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState("");
  const key = useRef<{ action: string; key: string } | null>(null);
  const lock = useRef(false);
  async function act(action: "accept" | "reject" | "cancel") {
    if (lock.current || !result.data) return;
    lock.current = true;
    setBusy(true);
    setError("");
    if (key.current?.action !== action)
      key.current = { action, key: mutationKey() };
    try {
      await decideInvitation(id, action, key.current.key);
      key.current = null;
      if (action === "accept")
        router.push(`/programs/${result.data.invitation.program_id}`);
      else result.reload();
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }
  const i = result.data?.invitation;
  const canRespond = result.data?.user.role === "customer";
  const pending =
    i?.status === "pending" && new Date(i.expires_at).getTime() > now;
  const status = i?.status === "pending" && !pending ? "expired" : i?.status;
  const offer = i?.offer_snapshot.offer;
  const statusNote = status === "accepted"
    ? "Lời mời đã được chấp nhận. Bạn có thể mở chương trình để theo dõi việc học."
    : status === "rejected"
      ? "Học viên đã từ chối lời mời này. Chương trình chưa được kích hoạt."
      : status === "cancelled"
        ? "Lời mời đã được hủy và không còn nhận phản hồi."
        : status === "expired"
          ? "Thời hạn phản hồi đã kết thúc. Tutor cần gửi lời mời mới để tiếp tục."
          : canRespond
            ? "Kiểm tra thông tin trước khi chấp nhận. Chương trình sẽ được kích hoạt sau khi bạn đồng ý."
            : "Lời mời đã được gửi. Chương trình sẽ được kích hoạt khi học viên chấp nhận.";
  return (
    <AppShell title="Chi tiết lời mời">
      <section className="content-stack workspace-page invitation-detail-page">
        <Link className="invitation-detail-back" href="/invitations">← Danh sách lời mời</Link>
        <ApiState {...result} />
        {i && offer && status && (
          <>
            <header className="invitation-detail-hero">
              <span className="invitation-detail-art"><InvitationIcon name="book" /></span>
              <div className="invitation-detail-heading">
                <p className="invitations-eyebrow">LỜI MỜI THAM GIA CHƯƠNG TRÌNH</p>
                <h1>{offer.title}</h1>
                <p>Cùng thống nhất mục tiêu và bắt đầu hành trình học tập của bạn.</p>
              </div>
              <Badge tone={status === "accepted" ? "success" : status === "pending" ? "brand" : "neutral"}>
                <span className={`invitation-detail-dot is-${status}`} aria-hidden="true" />{statuses[status]}
              </Badge>
            </header>
            <div className="invitation-detail-layout">
              <div className="invitation-detail-main">
                <Card className="invitation-detail-card">
                  <h2><span className="invitation-detail-icon"><InvitationIcon name="target" /></span>Mục tiêu & nội dung học</h2>
                  <p className="invitation-detail-goal">{offer.goal || "Mục tiêu sẽ được trao đổi thêm với tutor."}</p>
                  <dl className="invitation-detail-facts">
                    <div><dt>Trình độ hiện tại</dt><dd>{offer.level || "Chưa thống nhất"}</dd></div>
                    <div><dt>Hình thức học</dt><dd>{deliveryLabels[offer.delivery_mode]}</dd></div>
                    <div><dt>Số buổi dự kiến</dt><dd>{offer.planned_session_count != null ? `${offer.planned_session_count} buổi` : "Chưa thống nhất"}</dd></div>
                  </dl>
                </Card>
                <Card className="invitation-detail-card">
                  <h2><span className="invitation-detail-icon is-blue"><InvitationIcon name="calendar" /></span>Lịch học & địa điểm</h2>
                  <div className="invitation-detail-schedule">
                    <div><InvitationIcon name="clock" /><div><h3>Lịch học dự kiến</h3><p>{offer.schedule_note || "Chưa thống nhất lịch học"}</p><small>Múi giờ: {offer.timezone}</small></div></div>
                    <div><InvitationIcon name="pin" /><div><h3>Địa điểm học</h3><p>{offer.location_text || (offer.delivery_mode === "online" ? "Học trực tuyến" : "Chưa thống nhất địa điểm")}</p>{offer.meeting_url && <><h3 className="invitation-detail-meeting-label">Liên kết buổi học</h3><p>{offer.meeting_url}</p></>}</div></div>
                  </div>
                </Card>
                {i.offer_snapshot.source_listing && <div className="invitation-detail-source">
                  <span className="invitation-detail-icon"><InvitationIcon name="book" /></span>
                  <div><small>ĐƯỢC TẠO TỪ DỊCH VỤ</small><h3>{i.offer_snapshot.source_listing.title}</h3></div>
                </div>}
                <div className="invitation-detail-snapshot"><InvitationIcon name="check" /><p>Thông tin trên là nội dung đã thống nhất tại thời điểm gửi lời mời. Hãy kiểm tra mục tiêu, lịch học và học phí trước khi phản hồi.</p></div>
              </div>
              <aside className="invitation-detail-aside" aria-label="Học phí và phản hồi lời mời">
                <Card className="invitation-detail-card invitation-detail-decision">
                  <p className="invitations-eyebrow">HỌC PHÍ THỎA THUẬN</p>
                  <p className="invitation-detail-price">{new Intl.NumberFormat("vi-VN").format(offer.agreed_fee_minor)} <span>{offer.currency}</span></p>
                  <p className="invitation-detail-unit">/ {({ session: "buổi", hour: "giờ", course: "khóa học" })[offer.fee_unit]}</p>
                  <div className={`invitation-detail-deadline is-${status}`}>
                    <InvitationIcon name="clock" />
                    <div><strong>{pending ? "Phản hồi trước" : "Thời hạn phản hồi"}</strong><time dateTime={i.expires_at}>{new Date(i.expires_at).toLocaleString("vi-VN", { hour: "2-digit", minute: "2-digit", day: "2-digit", month: "2-digit", year: "numeric" })}</time></div>
                  </div>
                  <h2>{pending ? (canRespond ? "Sẵn sàng bắt đầu?" : "Đang chờ học viên") : statuses[status]}</h2>
                  <p className="invitation-detail-status-copy">{statusNote}</p>
                  {error && <p role="alert" className="invitation-detail-error">{error}</p>}
                  <div className="invitation-detail-actions" aria-busy={busy}>
              {pending &&
                (canRespond ? (
                  <>
                    <Button disabled={busy} onClick={() => act("accept")}>
                      Chấp nhận lời mời
                    </Button>
                    <Button
                      disabled={busy}
                      variant="outline"
                      onClick={() => act("reject")}
                    >
                      Từ chối
                    </Button>
                  </>
                ) : (
                  <Button
                    disabled={busy}
                    variant="danger"
                    onClick={() => act("cancel")}
                  >
                    Hủy lời mời
                  </Button>
                ))}
              {i.status === "accepted" && (
                <Link
                  className="button button-primary"
                  href={`/programs/${i.program_id}`}
                >
                  Mở workspace →
                </Link>
              )}
                    <Button variant="ghost" disabled={busy} onClick={result.reload}>
                      Tải trạng thái mới nhất
                    </Button>
                  </div>
                </Card>
                <p className="invitation-detail-help">Cần điều chỉnh thông tin? Hãy trao đổi lại với tutor hoặc học viên trước khi xác nhận.</p>
              </aside>
            </div>
          </>
        )}
      </section>
    </AppShell>
  );
}
