import { AuthForm } from "@/components/auth/auth-form";
import { TutorOnboardingForm } from "@/components/tutors/tutor-onboarding-form";
import { ApiClientError } from "@/lib/api/client";
import * as services from "@/lib/data/services";
import type { TutorOnboardingData, TutorProfile } from "@/lib/data/types";
import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, expect, it, vi } from "vitest";
const {push}=vi.hoisted(()=>({push:vi.fn()}));
vi.mock("next/navigation",()=>({useRouter:()=>({push})}));
vi.mock("@/lib/data/services",async original=>({...await original<typeof import("@/lib/data/services")>(),login:vi.fn(),getCurrentUser:vi.fn(),register:vi.fn(),saveTutorProfile:vi.fn(),submitTutorProfile:vi.fn(),uploadFile:vi.fn(),completeFile:vi.fn(),attachTutorAvatar:vi.fn(),createVerificationDocument:vi.fn(),getTutorProfile:vi.fn()}));
beforeEach(()=>vi.clearAllMocks());
it("displays a login failure without persisting a secret or redirecting",async()=>{
 vi.mocked(services.login).mockRejectedValue(new ApiClientError("Invalid credentials.",{status:401}));
 render(<AuthForm mode="login"/>);await userEvent.type(screen.getByLabelText("Email"),"test@example.test");await userEvent.type(screen.getByLabelText("Mật khẩu"),"test-password");await userEvent.click(screen.getByRole("button",{name:"Đăng nhập"}));
 expect(await screen.findByText("Email hoặc mật khẩu không chính xác.")).toBeVisible();expect(push).not.toHaveBeenCalled();expect(localStorage.length).toBe(0);
});
it("logs in with session cookies then routes according to the returned role",async()=>{
 vi.mocked(services.login).mockResolvedValue(undefined as never);vi.mocked(services.getCurrentUser).mockResolvedValue({role:"tutor"} as never);
 render(<AuthForm mode="login"/>);await userEvent.type(screen.getByLabelText("Email"),"test@example.test");await userEvent.type(screen.getByLabelText("Mật khẩu"),"test-password");await userEvent.click(screen.getByRole("button",{name:"Đăng nhập"}));expect(push).toHaveBeenCalledWith("/profile");
});
it("returns to the requested page after verifying the new session", async () => {
  vi.mocked(services.login).mockResolvedValue(undefined as never);
  vi.mocked(services.getCurrentUser).mockResolvedValue({ role: "customer" } as never);
  const destination = "/programs/7/sessions?page=2";
  render(<AuthForm mode="login" returnTo={destination} />);
  await userEvent.type(screen.getByLabelText("Email"), "test@example.test");
  await userEvent.type(screen.getByLabelText("Mật khẩu"), "test-password");
  await userEvent.click(screen.getByRole("button", { name: "Đăng nhập" }));
  expect(push).toHaveBeenCalledWith(destination);
});
const profile:TutorProfile={id:1,user_id:2,specialization_id:1,avatar_file_id:4,verification_document_file_id:5,headline:"Approved headline",bio:"Bio",experience_years:3,status:"approved",revision:{id:2,revision_no:1,status:"changes_requested",version:7,payload:{headline:"Revision headline",bio:"New bio"},review_reason:"Add detail"}};
function data(p:TutorProfile | null):TutorOnboardingData{return {profile:p,user:{id:2,role:"tutor",status:"active",full_name:"Tutor Anna",email:"tutor@example.test",email_verified_at:"2026-01-01T00:00:00Z"},specializations:[{id:1,subcategory_id:1,name:"English",slug:"english",status:"active"}]};}
it("shows revision content for resubmission and uses the current revision version",async()=>{
 vi.mocked(services.saveTutorProfile).mockResolvedValue(profile);
 render(<TutorOnboardingForm data={data(profile)} onReload={vi.fn()}/>);expect(screen.getByLabelText("Tiêu đề hồ sơ *")).toHaveValue("Revision headline");
 await userEvent.click(screen.getByRole("button",{name:/Tiếp theo/}));await userEvent.click(screen.getByRole("button",{name:/Tiếp theo/}));await userEvent.click(screen.getByRole("button",{name:/Tiếp theo/}));expect(services.saveTutorProfile).toHaveBeenCalledWith(expect.objectContaining({headline:"Revision headline",expected_version:7}));
 expect(await screen.findByLabelText("Loại tài liệu *")).toHaveValue("identity_card");expect(screen.getByRole("option",{name:"Chứng chỉ"})).toHaveValue("certificate");
});
it("locks a submitted revision while preserving the approved profile",()=>{
 render(<TutorOnboardingForm data={data({...profile,revision:{...profile.revision!,status:"submitted"}})} onReload={vi.fn()}/>);expect(screen.getByLabelText("Tiêu đề hồ sơ *")).toBeDisabled();expect(services.saveTutorProfile).not.toHaveBeenCalled();
});

