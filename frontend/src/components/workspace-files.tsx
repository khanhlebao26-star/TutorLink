"use client";
import { useState } from "react";
import { request, describeApiError } from "@/lib/api/client";
import { Button } from "./ui";
export function WorkspaceFiles({ ids }: { ids: number[] }) {
  const [busy, setBusy] = useState<number | null>(null),
    [error, setError] = useState("");
  async function download(id: number) {
    if (busy !== null) return;
    setBusy(id);
    setError("");
    try {
      const response = await request<Blob>({
        url: `/files/${id}/download`,
        responseType: "blob",
      });
      const href = URL.createObjectURL(response.data);
      const anchor = document.createElement("a");
      anchor.href = href;
      anchor.download = `tutorlink-file-${id}`;
      anchor.click();
      setTimeout(() => URL.revokeObjectURL(href), 1000);
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      setBusy(null);
    }
  }
  return (
    <div>
      {ids.map((id) => (
        <Button
          key={id}
          variant="ghost"
          disabled={busy !== null}
          onClick={() => download(id)}
        >
          Tải file #{id}
        </Button>
      ))}
      {error && <p role="alert">{error}</p>}
    </div>
  );
}
