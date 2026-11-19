"use client";
import { Button } from "@/components/shared/ui";
import { describeApiError } from "@/lib/api/client";
import { completeFile, uploadFile } from "@/lib/data/services";
import type { FileRecord } from "@/lib/data/types";
import { useRef, useState } from "react";
export function WorkspaceUpload({
  onComplete,
  onBusy,
  label = "Đính kèm bài nộp",
}: {
  onComplete: (id: number) => void;
  onBusy: (busy: boolean) => void;
  label?: string;
}) {
  const [busy, setBusy] = useState(false),
    [percent, setPercent] = useState(0),
    [error, setError] = useState("");
  const uploaded = useRef<FileRecord | null>(null),
    selected = useRef<File | null>(null),
    lock = useRef(false);
  async function run() {
    if (!selected.current || lock.current) return;
    lock.current = true;
    setBusy(true);
    onBusy(true);
    setError("");
    try {
      uploaded.current ??= await uploadFile(
        selected.current,
        "task_attachment",
        setPercent,
      );
      const completed = await completeFile(uploaded.current.id);
      onComplete(completed.id);
      uploaded.current = null;
      selected.current = null;
      setPercent(100);
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      lock.current = false;
      setBusy(false);
      onBusy(false);
    }
  }
  return (
    <div className="workspace-upload-control">
      <label className="workspace-upload-label">
        {label} (mỗi tệp tối đa 5 MB)
        <input
          type="file"
          disabled={busy}
          onChange={(e) => {
            const file = e.target.files?.[0];
            e.target.value = "";
            if (file) {
              selected.current = file;
              uploaded.current = null;
              void run();
            }
          }}
        />
      </label>
      {busy && (
        <progress aria-label="Tiến độ tải file" value={percent} max={100} />
      )}
      <p role="status">{percent}%</p>
      {error && (
        <div role="alert">
          <p>{error}</p>
          <Button variant="outline" disabled={busy} onClick={run}>
            Thử tải lại
          </Button>
        </div>
      )}
    </div>
  );
}
