"use client";
import Link from "next/link";
import { useCallback, useRef, useState, type ReactNode } from "react";
import { usePathname } from "next/navigation";
import { AppShell, useActor } from "./app-shell";
import { Badge, Button, Field, Input } from "./ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { usePolling } from "@/lib/hooks/use-polling";
import {
  conversations,
  conversation,
  messages,
  sendMessage,
  markRead,
  mergeMessages,
  type Conversation,
  type Message,
} from "@/lib/data/messaging";
import "@/app/messaging.css";

export function MessagingRoute({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const match = pathname.match(/^\/messages\/(\d+)$/);
  return <>{children}<Messaging id={match ? Number(match[1]) : undefined}/></>;
}

export function Messaging({ id }: { id?: number }) {
  return (
    <AppShell title="Tin nhắn">
      <MessagingBody id={id} />
    </AppShell>
  );
}
function MessagingBody({ id }: { id?: number }) {
  const actor = useActor();
  return <MessagingSession key={actor?.id ?? "guest"} id={id}/>;
}

// This state lives in the shared messages layout, so switching threads keeps the inbox.
function useInboxData() {
  const [items, setItems] = useState<Conversation[]>([]),
    [listCursor, setListCursor] = useState<string | null>(null),
    [listBusy, setListBusy] = useState(false);
  const [listLoaded, setListLoaded] = useState(false);
  const initialized = useRef(false);
  const poll = useCallback(async (signal: AbortSignal) => {
    const list = await conversations(undefined, signal);
    if (signal.aborted) return;
    setItems((previous) => [...list.data, ...previous.filter((item) => !list.data.some((fresh) => fresh.id === item.id))]);
    if (!initialized.current) setListCursor(list.meta.next_cursor);
    initialized.current = true;
    setListLoaded(true);
  }, []);
  const listError = usePolling(poll);
  return { items, setItems, listCursor, setListCursor, listBusy, setListBusy, listLoaded, listError };
}

function MessagingSession({ id }: { id?: number }) {
  const inbox = useInboxData();
  return <MessagingContent key={id ?? "list"} id={id} inbox={inbox}/>;
}

function MessagingContent({ id, inbox }: { id?: number; inbox: ReturnType<typeof useInboxData> }) {
  const actor=useActor();
  const { items, setItems, listCursor, setListCursor, listBusy, setListBusy, listLoaded, listError } = inbox;
  const [query, setQuery] = useState("");
  const [error, setError] = useState("");
  const [current, setCurrent] = useState<Conversation | null>(null),
    [history, setHistory] = useState<Message[]>([]),
    [older, setOlder] = useState<string | null>(null),
    [olderBusy, setOlderBusy] = useState(false);
  const [draft, setDraft] = useState(""),
    [sending, setSending] = useState(false),
    [failed, setFailed] = useState<{ body: string; key: string } | null>(null);
  const userId=actor?.id??null;
  function conversationTime(value: string | null) {
    if (!value) return "";
    const date = new Date(value);
    const today = new Date();
    if (date.toDateString() === today.toDateString()) return date.toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) return "Hôm qua";
    return date.toLocaleDateString("vi-VN", { day: "2-digit", month: "2-digit" });
  }
  const cursor = useRef<string | null>(null),
    initialized = useRef(false),
    read = useRef(0),
    pendingRead=useRef(0),
    sendLock = useRef(false);
  const poll = useCallback(
    async (signal: AbortSignal) => {
      if (!id) return;
      const [c, page] = await Promise.all([
        conversation(id, signal),
        messages(id, cursor.current ? { after: cursor.current } : {}, signal),
      ]);
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
  async function send(payload: { body: string; key: string }) {
    if (!id || sendLock.current) return;
    sendLock.current = true;
    setSending(true);
    setError("");
    setFailed(null);
    try {
      const m = await sendMessage(id, payload.body, payload.key);
      setHistory((previous) => mergeMessages(previous, [m]));
      setFailed(null);
      setDraft("");
    } catch (e) {
      setFailed(payload);
      setError(describeApiError(e));
    } finally {
      sendLock.current = false;
      setSending(false);
    }
  }
  return (
    <section className="messaging-page" aria-label="Hộp thư TutorLink">
      <aside className="conversation-list">
        <div className="conversation-list-heading">
          <h1>Tin nhắn</h1>
        </div>
        <div className="conversation-search-field">
          <Field htmlFor="conversation-search" label="Tìm cuộc trò chuyện">
            <Input
              type="search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Tìm kiếm cuộc trò chuyện…"
            />
          </Field>
        </div>
        <div className="conversation-items" aria-busy={!listLoaded && !listError}>
          {!listLoaded && !listError ? <div className="conversation-list-loading" role="status" aria-label="Đang tải danh sách cuộc trò chuyện">{[0, 1, 2].map((item) => <div key={item} aria-hidden="true"><i/><span><b/><small/></span></div>)}</div> : null}
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
                <span className="conversation-item-copy">
                  <strong>{c.peer.display_name}</strong>
                  <small>{c.preview?.text || "Bắt đầu trò chuyện"}</small>
                </span>
                <time className="conversation-time" dateTime={c.preview?.created_at || undefined}>{conversationTime(c.preview?.created_at || null)}</time>
                {c.unread_count > 0 && (
                  <Badge tone="brand">{c.unread_count}</Badge>
                )}
              </Link>
            ))}
          {listLoaded && !items.filter((c) => c.peer.display_name.toLocaleLowerCase().includes(query.toLocaleLowerCase())).length && <div className="conversation-list-empty"><span aria-hidden="true">⌕</span><strong>{query ? "Không tìm thấy cuộc trò chuyện" : "Chưa có cuộc trò chuyện"}</strong><small>{query ? "Thử tìm bằng tên khác." : "Bắt đầu trò chuyện để kết nối với gia sư hoặc học viên."}</small></div>}
          {listError ? <p role="alert" className="field-error">{describeApiError(listError)}</p> : null}
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
                <small className="thread-presence">
                  <i aria-hidden="true" />
                  {current.status === "active"
                    ? "Cuộc trò chuyện đang mở"
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
                history.map((m, index) => {
                  const own = m.sender_id === userId;
                  const previous = history[index - 1];
                  const showDay = !previous || new Date(previous.created_at).toDateString() !== new Date(m.created_at).toDateString();
                  const messageDate = new Date(m.created_at);
                  const yesterday = new Date();
                  yesterday.setDate(yesterday.getDate() - 1);
                  const dayLabel = messageDate.toDateString() === new Date().toDateString()
                    ? "Hôm nay"
                    : messageDate.toDateString() === yesterday.toDateString()
                      ? "Hôm qua"
                      : messageDate.toLocaleDateString("vi-VN", { day: "numeric", month: "long", year: "numeric" });
                  return <div className="message-entry" key={m.id}>
                    {showDay && <div className="message-day"><span>{dayLabel}</span></div>}
                    <div className={`message-row${own ? " own" : ""}`}>
                      {!own && <span className="chat-avatar message-peer-avatar" aria-hidden="true">{current.peer.display_name[0]}</span>}
                      <article className={`message-bubble${own ? " own" : ""}`}>
                        <p>{m.message_type === "text" ? m.body : "Tệp từ lịch sử (không hỗ trợ trong chat hiện tại)"}</p>
                        <time dateTime={m.created_at}>{new Date(m.created_at).toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" })}{own ? <span className="message-read-mark" aria-label="Đã gửi"> ✓</span> : null}</time>
                      </article>
                    </div>
                  </div>;
                })
              ) : (
                <div className="message-empty"><span aria-hidden="true">✦</span><strong>Cuộc trò chuyện mới bắt đầu</strong><p>Gửi lời chào để trao đổi mục tiêu và thống nhất bước tiếp theo.</p></div>
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
                <span aria-hidden="true">➤</span>
              </Button>
            </form>
            <small className="muted message-composer-hint">Chỉ gửi văn bản · {draft.length}/4000</small>
          </>
        ) : (
          id ? <div className="message-thread-loading" role="status" aria-label="Đang tải cuộc trò chuyện">
            <div className="message-thread-loading-heading"><span /><div><i /><i /></div></div>
            <div className="message-thread-loading-copy"><strong>Đang mở cuộc trò chuyện</strong><span>Tin nhắn sẽ xuất hiện tại đây trong giây lát.</span></div>
            <div className="message-thread-loading-bubble is-left" /><div className="message-thread-loading-bubble is-right" />
            <div className="message-thread-loading-composer"><span /><b>➤</b></div>
          </div> : <div className="message-unselected">
            <div className="message-unselected-art" aria-hidden="true"><svg viewBox="0 0 100 100" fill="none"><path d="M20 23h60a9 9 0 0 1 9 9v33a9 9 0 0 1-9 9H52L34 88V74H20a9 9 0 0 1-9-9V32a9 9 0 0 1 9-9Z"/><path d="M32 43h36M32 55h23"/></svg><span>✦</span></div>
            <h2>Chưa chọn cuộc trò chuyện nào</h2>
            <p>Chọn một người trong danh sách bên trái để xem tin nhắn và bắt đầu trao đổi.</p>
          </div>
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
        <h2>Thông tin liên hệ</h2>
        {current ? (
          <>
            <div className="conversation-contact-card">
              <span className="chat-avatar conversation-contact-avatar" aria-hidden="true">{current.peer.display_name[0]}</span>
              <strong>{current.peer.display_name}</strong>
              <small>{actor?.role === "tutor" ? "Học viên TutorLink" : current.peer.tutor_headline || "Gia sư TutorLink"}</small>
            </div>
            {actor?.role !== "tutor" && (current.peer.tutor_specializations || current.peer.tutor_slug) && (
              <div className="conversation-tutor-details">
                <p className="conversation-context-eyebrow">GIA SƯ DẠY</p>
                <strong>{current.peer.tutor_specializations || current.peer.tutor_headline || "Thông tin chuyên môn"}</strong>
                {current.peer.tutor_slug && (
                  <Link className="button button-outline" href={`/tutors/${current.peer.tutor_slug}`}>Xem hồ sơ gia sư <span aria-hidden="true">→</span></Link>
                )}
              </div>
            )}
            {current.context_listing_id ? <div className="conversation-service-card">
              <p className="conversation-context-eyebrow">DỊCH VỤ ĐANG TRAO ĐỔI</p>
              <div className="conversation-service-art" aria-hidden="true"><span>✦</span></div>
              <strong>Dịch vụ TutorLink</strong>
              <small>Mã dịch vụ #{current.context_listing_id}</small>
              <Link href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
            </div> : <p className="conversation-context-note">Hãy trao đổi mục tiêu, nhu cầu và thời gian phù hợp để bắt đầu.</p>}
            {actor?.role === "tutor" && <Link className="button button-outline conversation-program-link" href={`/programs/new?customer_id=${current.peer.id}${current.context_listing_id ? `&source_listing_id=${current.context_listing_id}` : ""}`}>Tạo Program &amp; lời mời</Link>}
          </>
        ) : (
          <div className="conversation-context-empty"><span aria-hidden="true">⌑</span><p>Chọn người liên hệ để xem thông tin và dịch vụ liên quan.</p></div>
        )}
      </aside>
    </section>
  );
}
