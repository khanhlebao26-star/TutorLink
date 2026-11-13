"use client";

import { WorkspaceForm } from "@/components/programs/workspace-form";
import { taskFields } from "@/components/programs/workspace-form-fields";
import { Button, Card } from "@/components/shared/ui";
import "@/styles/programs.css";
import { useState } from "react";

export function TaskCreateDialog({ id, programTitle, customerId, onSaved }: { id: number; programTitle: string; customerId: number; onSaved: () => void }) {
  const [open, setOpen] = useState(false);
  return <>
    <Card className="workspace-task-create">
      <div className="workspace-task-create-row"><div><h2>Giao bài tập mới</h2><p>Thêm hướng dẫn và tài liệu để học viên bắt đầu làm bài.</p></div><Button onClick={() => setOpen(true)}>＋ Giao bài tập mới</Button></div>
    </Card>
    {open ? <div className="session-detail-backdrop workspace-task-dialog-backdrop" onClick={() => setOpen(false)}>
      <section className="session-detail-dialog workspace-task-dialog" role="dialog" aria-modal="true" aria-labelledby={`task-create-title-${id}`} onClick={(event) => event.stopPropagation()}>
        <button className="session-detail-close" type="button" onClick={() => setOpen(false)} aria-label="Đóng form giao bài tập">×</button>
        <p className="session-detail-eyebrow">BÀI TẬP · {programTitle}</p>
        <h2 id={`task-create-title-${id}`}>Giao bài tập mới</h2>
        <p className="workspace-task-dialog-hint">Điền tên bài, hướng dẫn và hạn nộp nếu có. Học viên sẽ xem được bài và tài liệu trong chương trình.</p>
        <WorkspaceForm
          url={`/programs/${id}/tasks`}
          fields={taskFields}
          fixed={{ assigned_to: customerId }}
          fileUpload
          fileUploadLabel="Tải đề hoặc tài liệu hướng dẫn"
          label="Giao bài tập"
          onSaved={() => { onSaved(); setOpen(false); }}
        />
      </section>
    </div> : null}
  </>;
}