it("shows the error beside a blank qualification name before saving the draft", async () => {
  const draft = { ...profile, status: "draft", revision: null } as const;
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Thêm bằng cấp/ }));
  await userEvent.type(screen.getByLabelText("Tên chứng nhận 1"), "   ");
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  expect(screen.getByLabelText("Tên chứng nhận 1")).toHaveAccessibleDescription("Vui lòng nhập tên bằng cấp hoặc chứng chỉ.");
  expect(services.saveTutorProfile).not.toHaveBeenCalled();
});

it.each([
  ["2000-01-01T00:00:00Z", /Cần tải CCCD \/ CMND hoặc hộ chiếu còn hiệu lực/],
  [null, /Cần tải tài liệu xác minh chứng chỉ đã khai báo/],
] as const)("blocks submission until required documents are present (passport expiry %s)", async (expiresAt, message) => {
  const draft: TutorProfile = {
    ...profile,
    status: "draft",
    revision: null,
    qualification_claims: [{ type: "certificate", name: "Teaching certificate" }],
    verification_documents: [{ id: 1, tutor_profile_id: 1, file_id: 5, document_type: "passport", status: "pending", expires_at: expiresAt }],
  };
  vi.mocked(services.saveTutorProfile).mockResolvedValue(draft);
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);
  for (let step = 0; step < 4; step += 1) {
    await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  }
  await userEvent.click(screen.getByRole("button", { name: /Gửi xét duyệt/ }));
  expect(await screen.findByText(message)).toBeVisible();
  expect(services.saveTutorProfile).toHaveBeenCalledTimes(1);
  expect(services.submitTutorProfile).not.toHaveBeenCalled();
});

it("rejects an unsupported avatar before making an upload request", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, avatar_file_id: null };
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  await userEvent.upload(
    screen.getByLabelText(/Chọn ảnh đại diện/),
    new File(["avatar"], "avatar.gif", { type: "image/gif" }),
    { applyAccept: false },
  );

  expect(await screen.findByRole("alert")).toHaveTextContent("Ảnh đại diện phải là JPG hoặc PNG");
  expect(services.uploadFile).not.toHaveBeenCalled();
});

it("completes an avatar upload and submits a valid tutor profile", async () => {
  const draft: TutorProfile = {
    ...profile,
    status: "draft",
    revision: null,
    headline: "English tutor",
    bio: "Practical English lessons",
    avatar_file_id: 4,
    verification_documents: undefined,
  };
  const submitted: TutorProfile = { ...draft, status: "submitted" };
  vi.mocked(services.saveTutorProfile).mockResolvedValue(draft);
  vi.mocked(services.submitTutorProfile).mockResolvedValue(submitted);
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 8, complete: false, original_name: "avatar.png" } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 8, complete: true, original_name: "avatar.png" } as never);
  vi.mocked(services.attachTutorAvatar).mockResolvedValue({ ...draft, avatar_file_id: 8 });
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  await userEvent.upload(
    screen.getByLabelText(/Chọn ảnh đại diện/),
    new File(["avatar"], "avatar.png", { type: "image/png" }),
  );
  expect(await screen.findByText("Đã tải ảnh đại diện lên.")).toBeVisible();
  expect(services.uploadFile).toHaveBeenCalledWith(expect.any(File), "avatar");
  expect(services.completeFile).toHaveBeenCalledWith(8);
  expect(services.attachTutorAvatar).toHaveBeenCalledWith(8, 0);

  for (let step = 0; step < 4; step += 1) {
    await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  }
  expect(await screen.findByRole("heading", { name: "Trạng thái hồ sơ" })).toBeVisible();
  await userEvent.click(screen.getByRole("button", { name: "Gửi xét duyệt" }));

  expect(await screen.findByText("Hồ sơ đã được gửi để xét duyệt.")).toBeVisible();
  expect(services.submitTutorProfile).toHaveBeenCalledTimes(1);
});

