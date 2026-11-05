"use client";
import Link from "next/link";
import { useCallback, useRef, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { AppShell, useActor } from "./app-shell";
import { Badge, Button, Field, Input, StatePanel } from "./ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { usePolling } from "@/lib/hooks/use-polling";
import {
  conversations,
  conversation,
  openConversation,
  messages,
  sendMessage,
  markRead,
  mergeMessages,
  type Conversation,
  type Message,
} from "@/lib/data/messaging";
import "@/app/messaging.css";
export function Messaging({ id }: { id?: number }) {
  return (
    <AppShell title="Tin nhắn">
      <MessagingContent key={id ?? "list"} id={id} />
    </AppShell>
  );
}
function MessagingContent({ id }: { id?: number }) {
  const router = useRouter();
  const actor=useActor();
  const [items, setItems] = useState<Conversation[]>([]),
    [listCursor, setListCursor] = useState<string | null>(null),
    [listBusy, setListBusy] = useState(false),
    [query, setQuery] = useState("");
  const [peer, setPeer] = useState(""),
    [opening, setOpening] = useState(false),
    [error, setError] = useState("");
  const [current, setCurrent] = useState<Conversation | null>(null),
    [history, setHistory] = useState<Message[]>([]),
    [older, setOlder] = useState<string | null>(null),
    [olderBusy, setOlderBusy] = useState(false);
  const [draft, setDraft] = useState(""),
    [sending, setSending] = useState(false),
    [failed, setFailed] = useState<{ body: string; key: string } | null>(null);
  const userId=actor?.id??null;
  const cursor = useRef<string | null>(null),
    initialized = useRef(false),
    read = useRef(0),
    pendingRead=useRef(0),
    sendLock = useRef(false);
  const poll = useCallback(
    async (signal: AbortSignal) => {
      const list = await conversations(undefined, signal);
      if (signal.aborted) return;
      setItems((previous) => [
        ...new Map([...previous, ...list.data].map((c) => [c.id, c])).values(),
      ]);
      setListCursor((c) => (initialized.current ? c : list.meta.next_cursor));
      if (!id) {
        initialized.current = true;
        return;
      }
      const c = await conversation(id, signal);
      const page = await messages(
        id,
        cursor.current ? { after: cursor.current } : {},
        signal,
      );
      if (signal.aborted) return;
      setCurrent(c);
      setHistory((previous) => mergeMessages(previous, page.data));
      if (!initialized.current) setOlder(page.meta.next_cursor);
      cursor.current = page.meta.polling_cursor ?? cursor.current;
      initialized.current = true;
      pendingRead.current=Math.max(pendingRead.current,0,...page.data.map(m=>m.id));
      const latest=pendingRead.current;
      if (
        latest > read.current &&
        document.visibilityState !== "hidden" &&
        navigator.onLine
      ) {
        await markRead(id, latest);
        if (!signal.aborted) read.current = latest;
      }
    },
    [id],
  );
  const pollingError = usePolling(poll);
  async function loadOlder() {
    if (!id || !older || olderBusy) return;
    setOlderBusy(true);
    try {
      const page = await messages(id, { before: older });
      setHistory((previous) => mergeMessages(previous, page.data));
      setOlder(page.meta.next_cursor);
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      setOlderBusy(false);
    }
  }
  async function loadConversations() {
    if (!listCursor || listBusy) return;
    setListBusy(true);
    try {
      const page = await conversations(listCursor);
      setItems((previous) => [
        ...new Map([...previous, ...page.data].map((c) => [c.id, c])).values(),
      ]);
      setListCursor(page.meta.next_cursor);
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      setListBusy(false);
    }
  }
  async function open(e: FormEvent) {
    e.preventDefault();
    if (opening) return;
    setOpening(true);
    setError("");
    try {
      const c = await openConversation(Number(peer));
      router.push(`/messages/${c.id}`);
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      setOpening(false);
    }
  }
  async function send(payload: { body: string; key: string }) {
    if (!id || sendLock.current) return;
    sendLock.current = true;
    setSending(true);
    setError("");
    setFailed(payload);
    try {
      const m = await sendMessage(id, payload.body, payload.key);
      setHistory((previous) => mergeMessages(previous, [m]));
      setFailed(null);
      setDraft("");
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      sendLock.current = false;
      setSending(false);
    }
  }
  return (
    <section className="messaging-page">
      <aside className="conversation-list">
        <h1>Tin nhắn</h1>
        <Field htmlFor="conversation-search" label="Tìm cuộc trò chuyện">
          <Input
            type="search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Tên người liên hệ…"
          />
        </Field>
        <div className="conversation-items">
          {items
            .filter((c) =>
              c.peer.display_name
                .toLocaleLowerCase()
                .includes(query.toLocaleLowerCase()),
            )
            .map((c) => (
              <Link
                key={c.id}
                href={`/messages/${c.id}`}
                className={`conversation-item${id === c.id ? " selected" : ""}`}
                aria-current={id === c.id ? "page" : undefined}
              >
                <span className="chat-avatar" aria-hidden="true">
                  {c.peer.display_name[0]}
                </span>
                <span>
                  <strong>{c.peer.display_name}</strong>
                  <small>{c.preview?.text || "Bắt đầu trò chuyện"}</small>
                </span>
                {c.unread_count > 0 && (
                  <Badge tone="brand">{c.unread_count}</Badge>
                )}
              </Link>
            ))}
        </div>
        {listCursor && (
          <Button
            variant="ghost"
            disabled={listBusy}
            onClick={loadConversations}
          >
            Tải thêm cuộc trò chuyện
          </Button>
        )}
        <details>
          <summary>Bắt đầu cuộc trò chuyện</summary>
          <form onSubmit={open}>
            <Field htmlFor="chat-peer" label="ID người liên hệ">
              <Input
                required
                min={1}
                type="number"
                value={peer}
                onChange={(e) => setPeer(e.target.value)}
              />
            </Field>
            <Button type="submit" disabled={opening}>
              Mở cuộc trò chuyện
            </Button>
          </form>
        </details>
      </aside>
      <div className="message-thread">
        {current ? (
          <>
            <header className="thread-heading">
              <span className="chat-avatar" aria-hidden="true">
                {current.peer.display_name[0]}
              </span>
              <div>
                <h2>{current.peer.display_name}</h2>
                <small>
                  {current.status === "active"
                    ? "Cuộc trò chuyện"
                    : "Cuộc trò chuyện đã đóng"}
                </small>
              </div>
            </header>
            <div
              className="message-history"
              aria-label="Lịch sử tin nhắn"
              tabIndex={0}
            >
              {older && (
                <Button
                  variant="ghost"
                  disabled={olderBusy}
                  onClick={loadOlder}
                >
                  Tải tin cũ hơn
                </Button>
              )}
              {history.length ? (
                history.map((m) => (
                  <article
                    key={m.id}
                    className={`message-bubble${m.sender_id === userId ? " own" : ""}`}
                  >
                    <p>
                      {m.message_type === "text"
                        ? m.body
                        : "Tệp từ lịch sử (không hỗ trợ trong chat hiện tại)"}
                    </p>
                    <time dateTime={m.created_at}>
                      {new Date(m.created_at).toLocaleString("vi-VN")}
                    </time>
                  </article>
                ))
              ) : (
                <StatePanel
                  kind="empty"
                  message="Hãy gửi lời chào để bắt đầu."
                />
              )}
            </div>
            {failed && (
              <div className="failed-message" role="status">
                <p>{failed.body}</p>
                <Button
                  variant="outline"
                  disabled={sending}
                  onClick={() => send(failed)}
                >
                  Thử gửi lại
                </Button>
                <Button
                  variant="ghost"
                  disabled={sending}
                  onClick={() => {
                    setDraft(failed.body);
                    setFailed(null);
                    setError("");
                  }}
                >
                  Chỉnh sửa tin nhắn
                </Button>
              </div>
            )}
            <form
              className="message-composer"
              onSubmit={(e) => {
                e.preventDefault();
                if (draft.trim() && !failed)
                  void send({ body: draft, key: crypto.randomUUID() });
              }}
            >
              <label className="landing-sr-only" htmlFor="message-body">
                Nội dung tin nhắn
              </label>
              <textarea
                id="message-body"
                rows={2}
                maxLength={4000}
                placeholder="Nhập tin nhắn…"
                value={draft}
                disabled={sending || !!failed || current.status !== "active"}
                onChange={(e) => setDraft(e.target.value)}
              />
              <Button
                type="submit"
                disabled={
                  sending ||
                  !!failed ||
                  !draft.trim() ||
                  current.status !== "active"
                }
                aria-label="Gửi tin nhắn"
              >
                Gửi →
              </Button>
            </form>
            <small className="muted">
              Chỉ gửi văn bản · {draft.length}/4000
            </small>
          </>
        ) : (
          <StatePanel
            kind={id ? "loading" : "empty"}
            message={
              id
                ? "Đang tải cuộc trò chuyện…"
                : "Chọn một cuộc trò chuyện để bắt đầu."
            }
          />
        )}
        {Boolean(error || pollingError) && (
          <div role="alert" className="field-error">
            <p>{error || describeApiError(pollingError)}</p>
            {pollingError instanceof ApiClientError &&
              pollingError.status === 401 && (
                <Link href="/auth/login">Đăng nhập lại</Link>
              )}
          </div>
        )}
      </div>
      <aside className="conversation-context">
        <h2>Thông tin cuộc trò chuyện</h2>
        {current ? (
          <>
            <p>{current.peer.display_name}</p>
            <p className="muted">
              Mọi trao đổi và lời mời được kiểm tra theo quyền tài khoản.
            </p>
            {current.context_listing_id && (
              <p>Dịch vụ nguồn #{current.context_listing_id}</p>
            )}
{actor?.role==="tutor"&&            <Link
              className="button button-outline"
              href={`/programs/new?customer_id=${current.peer.id}${current.context_listing_id ? `&source_listing_id=${current.context_listing_id}` : ""}`}
            >
              Tạo Program & lời mời
            </Link>}
          </>
        ) : (
          <p className="muted">Chọn người liên hệ để xem thông tin.</p>
        )}
      </aside>
    </section>
  );
}
