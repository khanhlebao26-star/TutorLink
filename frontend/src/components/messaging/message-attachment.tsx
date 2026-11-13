"use client";
import { describeApiError, request } from "@/lib/api/client";
import type { Message } from "@/lib/data/messaging";
import { useState } from "react";

export function MessageAttachment({ file }: { file: NonNullable<Message["attachments"]>[number] }) {
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

