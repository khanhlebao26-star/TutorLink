"use client";
import Link from "next/link";
export const workspaceTabs = {
  overview: "Tổng quan",
  sessions: "Buổi học",
  tasks: "Bài tập",
  progress: "Tiến độ",
  announcements: "Thông báo",
  fees: "Học phí",
};
export type WorkspaceTab = keyof typeof workspaceTabs;
export function WorkspaceNavigation({
  id,
  active,
}: {
  id: number;
  active: WorkspaceTab;
}) {
  return (
    <nav className="program-workspace-links" aria-label="Các tab workspace">
      {Object.entries(workspaceTabs).map(([key, label]) => (
        <Link
          className={`program-workspace-link${active === key ? " is-active" : ""}`}
          aria-current={active === key ? "page" : undefined}
          key={key}
          href={
            key === "overview" ? `/programs/${id}` : `/programs/${id}/${key}`
          }
          prefetch={false}
        >
          {label}
        </Link>
      ))}
    </nav>
  );
}
