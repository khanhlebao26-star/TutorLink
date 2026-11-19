"use client";

import "@/components/admin/verification-document-preview.css";
import { Button } from "@/components/shared/ui";
import { describeApiError, request } from "@/lib/api/client";
import type { VerificationDocument } from "@/lib/data/types";
import { useEffect, useId, useRef, useState } from "react";

type PreviewState = { status: "loading" } | { status: "error"; message: string } | { status: "ready"; url: string; mime: string };
const imageTypes = ["image/jpeg", "image/png", "image/gif", "image/webp"];

function previewMime(blob: Blob, file: VerificationDocument) {
  const supported = [...imageTypes, "application/pdf"];
  const candidates = [blob.type, file.mime_type].map(value => value?.split(";")[0].trim().toLowerCase());
  const known = candidates.find(value => value && supported.includes(value));
  if (known) return known;
  const extension = file.original_name?.split(".").pop()?.toLowerCase();
  return ({ jpg: "image/jpeg", jpeg: "image/jpeg", png: "image/png", gif: "image/gif", webp: "image/webp", pdf: "application/pdf" } as Record<string, string>)[extension ?? ""] ?? "application/octet-stream";
}

export function VerificationDocumentPreview({ file, onClose }: { file: VerificationDocument; onClose: () => void }) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const [state, setState] = useState<PreviewState>({ status: "loading" });
  const [attempt, setAttempt] = useState(0);
  const [imageFailed, setImageFailed] = useState(false);
  const filename = file.original_name || `Tài liệu #${file.id}`;

  useEffect(() => {
    const dialog = dialogRef.current;
    const previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const previousOverflow = document.body.style.overflow;
    dialog?.showModal();
    document.body.style.overflow = "hidden";
    return () => {
      dialog?.close();
      document.body.style.overflow = previousOverflow;
      if (previousFocus?.isConnected) previousFocus.focus();
    };
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;
    let objectUrl: string | null = null;
    request<Blob>({ url: `/files/${file.file_id}/download`, responseType: "blob", signal: controller.signal })
      .then(response => {
        if (!active) return;
        if (!response.data.size || response.data.type.includes("json")) {
          throw new Error("Máy chủ chưa trả về nội dung tài liệu. Vui lòng thử lại.");
        }
        const mime = previewMime(response.data, file);
        objectUrl = URL.createObjectURL(new Blob([response.data], { type: mime }));
        setState({ status: "ready", url: objectUrl, mime });
      })
      .catch(error => { if (active) setState({ status: "error", message: describeApiError(error) }); });
    return () => {
      active = false;
      controller.abort();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [file, attempt]);

  return <dialog ref={dialogRef} className="document-preview-dialog" aria-labelledby={titleId} onCancel={event => { event.preventDefault(); onClose(); }} onClick={event => {
    if (event.target !== event.currentTarget) return;
    const bounds = event.currentTarget.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) onClose();
  }}>
    <header className="document-preview-header"><div><span>Tài liệu xác minh</span><h2 id={titleId}>{filename}</h2></div><button className="document-preview-close" type="button" onClick={onClose} aria-label="Đóng tài liệu">×</button></header>
    <div className="document-preview-body" aria-busy={state.status === "loading"}>
      {state.status === "loading" ? <div className="document-preview-state" role="status"><span className="document-preview-loading" aria-hidden="true"/><p>Đang tải tài liệu…</p></div> : state.status === "error" ? <div className="document-preview-state"><p role="alert">{state.message}</p><Button variant="secondary" onClick={() => { setState({ status: "loading" }); setImageFailed(false); setAttempt(value => value + 1); }}>Thử lại</Button></div> : imageTypes.includes(state.mime) && !imageFailed ? (
        // Private files are displayed from a short-lived browser URL after an authenticated request.
        // eslint-disable-next-line @next/next/no-img-element
        <img className="document-preview-image" src={state.url} alt={`Nội dung ${filename}`} onError={() => setImageFailed(true)} />
      ) : state.mime === "application/pdf" ? <iframe className="document-preview-pdf" src={state.url} title={`Nội dung ${filename}`} /> : <div className="document-preview-state"><strong>{imageFailed ? "Không thể hiển thị ảnh này" : "Tệp này chưa hỗ trợ xem trực tiếp"}</strong><p>Bạn có thể tải tài liệu về thiết bị để xem.</p></div>}
    </div>
    <footer className="document-preview-footer"><p>{state.status === "ready" ? "Nếu tài liệu không hiển thị, bạn có thể tải về để xem." : "Tài liệu chỉ được tải khi bạn có quyền truy cập."}</p><div><Button variant="secondary" onClick={onClose}>Đóng</Button>{state.status === "ready" ? <a className="button button-primary" href={state.url} download={filename}>Tải tài liệu</a> : null}</div></footer>
  </dialog>;
}
