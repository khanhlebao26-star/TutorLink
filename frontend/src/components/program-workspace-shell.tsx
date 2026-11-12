"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import { AppShell } from "./app-shell";
import { WorkspaceNavigation, workspaceTabs, type WorkspaceTab } from "./workspace-navigation";
import "@/app/workspace.css";

export function ProgramWorkspaceShell({ id, children }: { id: number; children: ReactNode }) {
  const pathname = usePathname();
  const segment = pathname.split("/")[3];
  const editing = segment === "edit";
  const active: WorkspaceTab = segment && segment in workspaceTabs ? segment as WorkspaceTab : "overview";
  return (
    <AppShell title={editing ? "Chỉnh sửa chương trình" : workspaceTabs[active]} role={editing ? "tutor" : undefined}>
      {!editing && <div className="content-stack workspace-page program-workspace-navigation">
        <Link className="program-workspace-back" href="/programs">← Program của tôi</Link>
        <WorkspaceNavigation id={id} active={active} />
      </div>}
      {children}
    </AppShell>
  );
}
