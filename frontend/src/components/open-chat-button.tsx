"use client";
import Link from "next/link";
import Image from "next/image";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiClientError } from "@/lib/api/client";
import { openConversation, sendMessage } from "@/lib/data/messaging";
import { Button } from "./ui";

export type ChatContactContext = {
  tutorName: string;
  listingTitle: string;
  category?: string;
  delivery?: string;
  price?: string;
  image?: string;
};

export function OpenChatButton({
  peerId,
  listingId,
  label = "Nhắn tin với gia sư",
  className,
  contact,
}: {
  peerId: number;
  listingId?: number;
  label?: string;
  className?: string;
  contact?: ChatContactContext;
}) {
  const router = useRouter();
  const lock = useRef(false);
  const [isOpen, setIsOpen] = useState(false);
  const [draft, setDraft] = useState("");
  const [busy, setBusy] = useState(false),
    [error, setError] = useState<unknown>(null);
  const apiError = error instanceof ApiClientError ? error : null;
  const errorMessage = apiError?.code === "tutor_ineligible"
    ? "Gia sư chưa đủ điều kiện nhận liên hệ mới. Gia sư cần hoàn tất các yêu cầu dưới đây:"
    : apiError?.code === "account_inactive"
      ? "Tài khoản của bạn đang bị vô hiệu hóa hoặc tạm khóa. Vui lòng liên hệ hỗ trợ."
    : apiError?.code === "conversation_forbidden"
      ? "Chỉ tài khoản học viên và tutor mới có thể bắt đầu cuộc trò chuyện."
    : apiError?.code === "peer_invalid"
    ? "TutorLink chỉ hỗ trợ trò chuyện giữa Học viên và Tutor. Hãy dùng tài khoản có vai trò đối lập với gia sư để bắt đầu nhắn tin."
    : apiError?.status === 401
      ? "Phiên đăng nhập của bạn chưa sẵn sàng. Vui lòng đăng nhập để tiếp tục trò chuyện."
      : apiError?.status === 403
        ? "Tài khoản hiện chưa đủ điều kiện để bắt đầu cuộc trò chuyện."
        : apiError?.status === 409
          ? "Gia sư hiện chưa thể nhận cuộc trò chuyện mới. Vui lòng thử lại sau."
          : apiError?.status === 429
            ? "Bạn đang gửi yêu cầu quá nhanh. Vui lòng đợi một chút rồi thử lại."
            : "Chưa thể mở cuộc trò chuyện lúc này. Vui lòng thử lại sau.";
  const errorTitle = apiError?.code === "peer_invalid"
    ? "Tài khoản chưa đúng vai trò"
    : apiError?.code === "tutor_ineligible"
      ? "Gia sư chưa sẵn sàng nhận tin nhắn"
    : apiError?.status === 401
      ? "Bạn cần đăng nhập"
      : "Chưa thể bắt đầu trò chuyện";
  function open() {
    const tutorName = contact?.tutorName || "gia sư";
    setDraft(contact?.listingTitle
      ? `Chào ${tutorName}, mình quan tâm đến dịch vụ ${contact.listingTitle}. Bạn có thể chia sẻ thêm về nội dung và lịch học phù hợp không?`
      : `Chào ${tutorName}, mình muốn trao đổi thêm về dịch vụ của bạn. Bạn có thể tư vấn giúp mình không?`);
    setError(null);
    setIsOpen(true);
  }

  useEffect(() => {
    if (!isOpen) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape" && !lock.current) setIsOpen(false);
    };
    window.addEventListener("keydown", closeOnEscape);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", closeOnEscape);
    };
  }, [isOpen]);

  async function send() {
    if (lock.current) return;
    const message = draft.trim();
    if (!message) return;
    lock.current = true;
    setBusy(true);
    setError(null);
    try {
      const c = await openConversation(peerId, listingId);
      const clientId = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      await sendMessage(c.id, message, clientId);
      setIsOpen(false);
      router.push(`/messages/${c.id}`);
    } catch (e) {
      setError(e);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  }
  return (
    <>
      <Button className={className} onClick={open}>
        {label}
      </Button>
      {isOpen ? <div className="chat-compose-backdrop" onMouseDown={(event) => { if (event.target === event.currentTarget && !lock.current) setIsOpen(false); }}>
        <section className="chat-compose-dialog" role="dialog" aria-modal="true" aria-labelledby="chat-compose-title">
          <div className="chat-compose-heading">
            <h2 id="chat-compose-title">Nhắn tin cho {contact?.tutorName || "gia sư"}</h2>
            <button className="chat-compose-close" type="button" aria-label="Đóng cửa sổ nhắn tin" disabled={busy} onClick={() => setIsOpen(false)}>×</button>
          </div>
          {contact ? <article className="chat-compose-listing">
            <div className="chat-compose-listing-image">
              {contact.image ? <Image src={contact.image} alt={`Ảnh minh họa ${contact.category || "dịch vụ"}`} fill sizes="128px" /> : null}
            </div>
            <div className="chat-compose-listing-copy">
              <strong>{contact.listingTitle}</strong>
              {contact.category ? <span><i aria-hidden="true">★</i> {contact.category}</span> : null}
              {contact.delivery ? <span>▦ {contact.delivery}</span> : null}
              {contact.price ? <span>◇ {contact.price}</span> : null}
            </div>
          </article> : null}
          <label className="chat-compose-field">
            <span>Tin nhắn của bạn</span>
            <textarea autoFocus maxLength={1000} value={draft} onChange={(event) => setDraft(event.target.value)} />
            <small>{draft.length}/1000</small>
          </label>
          {error ? <aside className="open-chat-alert" role="alert">
            <span className="open-chat-alert-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z" /></svg></span>
            <span className="open-chat-alert-copy"><strong>{errorTitle}</strong><span>{errorMessage}</span>{apiError?.code === "tutor_ineligible" ? <ul>{(apiError.fieldErrors?.eligibility?.length ? apiError.fieldErrors.eligibility : ["Email gia sư đã xác minh; tài khoản hoạt động; hồ sơ được duyệt và không bị đình chỉ. Giấy tờ được duyệt nếu hệ thống yêu cầu xác minh."]).map((reason) => <li key={reason}>{reason}</li>)}</ul> : null}{apiError?.status === 401 ? <Link href="/auth/login">Đăng nhập để tiếp tục <span aria-hidden="true">→</span></Link> : null}</span>
          </aside> : null}
          <Button className="chat-compose-send" disabled={busy || !draft.trim()} onClick={send}>{busy ? "Đang gửi…" : "Gửi tin nhắn"}</Button>
        </section>
      </div> : null}
    </>
  );
}
