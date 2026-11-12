"use client";
import Link from "next/link";
import { useCallback, useRef, useState, type ChangeEvent, type ReactNode } from "react";
import { usePathname } from "next/navigation";
import { AppShell, useActor } from "./app-shell";
import { Badge, Button, Field, Input } from "./ui";
import { ApiClientError, describeApiError, request } from "@/lib/api/client";
import { completeFile, uploadFile } from "@/lib/data/services";
import type { FileRecord } from "@/lib/data/types";
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

function MessageAttachment({ file }: { file: NonNullable<Message["attachments"]>[number] }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  async function download() {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      const response = await request<Blob>({ url: `/files/${file.id}/download`, responseType: "blob" });
      const href = URL.createObjectURL(response.data);
      const anchor = document.createElement("a");
      anchor.href = href;
      anchor.download = file.name || `tutorlink-file-${file.id}`;
      anchor.click();
      window.setTimeout(() => URL.revokeObjectURL(href), 1000);
    } catch (reason) {
      setError(describeApiError(reason));
    } finally {
      setBusy(false);
    }
  }
  return <div className="message-attachment-wrap">
    <button className="message-attachment" type="button" onClick={() => void download()} disabled={busy}>
      <span aria-hidden="true">↧</span><span><strong>{file.name}</strong><small>{busy ? "Đang tải…" : `${(file.size_bytes / 1024).toFixed(0)} KB · Tải tệp`}</small></span>
    </button>
    {error ? <small role="alert" className="message-attachment-error">{error}</small> : null}
  </div>;
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
    [failed, setFailed] = useState<{ body: string; key: string; fileIds: number[] } | null>(null);
  const [attachments, setAttachments] = useState<FileRecord[]>([]);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const attachmentInput = useRef<HTMLInputElement>(null);
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
  async function send(payload: { body: string; key: string; fileIds: number[] }) {
    if (!id || sendLock.current) return;
    sendLock.current = true;
    setSending(true);
    setError("");
    setFailed(null);
    try {
      const m = await sendMessage(id, payload.body, payload.key, payload.fileIds);
      setHistory((previous) => mergeMessages(previous, [m]));
      setFailed(null);
      setDraft("");
      setAttachments([]);
    } catch (e) {
      setFailed(payload);
      setError(describeApiError(e));
    } finally {
      sendLock.current = false;
      setSending(false);
    }
  }
  async function chooseAttachments(event: ChangeEvent<HTMLInputElement>) {
    const chosen = Array.from(event.target.files ?? []);
    event.target.value = "";
    if (!chosen.length) return;
    if (attachments.length + chosen.length > 5) {
      setError("Bạn có thể đính kèm tối đa 5 tệp trong một tin nhắn.");
      return;
    }
    setError("");
    setUploading(true);
    try {
      for (const file of chosen) {
        setUploadProgress(0);
        const uploaded = await uploadFile(file, "message_attachment", setUploadProgress);
        const complete = await completeFile(uploaded.id);
        setAttachments((current) => [...current, complete]);
      }
    } catch (reason) {
      setError(describeApiError(reason));
    } finally {
      setUploading(false);
      setUploadProgress(0);
    }
  }
  async function removeAttachment(file: FileRecord) {
    setAttachments((current) => current.filter((item) => item.id !== file.id));
    try {
      await request({ method: "DELETE", url: `/files/${file.id}` }, true);
    } catch {
      // The file remains private and unlinked if cleanup is unavailable.
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
                        {m.body ? <p>{m.body}</p> : null}
                        {m.attachments?.map((file) => <MessageAttachment file={file} key={file.id} />)}
                        {!m.body && !m.attachments?.length ? <p>Tệp đính kèm</p> : null}
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
                <p>{failed.body || "Tin nhắn có tệp đính kèm"}</p>
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
                if ((draft.trim() || attachments.length) && !failed && !uploading)
                  void send({ body: draft, key: crypto.randomUUID(), fileIds: attachments.map((file) => file.id) });
              }}
            >
              {attachments.length ? <div className="message-composer-attachments" aria-label="Tệp đính kèm">
                {attachments.map((file) => <span className="message-composer-attachment" key={file.id}>
                  <span aria-hidden="true">▤</span><strong title={file.original_name ?? undefined}>{file.original_name ?? "Tệp đính kèm"}</strong>
                  <button type="button" aria-label={`Gỡ tệp ${file.original_name ?? "đính kèm"}`} disabled={sending || !!failed} onClick={() => void removeAttachment(file)}>×</button>
                </span>)}
              </div> : null}
              <div className="message-composer-controls">
                <input
                  ref={attachmentInput}
                  className="message-file-input"
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                  multiple
                  aria-label="Chọn tệp đính kèm"
                  onChange={(event) => void chooseAttachments(event)}
                />
                <button
                  className="message-attach-button"
                  type="button"
                  aria-label="Đính kèm tệp"
                  title="Đính kèm tệp (tối đa 5 MB mỗi tệp)"
                  disabled={sending || uploading || !!failed || current.status !== "active" || attachments.length >= 5}
                  onClick={() => attachmentInput.current?.click()}
                >
                  <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m20.6 11.3-8.9 8.9a5.2 5.2 0 0 1-7.4-7.4l9.5-9.5a3.5 3.5 0 1 1 4.9 5l-9.5 9.4a1.7 1.7 0 0 1-2.4-2.4l8.8-8.8" /></svg>
                </button>
                <label className="landing-sr-only" htmlFor="message-body">
                  Nội dung tin nhắn
                </label>
                <textarea
                  id="message-body"
                  rows={2}
                  maxLength={4000}
                  placeholder={attachments.length ? "Thêm lời nhắn…" : "Nhập tin nhắn…"}
                  value={draft}
                  disabled={sending || uploading || !!failed || current.status !== "active"}
                  onChange={(e) => setDraft(e.target.value)}
                />
                <Button
                  type="submit"
                  disabled={
                    sending || uploading || !!failed || (!draft.trim() && !attachments.length) || current.status !== "active"
                  }
                  aria-label="Gửi tin nhắn"
                >
                  <span aria-hidden="true">➤</span>
                </Button>
              </div>
              {uploading ? <small className="message-upload-progress" role="status">Đang tải tệp lên… {uploadProgress}%</small> : null}
            </form>
            <small className="muted message-composer-hint">PDF, JPG, PNG, DOC hoặc DOCX · tối đa 5 MB mỗi tệp · {draft.length}/4000</small>
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
