"use client";
import Link from "next/link";
import { useCallback, useRef, useState } from "react";
import { useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Badge, Card, Button } from "./ui";
import { WorkspaceFiles } from "./workspace-files";
import { WorkspaceForm } from "./workspace-form";
import { describeApiError } from "@/lib/api/client";
import {
  workspaceRead,
  workspaceWrite,
  retryIdentity,
  type WorkspaceRow,
  type Collection,
} from "@/lib/data/workspace";
import { getProgram } from "@/lib/data/collaboration";
import { useAsyncData } from "@/lib/hooks/use-async-data";
export function TaskWorkspace({
  programId,
  id,
}: {
  programId: number;
  id: number;
}) {
  return <TaskContent key={id} programId={programId} id={id} />;
}
function TaskContent({ programId, id }: { programId: number; id: number }) {
  const actor = useActor();
  const load = useCallback(async () => {
    const task = (await workspaceRead<{ data: WorkspaceRow }>(`/tasks/${id}`))
      .data;
    if (task.program_id !== undefined && task.program_id !== programId)
      throw new Error("Bài tập không thuộc Program này.");
    return {
      task,
      program: await getProgram(programId),
      submission: (
        await workspaceRead<{ data: WorkspaceRow | null }>(
          `/tasks/${id}/submission`,
        )
      ).data,
    };
  }, [id, programId]);
  const result = useAsyncData(load);
  const d = result.data;
  const terminal = d && ["completed", "cancelled"].includes(d.program.status);
  const taskStatus = d?.task.status === "todo" ? "Chưa bắt đầu"
    : d?.task.status === "in_progress" ? "Đang làm"
      : d?.task.status === "submitted" ? "Đã nộp · chờ gia sư xem"
        : d?.task.status === "completed" ? "Đã hoàn thành"
          : d?.task.status === "cancelled" ? "Đã hủy" : "Bài tập";
  return (
    <section className="content-stack workspace-page task-workspace-page">
      <ApiState {...result} />
      {d && (
        <>
          <header className="task-detail-page-header">
            <Link className="task-workspace-back" href={`/programs/${programId}/tasks`}><span aria-hidden="true">←</span> Quay lại bài tập</Link>
            <div className="task-detail-title-row"><div><p className="task-detail-eyebrow">CHƯƠNG TRÌNH · {d.program.title}</p><h1>Bài nộp &amp; phản hồi</h1><p className="task-detail-context">Bài tập: <strong>{d.task.title}</strong> <span>·</span> Mã bài: #{d.task.id}</p></div><Badge tone={d.task.status === "completed" ? "success" : d.task.status === "cancelled" ? "danger" : d.task.status === "in_progress" ? "warning" : d.task.status === "submitted" ? "brand" : "brand"}>{taskStatus}</Badge></div>
          </header>

          <Card className="task-detail-card task-prompt-card">
            <div className="task-detail-heading"><div><p className="task-detail-eyebrow">ĐỀ BÀI</p><h2>{d.task.title}</h2></div>{d.task.due_at ? <span className="task-detail-due">Hạn nộp: {new Date(d.task.due_at).toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" })}</span> : <span className="task-detail-due">Chưa đặt hạn nộp</span>}</div>
            {d.task.description ? <p className="task-detail-description">{d.task.description}</p> : <p className="task-detail-description task-detail-empty-description">Gia sư chưa thêm hướng dẫn cho bài tập này.</p>}
            {d.task.file_ids?.length ? <div className="task-detail-files"><strong>Đề và tài liệu hướng dẫn</strong><WorkspaceFiles ids={d.task.file_ids} /></div> : null}
          </Card>

          <Card className="task-detail-card task-latest-card">
            <div className="task-submission-heading"><div><p className="task-detail-eyebrow">BÀI NỘP CỦA HỌC VIÊN</p><h2>{d.submission ? `Lần nộp ${d.submission.submission_version ?? 1}` : "Chưa có bài nộp"}</h2></div>{d.submission ? <Badge tone={d.submission.status === "submitted" ? "warning" : d.submission.status === "accepted" ? "success" : "brand"}>{submissionStatusLabel(d.submission.status)}</Badge> : null}</div>
            {d.submission ? <>
              {d.submission.created_at ? <p className="task-submission-version">Đã gửi {new Date(d.submission.created_at).toLocaleString("vi-VN", { dateStyle: "medium", timeStyle: "short" })}</p> : null}
              {d.submission.content ? <p className="task-submission-content">{d.submission.content}</p> : <p className="task-submission-content task-detail-empty-description">Không có nội dung văn bản.</p>}
              {d.submission.file_ids?.length ? <div className="task-detail-files"><strong>Tệp học viên đính kèm</strong><WorkspaceFiles ids={d.submission.file_ids} /></div> : null}
              {d.submission.feedback ? <p className="task-submission-feedback"><strong>Phản hồi của gia sư</strong><span>{d.submission.feedback}</span></p> : null}
            </> : <p className="task-detail-description">Học viên chưa gửi bài. Bài nộp sẽ xuất hiện tại đây.</p>}
          </Card>

          {!terminal && actor?.id === d.task.assigned_to && !["completed", "cancelled"].includes(d.task.status ?? "") ? <Card className="task-detail-card task-student-submit-card">
            <div><p className="task-detail-eyebrow">GỬI BÀI LÀM</p><h2>{d.submission ? "Gửi phiên bản mới" : "Nộp bài tập"}</h2><p className="task-detail-description">Viết nội dung bài làm và đính kèm tệp nếu cần.</p></div>
            <WorkspaceForm key={d.submission?.submission_version ?? 0} url={`/tasks/${id}/submission`} method="PUT" fileUpload fields={[{ name: "content", label: "Nội dung bài nộp", type: "textarea", required: true }]} initial={{ content: d.submission?.content ?? "" }} fixed={{ expected_submission_version: d.submission?.submission_version ?? 0, file_ids: d.submission?.file_ids ?? [] }} label="Nộp phiên bản mới" onSaved={result.reload} />
          </Card> : null}

          {!terminal && d.program.allowed_actions.length > 0 && d.submission?.status === "submitted" ? <Card className="task-detail-card task-review-card"><TaskReviewActions id={id} submissionVersion={d.submission.submission_version ?? 1} onSaved={result.reload} /></Card> : null}
          <SubmissionHistory id={id} />
        </>
      )}
    </section>
  );
}

function submissionStatusLabel(status?: string | null) {
  if (status === "submitted") return "Chờ gia sư xem";
  if (status === "accepted") return "Đã duyệt";
  if (status === "revision_requested") return "Cần chỉnh sửa";
  return "Bản nháp";
}

function TaskReviewActions({ id, submissionVersion, onSaved }: { id: number; submissionVersion: number; onSaved: () => void }) {
  const [feedback, setFeedback] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const receipt = useRef<{ payload: string; key: string } | null>(null);

  async function decide(decision: "review" | "request-revision") {
    if (busy) return;
    if (decision === "request-revision" && !feedback.trim()) {
      setError("Hãy ghi rõ nội dung học viên cần chỉnh sửa.");
      return;
    }
    setBusy(true);
    setError("");
    const url = `/tasks/${id}/submission/${decision}`;
    const payload = { expected_submission_version: submissionVersion, feedback: feedback.trim() || null };
    receipt.current = retryIdentity(receipt.current, { url, method: "POST", payload });
    try {
      await workspaceWrite(url, "POST", payload, receipt.current.key);
      onSaved();
    } catch (e) {
      setError(describeApiError(e));
    } finally {
      setBusy(false);
    }
  }

  return <section className="task-review-panel">
    <h3>Phản hồi bài nộp</h3>
    <label htmlFor={`task-feedback-${id}`}>Nhận xét cho học viên</label>
    <textarea id={`task-feedback-${id}`} className="input" rows={3} maxLength={5000} value={feedback} onChange={(event) => setFeedback(event.target.value)} placeholder="Viết nhận xét hoặc hướng dẫn học viên sửa bài…" disabled={busy} />
    <p className="task-review-hint">Nhận xét không bắt buộc khi duyệt bài, nhưng cần có nếu yêu cầu chỉnh sửa.</p>
    {error ? <p className="task-review-error" role="alert">{error}</p> : null}
    <div className="task-review-buttons">
      <Button disabled={busy} onClick={() => void decide("review")}>{busy ? "Đang lưu…" : "Duyệt bài nộp"}</Button>
      <Button variant="outline" disabled={busy} onClick={() => void decide("request-revision")}>Yêu cầu chỉnh sửa</Button>
    </div>
  </section>;
}

function SubmissionHistory({ id }: { id: number }) {
  const [page, setPage] = useState(1);
  const load = useCallback(
    () =>
      workspaceRead<Collection<WorkspaceRow>>(
        `/tasks/${id}/submission/versions`,
        { page },
      ),
    [id, page],
  );
  const result = useAsyncData(load);
  return (
    <Card className="task-submission-history">
      <h3>Các lần nộp trước</h3>
      <ApiState {...result} />
      {result.data && (
        <>
          {result.data.data.map((v) => (
            <article className="task-history-entry" key={v.id}>
              <div className="task-submission-heading"><strong>Lần nộp {v.submission_version}</strong><Badge tone={v.status === "submitted" ? "warning" : v.status === "accepted" ? "success" : "brand"}>{submissionStatusLabel(v.status)}</Badge></div>
              {v.content ? <p>{v.content}</p> : null}
              {v.feedback ? <p className="task-history-feedback">Phản hồi: {v.feedback}</p> : null}
              {v.file_ids?.length ? <WorkspaceFiles ids={v.file_ids} /> : null}
            </article>
          ))}
          <nav className="button-row" aria-label="Phân trang lịch sử bài nộp">
            <Button
              variant="outline"
              disabled={page === 1}
              onClick={() => setPage(page - 1)}
            >
              Trước
            </Button>
            <span>Trang {page}</span>
            <Button
              variant="outline"
              disabled={
                page >=
                (result.data.meta?.last_page ??
                  Math.max(
                    1,
                    Math.ceil(
                      (result.data.meta?.total ?? 0) /
                        (result.data.meta?.per_page ?? 20),
                    ),
                  ))
              }
              onClick={() => setPage(page + 1)}
            >
              Sau
            </Button>
          </nav>
        </>
      )}
    </Card>
  );
}
