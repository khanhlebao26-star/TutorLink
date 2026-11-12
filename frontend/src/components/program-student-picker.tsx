"use client";

import Link from "next/link";
import { useCallback } from "react";
import { conversations, type Conversation } from "@/lib/data/messaging";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { describeApiError } from "@/lib/api/client";
import { Button, Select } from "./ui";

export function ProgramStudentPicker({ value, disabled, onChange }: {
  value: number;
  disabled: boolean;
  onChange: (studentId: number, listingId: number | null) => void;
}) {
  const load = useCallback(async () => {
    const students = new Map<number, Conversation>();
    const cursors = new Set<string>();
    let cursor: string | undefined;
    do {
      const page = await conversations(cursor);
      for (const item of page.data) {
        if (item.status === "active" && !students.has(item.peer.id)) students.set(item.peer.id, item);
      }
      if (!page.meta.has_more || !page.meta.next_cursor || cursors.has(page.meta.next_cursor)) break;
      cursor = page.meta.next_cursor;
      cursors.add(cursor);
    } while (cursor);
    return [...students.values()];
  }, []);
  const result = useAsyncData(load);
  const selected = result.data?.find((item) => item.peer.id === value);

  return <div className="program-student-picker">
    <Select id="program-customer" required value={value || ""} disabled={disabled || result.loading || Boolean(result.error)} onChange={(event) => {
      const id = Number(event.target.value);
      const item = result.data?.find((student) => student.peer.id === id);
      onChange(id, item?.context_listing_id ?? null);
    }}>
      <option value="">{result.loading ? "Đang tải học viên từ cuộc trò chuyện…" : "Chọn học viên bạn đã trò chuyện"}</option>
      {value > 0 && !selected ? <option value={value}>{disabled ? "Học viên đã gắn với chương trình" : "Học viên từ cuộc trò chuyện đã chọn"}</option> : null}
      {result.data?.map((item) => <option key={item.peer.id} value={item.peer.id}>{item.peer.display_name}</option>)}
    </Select>
    {result.loading ? <small role="status">Đang lấy danh sách học viên…</small> : result.error ? <div role="alert"><p className="field-error">{describeApiError(result.error)}</p><Button variant="outline" onClick={result.reload}>Tải lại danh sách</Button></div> : !result.data?.length && !value ? <small>Chưa có cuộc trò chuyện với học viên. <Link href="/messages">Mở tin nhắn →</Link></small> : null}
  </div>;
}