it("uploads and attaches a verification document using the selected document type", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, verification_documents: [] };
  const withDocument: TutorProfile = {
    ...draft,
    verification_documents: [{ id: 12, tutor_profile_id: 1, file_id: 9, document_type: "certificate", status: "pending" }],
  };
  vi.mocked(services.saveTutorProfile).mockResolvedValue(draft);
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 9, complete: false, original_name: "certificate.pdf" } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 9, complete: true, original_name: "certificate.pdf" } as never);
  vi.mocked(services.createVerificationDocument).mockResolvedValue(undefined as never);
  vi.mocked(services.getTutorProfile).mockResolvedValue(withDocument);
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  for (let step = 0; step < 3; step += 1) {
    await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  }
  await userEvent.selectOptions(screen.getByLabelText("Loại tài liệu *"), "certificate");
  await userEvent.upload(
    screen.getByLabelText(/Chọn tài liệu xác minh/),
    new File(["certificate"], "certificate.pdf", { type: "application/pdf" }),
  );

  expect(await screen.findByText("Đã tải tài liệu xác minh lên.")).toBeVisible();
  expect(services.uploadFile).toHaveBeenCalledWith(expect.any(File), "verification_document");
  expect(services.completeFile).toHaveBeenCalledWith(9);
  expect(services.createVerificationDocument).toHaveBeenCalledWith({ fileId: 9, documentType: "certificate" });
  expect(services.getTutorProfile).toHaveBeenCalledTimes(1);
  expect(screen.getByText("certificate.pdf")).toBeVisible();
});

it("retries attaching a completed verification document without uploading it twice", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, verification_documents: [] };
  vi.mocked(services.saveTutorProfile).mockResolvedValue(draft);
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 10, complete: false, original_name: "identity.pdf" } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 10, complete: true, original_name: "identity.pdf" } as never);
  vi.mocked(services.createVerificationDocument)
    .mockRejectedValueOnce(new ApiClientError("Document attachment failed", { status: 500 }))
    .mockResolvedValueOnce(undefined as never);
  vi.mocked(services.getTutorProfile).mockResolvedValue({ ...draft, verification_document_file_id: 10 });
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  for (let step = 0; step < 3; step += 1) {
    await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  }
  await userEvent.upload(
    screen.getByLabelText(/Chọn tài liệu xác minh/),
    new File(["identity"], "identity.pdf", { type: "application/pdf" }),
  );
  expect(await screen.findAllByText("Document attachment failed")).toHaveLength(2);

  await userEvent.click(screen.getByRole("button", { name: "Thử lại" }));

  expect(await screen.findByText("Đã tải tài liệu xác minh lên.")).toBeVisible();
  expect(services.uploadFile).toHaveBeenCalledTimes(1);
  expect(services.completeFile).toHaveBeenCalledTimes(1);
  expect(services.createVerificationDocument).toHaveBeenCalledTimes(2);
});

it("refreshes the tutor profile when saving detects a concurrent edit", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, headline: "Older headline" };
  const latest: TutorProfile = { ...draft, headline: "Updated by another session" };
  vi.mocked(services.saveTutorProfile).mockRejectedValue(new ApiClientError("Profile changed", { status: 409 }));
  vi.mocked(services.getTutorProfile).mockResolvedValue(latest);
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));

  expect(await screen.findByText("Profile changed")).toBeVisible();
  expect(services.getTutorProfile).toHaveBeenCalledTimes(1);
  await userEvent.click(screen.getByRole("button", { name: /Thông tin cơ bản/ }));
  expect(screen.getByLabelText("Tiêu đề hồ sơ *")).toHaveValue("Updated by another session");
});

