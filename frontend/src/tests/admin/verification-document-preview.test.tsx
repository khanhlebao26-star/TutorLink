import { VerificationDocumentPreview } from "@/components/admin/verification-document-preview";
import { ApiClientError, request } from "@/lib/api/client";
import type { VerificationDocument } from "@/lib/data/types";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AxiosHeaders } from "axios";
import { beforeEach, expect, it, vi } from "vitest";

vi.mock("@/lib/api/client", async original => ({ ...await original<typeof import("@/lib/api/client")>(), request: vi.fn() }));
const file: VerificationDocument = { id: 8, file_id: 44, tutor_profile_id: 43, document_type: "identity_card", status: "pending", original_name: "CCCD.jpg", mime_type: "image/jpeg", download_url: "http://localhost:8000/api/v1/files/44/download" };
const response = (mime: string) => ({ status: 200, statusText: "OK", headers: {}, config: { headers: new AxiosHeaders() }, data: new Blob(["private-file"], { type: mime }) });

beforeEach(() => {
  vi.clearAllMocks();
  Object.defineProperty(URL, "createObjectURL", { configurable: true, value: vi.fn().mockReturnValue("blob:private-document") });
  Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: vi.fn() });
  Object.defineProperty(HTMLDialogElement.prototype, "showModal", { configurable: true, value: function (this: HTMLDialogElement) { this.setAttribute("open", ""); } });
  Object.defineProperty(HTMLDialogElement.prototype, "close", { configurable: true, value: function (this: HTMLDialogElement) { this.removeAttribute("open"); } });
  vi.mocked(request).mockResolvedValue(response("image/jpeg"));
});

it("loads through the authenticated API client and displays a local image instead of opening the API URL", async () => {
  const close = vi.fn();
  const view = render(<VerificationDocumentPreview file={file} onClose={close} />);
  expect(screen.getByRole("dialog", { name: "CCCD.jpg" })).toBeVisible();
  expect(await screen.findByRole("img", { name: "Nội dung CCCD.jpg" })).toHaveAttribute("src", "blob:private-document");
  expect(request).toHaveBeenCalledWith(expect.objectContaining({ url: "/files/44/download", responseType: "blob", signal: expect.any(AbortSignal) }));
  expect(screen.getByRole("link", { name: "Tải tài liệu" })).toHaveAttribute("download", "CCCD.jpg");
  expect(screen.getByRole("link", { name: "Tải tài liệu" })).not.toHaveAttribute("target");
  await userEvent.click(screen.getByRole("button", { name: "Đóng tài liệu" }));
  expect(close).toHaveBeenCalledOnce();
  view.unmount();
  expect(URL.revokeObjectURL).toHaveBeenCalledWith("blob:private-document");
});

it("displays PDFs inside the popup even when the download transport uses octet-stream", async () => {
  vi.mocked(request).mockResolvedValue(response("application/octet-stream"));
  render(<VerificationDocumentPreview file={{ ...file, original_name: "Chứng chỉ.pdf", mime_type: "application/pdf" }} onClose={vi.fn()} />);
  expect(await screen.findByTitle("Nội dung Chứng chỉ.pdf")).toHaveAttribute("src", "blob:private-document");
});

it("shows authentication errors in the popup and supports retrying", async () => {
  vi.mocked(request).mockRejectedValueOnce(new ApiClientError("Unauthenticated", { status: 401 })).mockResolvedValueOnce(response("image/jpeg"));
  render(<VerificationDocumentPreview file={file} onClose={vi.fn()} />);
  expect(await screen.findByRole("alert")).toHaveTextContent("Hãy đăng nhập lại");
  expect(URL.createObjectURL).not.toHaveBeenCalled();
  await userEvent.click(screen.getByRole("button", { name: "Thử lại" }));
  expect(await screen.findByRole("img")).toHaveAttribute("src", "blob:private-document");
});

it("aborts an in-flight request when closed and does not create a URL for its late response", async () => {
  let finish!: (value: ReturnType<typeof response>) => void;
  vi.mocked(request).mockReturnValue(new Promise(resolve => { finish = resolve; }));
  const view = render(<VerificationDocumentPreview file={file} onClose={vi.fn()} />);
  const config = vi.mocked(request).mock.calls[0][0];
  view.unmount();
  expect(config.signal?.aborted).toBe(true);
  finish(response("image/jpeg"));
  await waitFor(() => expect(URL.createObjectURL).not.toHaveBeenCalled());
});

it("keeps unsupported document types downloadable and handles the dialog cancel action", async () => {
  vi.mocked(request).mockResolvedValue(response("application/msword"));
  const close = vi.fn();
  render(<VerificationDocumentPreview file={{ ...file, original_name: "Bằng cấp.doc", mime_type: "application/msword" }} onClose={close} />);
  expect(await screen.findByText("Tệp này chưa hỗ trợ xem trực tiếp")).toBeVisible();
  expect(screen.getByRole("link", { name: "Tải tài liệu" })).toHaveAttribute("href", "blob:private-document");
  fireEvent(screen.getByRole("dialog"), new Event("cancel", { bubbles: true, cancelable: true }));
  expect(close).toHaveBeenCalledOnce();
});
