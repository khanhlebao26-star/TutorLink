import { render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
import { WorkspaceTabPage } from "@/components/workspace-tabs";
import { useActor } from "@/components/app-shell";
import { Notifications } from "@/components/notifications";
import { getProgram } from "@/lib/data/collaboration";
import { workspaceRead } from "@/lib/data/workspace";
import { ApiClientError } from "@/lib/api/client";
vi.mock("@/components/app-shell", () => ({
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useActor: vi.fn(),
}));
vi.mock("@/lib/data/collaboration", () => ({ getProgram: vi.fn() }));
vi.mock("@/lib/data/workspace", async (original) => ({
  ...(await original<typeof import("@/lib/data/workspace")>()),
  workspaceRead: vi.fn(),
}));
beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(useActor).mockReturnValue({ id: 4, role: "tutor" } as never);
});
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

it.each([
  ["tutor", "all", "draft"],
  ["customer", "published", "published"],
] as const)("loads announcements for the %s scope", async (role, queryState, rowState) => {
  vi.mocked(useActor).mockReturnValue({ id: role === "tutor" ? 4 : 5, role } as never);
  vi.mocked(getProgram).mockResolvedValue({
    id: 8,
    title: "Lessons",
    status: "active",
    allowed_actions: role === "tutor" ? ["pause"] : [],
  } as never);
  vi.mocked(workspaceRead).mockResolvedValue({
    data: [{ id: 12, version: 1, author_id: 4, title: "Class update", state: rowState }],
    meta: { last_page: 1 },
  } as never);

  render(<WorkspaceTabPage id={8} tab="announcements" />);

  await screen.findByRole("heading", { name: "Class update" });
  expect(workspaceRead).toHaveBeenCalledWith("/programs/8/announcements", { page: 1, state: queryState });
  const publish = screen.queryByRole("button", { name: "Đăng thông báo" });
  if (role === "tutor") expect(publish).toBeEnabled();
  else expect(publish).toBeNull();
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
