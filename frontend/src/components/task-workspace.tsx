"use client";
import { useCallback, useState } from "react";
import { AppShell, useActor } from "./app-shell";
import { ApiState } from "./api-state";
import { Card, Button } from "./ui";
import { WorkspaceFiles } from "./workspace-files";
import { WorkspaceForm } from "./workspace-form";
import { WorkspaceNavigation } from "./workspace-navigation";
import {
  workspaceRead,
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
  return (
    <AppShell title="Bài nộp">
      <TaskContent programId={programId} id={id} />
    </AppShell>
  );
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
  return (
    <section className="content-stack workspace-page">
      <WorkspaceNavigation id={programId} active="tasks" />
      <h1>Bài nộp & phản hồi</h1>
      <ApiState {...result} />
      {d && (
        <>
          <Card>
            <h2>{d.task.title}</h2>
            <p>{d.task.description}</p>
            <WorkspaceFiles ids={d.task.file_ids ?? []} />
            <p>{d.task.status}</p>
            {d.submission && (
              <>
                <h3>Bài nộp phiên bản {d.submission.submission_version}</h3>
                <p style={{ whiteSpace: "pre-wrap" }}>{d.submission.content}</p>
                <p>
                  {d.submission.status} · {d.submission.feedback}
                </p>
              </>
            )}
            {!terminal &&
              actor?.id === d.task.assigned_to &&
              !["completed", "cancelled"].includes(d.task.status ?? "") && (
                <WorkspaceForm
                  key={d.submission?.submission_version ?? 0}
                  url={`/tasks/${id}/submission`}
                  method="PUT"
                  fileUpload
                  fields={[
                    {
                      name: "content",
                      label: "Nội dung bài nộp",
                      type: "textarea",
                      required: true,
                    },
                  ]}
                  initial={{ content: d.submission?.content ?? "" }}
                  fixed={{
                    expected_submission_version:
                      d.submission?.submission_version ?? 0,
                    file_ids: d.submission?.file_ids ?? [],
                  }}
                  label="Nộp phiên bản mới"
                  onSaved={result.reload}
                />
              )}
            {!terminal &&
              d.program.allowed_actions.length > 0 &&
              d.submission?.status === "submitted" && (
                <div className="form-grid">
                  {["review", "request-revision"].map((action) => (
                    <WorkspaceForm
                      key={`${action}-${d.submission?.submission_version}`}
                      url={`/tasks/${id}/submission/${action}`}
                      fields={[
                        {
                          name: "feedback",
                          label: "Phản hồi",
                          type: "textarea",
                          required: action === "request-revision",
                          maxLength: 5000,
                        },
                      ]}
                      fixed={{
                        expected_submission_version:
                          d.submission?.submission_version,
                      }}
                      label={
                        action === "review"
                          ? "Duyệt bài nộp"
                          : "Yêu cầu sửa bài"
                      }
                      onSaved={result.reload}
                    />
                  ))}
                </div>
              )}
          </Card>
          <SubmissionHistory id={id} />
        </>
      )}
    </section>
  );
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
    <Card>
      <h2>Lịch sử phiên bản</h2>
      <ApiState {...result} />
      {result.data && (
        <>
          {result.data.data.map((v) => (
            <article key={v.id}>
              <h3>
                Phiên bản {v.submission_version} · {v.status}
              </h3>
              <p style={{ whiteSpace: "pre-wrap" }}>{v.content}</p>
              <p>{v.feedback}</p>
              <WorkspaceFiles ids={v.file_ids ?? []} />
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
