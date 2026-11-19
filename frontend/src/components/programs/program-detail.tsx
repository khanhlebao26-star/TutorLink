"use client";

import { ProgramOverview } from "@/components/programs/program-overview";
import { ProgramTrust } from "@/components/programs/trust-workspace";
import { ApiState } from "@/components/shared/api-state";
import { Button, Card } from "@/components/shared/ui";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import {
  getOverview,
  getProgram,
  mutationKey,
  transitionProgram
} from "@/lib/data/collaboration";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/styles/listings.css";
import "@/styles/programs.css";
import { useCallback, useRef, useState } from "react";

export function ProgramDetail({ id }: { id: number }) {
  const load = useCallback(async () => {
    const program = await getProgram(id);
    return {
      program,
      overview: program.status === "draft" ? null : await getOverview(id),
    };
  }, [id]);
  const result = useAsyncData(load);
  const [error, setError] = useState(""),
    [busy, setBusy] = useState(false);
  const key = useRef<{ action: string; version: number; key: string } | null>(
    null,
  );
  async function act(action: string) {
    if (!result.data || busy) return;
    const p = result.data.program;
    setBusy(true);
    setError("");
    if (
      !key.current ||
      key.current.action !== action ||
      key.current.version !== p.version
    )
      key.current = { action, version: p.version, key: mutationKey() };
    try {
      await transitionProgram(id, action, key.current.key);
      key.current = null;
      result.reload();
    } catch (e) {
      setError(describeApiError(e) + (e instanceof ApiClientError && e.blockers ? ` Còn buổi học: ${e.blockers.sessions.join(", ") || "không"}; bài tập: ${e.blockers.tasks.join(", ") || "không"}.` : ""));
    } finally {
      setBusy(false);
    }
  }
  return (
    <section className="content-stack workspace-page program-overview-page">
      <ApiState {...result} />
      {error && (
        <p role="alert" className="field-error">
          {error}
        </p>
      )}
      {result.data && (
        <>
          <ProgramOverview program={result.data.program} overview={result.data.overview} />
          {result.data.program.status === "completed" && <ProgramTrust id={id} />}
          {result.data.program.allowed_actions.some(
            (a) => a !== "edit" && a !== "send_invitation",
          ) && (
              <Card>
                <h2>Trạng thái chương trình</h2>
                <div className="button-row">
                  {result.data.program.allowed_actions
                    .filter((a) => a !== "edit" && a !== "send_invitation")
                    .map((a) => (
                      <Button
                        key={a}
                        disabled={busy}
                        variant={a === "cancel" ? "danger" : "outline"}
                        onClick={() => act(a)}
                      >
                        {{
                          pause: "Tạm dừng",
                          resume: "Tiếp tục",
                          complete: "Hoàn thành",
                          cancel: "Hủy Program",
                        }[a] || a}
                      </Button>
                    ))}
                </div>
                <Button variant="ghost" disabled={busy} onClick={result.reload}>
                  Tải trạng thái mới nhất
                </Button>
              </Card>
            )}
        </>
      )}
    </section>
  );
}