it("queues an avatar before a profile exists and uploads it after saving the draft", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, avatar_file_id: null };
  vi.mocked(services.saveTutorProfile).mockResolvedValue(draft);
  vi.mocked(services.uploadFile).mockResolvedValue({ id: 11, complete: false, original_name: "queued.png" } as never);
  vi.mocked(services.completeFile).mockResolvedValue({ id: 11, complete: true, original_name: "queued.png" } as never);
  vi.mocked(services.attachTutorAvatar).mockResolvedValue({ ...draft, avatar_file_id: 11 });
  render(<TutorOnboardingForm data={data(null)} onReload={vi.fn()} />);

  await userEvent.upload(screen.getByLabelText(/Chọn ảnh đại diện/), new File(["avatar"], "queued.png", { type: "image/png" }));
  expect(screen.getByText("Ảnh đã chọn. Lưu hồ sơ ở Bước 3 để tải ảnh lên.")).toBeVisible();
  fireEvent.change(screen.getByLabelText("Tiêu đề hồ sơ *"), { target: { value: "English tutor" } });
  fireEvent.change(screen.getByLabelText("Giới thiệu bản thân *"), { target: { value: "Friendly lessons" } });
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.selectOptions(screen.getByLabelText("Chuyên môn *"), "1");
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  fireEvent.change(screen.getByLabelText("Số năm kinh nghiệm *"), { target: { value: "3" } });
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));

  expect(screen.getByText("Ảnh đại diện đã sẵn sàng")).toBeVisible();
  expect(services.saveTutorProfile).toHaveBeenCalledWith(expect.objectContaining({ headline: "English tutor", experience_years: 3 }));
  expect(services.uploadFile).toHaveBeenCalledWith(expect.any(File), "avatar");
  expect(services.attachTutorAvatar).toHaveBeenCalledWith(11, 0);
});

it("shows a server field error and returns to the matching onboarding step", async () => {
  const draft: TutorProfile = {
    ...profile, status: "draft", revision: null, headline: "English tutor", bio: "Friendly lessons",
    verification_documents: [{ id: 20, tutor_profile_id: 1, file_id: 20, document_type: "identity_card", status: "pending" }],
  };
  vi.mocked(services.saveTutorProfile)
    .mockResolvedValueOnce(draft)
    .mockRejectedValueOnce(new ApiClientError("Profile validation failed", { status: 422, fieldErrors: { headline: ["Headline is already in use"] } }));
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  for (let step = 0; step < 4; step += 1) await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: "Gửi xét duyệt" }));

  expect(await screen.findByText("Profile validation failed")).toBeVisible();
  expect(await screen.findByLabelText("Tiêu đề hồ sơ *")).toHaveAccessibleDescription("Headline is already in use");
  expect(services.submitTutorProfile).not.toHaveBeenCalled();
});

it("lets a locked revision move through read-only onboarding steps", async () => {
  const lockedProfile: TutorProfile = { ...profile, status: "approved", revision: { ...profile.revision!, status: "submitted" } };
  render(<TutorOnboardingForm data={data(lockedProfile)} onReload={vi.fn()} />);

  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));

  expect(screen.getByRole("heading", { name: "Trạng thái hồ sơ" })).toBeVisible();
  expect(screen.getByText("Hồ sơ đã gửi hoặc đã được xét duyệt nên hiện chỉ có thể xem lại.")).toBeVisible();
  expect(services.saveTutorProfile).not.toHaveBeenCalled();
});

it("prompts a tutor to add an avatar before submitting a valid profile", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null, avatar_file_id: null, verification_documents: undefined };
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);

  for (let step = 0; step < 4; step += 1) await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  await userEvent.click(screen.getByRole("button", { name: "Gửi xét duyệt" }));

  expect(await screen.findByText("Cần tải ảnh đại diện trước khi gửi hồ sơ.")).toBeVisible();
  expect(services.submitTutorProfile).not.toHaveBeenCalled();
});

it("requires a document type before uploading a verification file", async () => {
  const draft: TutorProfile = { ...profile, status: "draft", revision: null };
  render(<TutorOnboardingForm data={data(draft)} onReload={vi.fn()} />);
  for (let step = 0; step < 3; step += 1) await userEvent.click(screen.getByRole("button", { name: /Tiếp theo/ }));
  fireEvent.change(screen.getByLabelText("Loại tài liệu *"), { target: { value: "" } });
  await userEvent.upload(screen.getByLabelText(/Chọn tài liệu xác minh/), new File(["identity"], "identity.pdf", { type: "application/pdf" }));

  expect(await screen.findByText("Nhập loại tài liệu xác minh trước khi tải lên.")).toBeVisible();
  expect(services.uploadFile).not.toHaveBeenCalled();
});
