import { useActor } from "@/components/layout/app-shell";
import { Notifications } from "@/components/notifications/notifications";
import { WorkspaceTabPage } from "@/components/programs/workspace-tab-page";
import { ApiClientError } from "@/lib/api/client";
import { getProgram, getOverview } from "@/lib/data/collaboration";
import { workspaceRead } from "@/lib/data/workspace";
import userEvent from "@testing-library/user-event";
import { render, screen } from "@testing-library/react";
import { beforeEach, expect, it, vi } from "vitest";
vi.mock("@/components/layout/app-shell", () => ({
  AppShell: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useActor: vi.fn(),
}));
vi.mock("@/lib/data/collaboration", () => ({ getProgram: vi.fn(), getOverview: vi.fn() }));
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
  await userEvent.click(await screen.findByRole("button", { name: /Planned lesson/ }));
  await userEvent.click(screen.getByText("Thao tác khác"));
  expect(
    screen.getByRole("button", { name: "Hoàn thành buổi học" }),
  ).toBeEnabled();
  expect(screen.getByRole("button", { name: "Hủy lịch học" })).toBeEnabled();
  expect(screen.queryByRole("button", { name: /Lên lịch vào/ })).toBeNull();
  expect(screen.queryByText("Chỉnh sửa lịch", { exact: true })).toBeNull();
  expect(screen.queryByText("Kết quả")).toBeNull();
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
  await userEvent.click(await screen.findByRole("button", { name: /Finished lesson/ }));
  expect(screen.getByText("Kết quả").nextElementSibling).toHaveTextContent("Đã hoàn thành");
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
  expect(workspaceRead).toHaveBeenCalledWith("/programs/8/announcements", { page: 1, state: queryState }, expect.any(AbortSignal));
  const publish = screen.queryByRole("button", { name: "Đăng cho học viên" });
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


it("loads bounded task pages and uses server counters for filters", async () => {
  vi.mocked(getProgram).mockResolvedValue({ id: 8, title: "Lessons", status: "active", allowed_actions: [] } as never);
  vi.mocked(workspaceRead).mockResolvedValue({ data: [{ id: 12, title: "Only visible task", status: "todo", version: 1 }],
    meta: { total: 1000, last_page: 200, per_page: 5, status_counts: { todo: 900, submitted: 100 } } } as never);
  render(<WorkspaceTabPage id={8} tab="tasks" />);
  await screen.findByRole("heading", { name: "Only visible task" });
  expect(workspaceRead).toHaveBeenCalledTimes(1);
  expect(workspaceRead).toHaveBeenCalledWith("/programs/8/tasks", { page: 1, per_page: 5, filter: "all", sort: "due" }, expect.any(AbortSignal));
  await userEvent.click(screen.getByRole("button", { name: "Sau" }));
  await screen.findByText("Trang 2");
  expect(workspaceRead).toHaveBeenLastCalledWith("/programs/8/tasks", { page: 2, per_page: 5, filter: "all", sort: "due" }, expect.any(AbortSignal));
});

it("loads progress counters without fetching task or session histories", async () => {
  vi.mocked(getProgram).mockResolvedValue({ id: 8, title: "Lessons", planned_session_count: 10, status: "active", allowed_actions: [] } as never);
  vi.mocked(getOverview).mockResolvedValue({ task_counts: { todo: 500, completed: 500 }, completed_session_count: 5 } as never);
  vi.mocked(workspaceRead).mockResolvedValue({ data: [], meta: { last_page: 1 } } as never);
  render(<WorkspaceTabPage id={8} tab="progress" />);
  await screen.findByText("TIẾN ĐỘ TỰ ĐỘNG");
  expect(workspaceRead).toHaveBeenCalledTimes(1);
  expect(workspaceRead).toHaveBeenCalledWith("/programs/8/progress-records", { page: 1 }, expect.any(AbortSignal));
  expect(screen.getAllByText("50%")).toHaveLength(2);
});
