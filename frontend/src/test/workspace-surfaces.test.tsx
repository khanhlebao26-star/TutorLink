import { render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { WorkspaceTabPage } from "@/components/workspace-tabs";
import { Notifications } from "@/components/notifications";
import { getProgram } from "@/lib/data/collaboration";
import { workspaceRead } from "@/lib/data/workspace";
import { ApiClientError } from "@/lib/api/client";
vi.mock("@/components/app-shell", () => ({
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useActor: () => ({ id: 4, role: "tutor" }),
}));
vi.mock("@/lib/data/collaboration", () => ({ getProgram: vi.fn() }));
vi.mock("@/lib/data/workspace", async (original) => ({
  ...(await original<typeof import("@/lib/data/workspace")>()),
  workspaceRead: vi.fn(),
}));
beforeEach(() => vi.clearAllMocks());
it("Paused allows existing Session complete/cancel but no create or edit", async () => {
  vi.mocked(getProgram).mockResolvedValue({
    id: 8,
    title: "Paused lessons",
    status: "paused",
    allowed_actions: ["resume"],
  } as never);
  vi.mocked(workspaceRead).mockResolvedValue({
    data: [
      {
        id: 12,
        version: 2,
        title: "Planned lesson",
        status: "scheduled",
        actual: null,
      },
    ],
    meta: { last_page: 1 },
  } as never);
  render(<WorkspaceTabPage id={8} tab="sessions" />);
  await screen.findByRole("heading", { name: "Planned lesson" });
  expect(
    screen.getByRole("button", { name: "Hoàn thành buổi học" }),
  ).toBeEnabled();
  expect(screen.getByRole("button", { name: "Hủy lịch học" })).toBeEnabled();
  expect(screen.queryByText("Tạo mới", { exact: true })).toBeNull();
  expect(screen.queryByText("Chỉnh sửa lịch", { exact: true })).toBeNull();
  expect(screen.getByText("Chưa có buổi học thực tế")).toBeVisible();
});
it("terminal Program never exposes Session mutations", async () => {
  vi.mocked(getProgram).mockResolvedValue({
    id: 8,
    title: "Closed lessons",
    status: "completed",
    allowed_actions: [],
  } as never);
  vi.mocked(workspaceRead).mockResolvedValue({
    data: [
      {
        id: 12,
        version: 3,
        title: "Finished lesson",
        status: "completed",
        actual: { id: 91, status: "completed" },
      },
    ],
    meta: { last_page: 1 },
  } as never);
  render(<WorkspaceTabPage id={8} tab="sessions" />);
  await screen.findByText("Actual #91 · completed");
  expect(
    screen.queryByRole("button", { name: "Hoàn thành buổi học" }),
  ).toBeNull();
});
it("lost notification access produces a forbidden state without a content link", async () => {
  vi.mocked(workspaceRead).mockRejectedValue(
    new ApiClientError("Denied", { status: 403 }),
  );
  render(<Notifications />);
  expect(
    await screen.findByText(/Bạn không có quyền thực hiện thao tác này/),
  ).toBeVisible();
  expect(screen.queryByRole("link", { name: "Mở nội dung →" })).toBeNull();
});
