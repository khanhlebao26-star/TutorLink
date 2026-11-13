"use client";

import { AppShell } from "@/components/layout/app-shell";
import { ProgramForm } from "@/components/programs/program-form";
import { ApiState } from "@/components/shared/api-state";
import { StatePanel } from "@/components/shared/ui";
import {
  getProgram
} from "@/lib/data/collaboration";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import "@/styles/listings.css";
import "@/styles/programs.css";
import Link from "next/link";
import { useCallback } from "react";

export function ProgramEditor({
  id,
  customerId,
  sourceListingId,
}: {
  id?: number;
  customerId?: number;
  sourceListingId?: number;
}) {
  const load = useCallback(
    () => (id ? getProgram(id) : Promise.resolve(null)),
    [id],
  );
  const result = useAsyncData(load);
  const content = (
    <section className="owner-form-page program-editor-page">
      <Link href="/programs" className="program-editor-back">← Chương trình học</Link>
      <ApiState {...result} />
      {!result.loading &&
        !result.error &&
        (result.data && !result.data.allowed_actions.includes("edit") ? (
          <StatePanel
            kind="error"
            message="Bản nháp đang khóa hoặc bạn không có quyền chỉnh sửa."
          />
        ) : (
          <ProgramForm
            key={result.data?.version ?? "new"}
            existing={result.data}
            customerId={customerId}
            sourceListingId={sourceListingId}
          />
        ))}
    </section>
  );
  return id ? content : <AppShell role="tutor" title="Tạo Program">{content}</AppShell>;
}
