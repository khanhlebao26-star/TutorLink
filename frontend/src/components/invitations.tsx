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
} from "@/lib/data/collaboration";
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
  const load = useCallback(() => listInvitations(page), [page]);
  const result = useAsyncData(load);
  return (
    <AppShell title="Lời mời học">
      <section className="content-stack workspace-page">
        <h1>Lời mời học</h1>
        <p className="muted">
          Xem điều khoản đã gửi và phản hồi trước khi tham gia workspace.
        </p>
        <ApiState {...result} />
        {result.data && (
          <>
            <div className="program-grid">
              {result.data.data.map((i) => (
                <Card key={i.id}>
                  <Badge tone="brand">{statuses[i.status]}</Badge>
                  <h2>{i.offer_snapshot.offer.title}</h2>
                  <p>
                    Hết hạn: {new Date(i.expires_at).toLocaleString("vi-VN")}
                  </p>
                  <Link
                    className="button button-outline"
                    href={`/invitations/${i.id}`}
                  >
                    Xem lời mời →
                  </Link>
                </Card>
              ))}
            </div>
            {!result.data.data.length && (
              <StatePanel kind="empty" message="Bạn chưa có lời mời." />
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
