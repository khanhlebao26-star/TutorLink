import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
import { WorkspaceForm } from "@/components/workspace-form";
import { workspaceWrite } from "@/lib/data/workspace";
import { ApiClientError } from "@/lib/api/client";
vi.mock("@/lib/data/workspace", async (original) => ({
  ...(await original<typeof import("@/lib/data/workspace")>()),
  workspaceWrite: vi.fn(),
}));
beforeEach(() => vi.mocked(workspaceWrite).mockReset());
it("keeps input on conflict and requires a deliberate reload", async () => {
  const reload = vi.fn();
  vi.mocked(workspaceWrite).mockRejectedValue(
    new ApiClientError("Changed", { status: 409 }),
  );
  render(
    <WorkspaceForm
      url="/tasks/1"
      method="PATCH"
      fields={[{ name: "title", label: "Tiêu đề", required: true }]}
      initial={{ title: "My draft" }}
      fixed={{ expected_version: 2 }}
      label="Lưu"
      onSaved={reload}
    />,
  );
  await userEvent.click(
    screen.getByRole("button", { name: /^Lưu$/ }),
  );
  expect(await screen.findByText("Changed")).toBeVisible();
  expect(screen.getByLabelText("Tiêu đề")).toHaveValue("My draft");
  expect(
    screen.getByRole("button", { name: /^Lưu$/ }),
  ).toBeDisabled();
  expect(reload).not.toHaveBeenCalled();
  await userEvent.click(
    screen.getByRole("button", { name: "Tải dữ liệu mới" }),
  );
  expect(reload).toHaveBeenCalledOnce();
});
it("reuses UUID after a network failure with unchanged content", async () => {
  vi.mocked(workspaceWrite)
    .mockRejectedValueOnce(new Error("offline"))
    .mockResolvedValueOnce({ data: {} });
  render(
    <WorkspaceForm
      url="/sessions/1/complete"
      fixed={{ expected_version: 3 }}
      label="Hoàn thành"
      onSaved={vi.fn()}
    />,
  );
  await userEvent.click(screen.getByRole("button", { name: "Hoàn thành" }));
  await screen.findByText("offline");
  await userEvent.click(screen.getByRole("button", { name: "Hoàn thành" }));
  await waitFor(() => expect(workspaceWrite).toHaveBeenCalledTimes(2));
  expect(vi.mocked(workspaceWrite).mock.calls[0]).toEqual(
    vi.mocked(workspaceWrite).mock.calls[1],
  );
});
