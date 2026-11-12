import type { ReactNode } from "react";
import { notFound } from "next/navigation";
import { ProgramWorkspaceShell } from "@/components/program-workspace-shell";

export default async function ProgramLayout({ children, params }: { children: ReactNode; params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^\d+$/.test(id) || !Number.isSafeInteger(Number(id)) || Number(id) < 1) notFound();
  return <ProgramWorkspaceShell id={Number(id)}>{children}</ProgramWorkspaceShell>;
}
