"use client";
import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { AppShell } from "./app-shell";
import { ApiState } from "./api-state";
import { Offer } from "./programs";
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
  return (
    <AppShell title="Chi tiết lời mời">
      <section className="content-stack workspace-page invitation-offer">
        <Link href="/invitations">← Danh sách lời mời</Link>
        <ApiState {...result} />
        {i && (
          <Card>
            <Badge tone="brand">{statuses[i.status]}</Badge>
            <h1>{i.offer_snapshot.offer.title}</h1>
            <p className="muted">
              Điều khoản tại thời điểm gửi · Hết hạn{" "}
              {new Date(i.expires_at).toLocaleString("vi-VN")}
            </p>
            <Offer program={i.offer_snapshot.offer} />
            {i.offer_snapshot.source_listing && (
              <p>Dịch vụ nguồn: {i.offer_snapshot.source_listing.title}</p>
            )}
            {error && (
              <p role="alert" className="field-error">
                {error}
              </p>
            )}
            <div className="button-row">
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
        )}
      </section>
    </AppShell>
  );
}
