import { WorkspaceUpload } from "@/components/programs/workspace-upload";
import { WorkspaceFiles } from "@/components/programs/workspace-files";
import { ApiClientError, request } from "@/lib/api/client";
import { completeFile, uploadFile } from "@/lib/data/services";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/data/services", async (original) => ({
  ...(await original<typeof import("@/lib/data/services")>()),
  uploadFile: vi.fn(),
  completeFile: vi.fn(),
}));
vi.mock("@/lib/api/client", async (original) => ({
  ...(await original<typeof import("@/lib/api/client")>()),
  request: vi.fn(),
}));

beforeEach(() => vi.clearAllMocks());

it("retries completing an uploaded file without uploading it twice", async () => {
  const onComplete = vi.fn();
  const onBusy = vi.fn();
  const file = new File(["essay"], "essay.pdf", { type: "application/pdf" });
  vi.mocked(uploadFile).mockImplementation(async (_file, purpose, onProgress) => {
    expect(purpose).toBe("task_attachment");
    onProgress?.(55);
    return { id: 42, complete: false } as never;
  });
  vi.mocked(completeFile)
    .mockRejectedValueOnce(new ApiClientError("File completion failed"))
    .mockResolvedValueOnce({ id: 42, complete: true } as never);

  render(<WorkspaceUpload onComplete={onComplete} onBusy={onBusy} />);
  await userEvent.upload(screen.getByLabelText(/Đính kèm bài nộp/), file);

  expect(await screen.findByRole("alert")).toHaveTextContent("File completion failed");
  expect(screen.getByRole("button", { name: "Thử tải lại" })).toBeEnabled();
  await userEvent.click(screen.getByRole("button", { name: "Thử tải lại" }));

  expect(await screen.findByText("100%")).toBeVisible();
  expect(uploadFile).toHaveBeenCalledTimes(1);
  expect(completeFile).toHaveBeenNthCalledWith(1, 42);
  expect(completeFile).toHaveBeenNthCalledWith(2, 42);
  expect(onComplete).toHaveBeenCalledWith(42);
  expect(onBusy).toHaveBeenNthCalledWith(1, true);
  expect(onBusy).toHaveBeenLastCalledWith(false);
});

it("downloads an authorized file and surfaces a download error", async () => {
  const click = vi.fn();
  const anchors: HTMLAnchorElement[] = [];
  const originalCreateElement = document.createElement.bind(document);
  vi.spyOn(document, "createElement").mockImplementation((tagName: string) => {
    const element = originalCreateElement(tagName);
    if (tagName === "a") {
      (element as HTMLAnchorElement).click = click;
      anchors.push(element as HTMLAnchorElement);
    }
    return element as HTMLElement;
  });
  Object.defineProperty(URL, "createObjectURL", { configurable: true, value: vi.fn(() => "blob:file-12") });
  vi.mocked(request).mockResolvedValueOnce({ data: new Blob(["contents"]) } as never);

  render(<WorkspaceFiles ids={[12]} />);
  await userEvent.click(screen.getByRole("button", { name: "Tải file #12" }));

  expect(request).toHaveBeenCalledWith({ url: "/files/12/download", responseType: "blob" });
  expect(anchors[0].href).toContain("blob:file-12");
  expect(anchors[0].download).toBe("tutorlink-file-12");
  expect(click).toHaveBeenCalledOnce();

  vi.mocked(request).mockRejectedValueOnce(new ApiClientError("Download denied", { status: 403 }));
  render(<WorkspaceFiles ids={[13]} />);
  await userEvent.click(screen.getByRole("button", { name: "Tải file #13" }));
  expect(await screen.findByRole("alert")).toHaveTextContent("Download denied");
});
