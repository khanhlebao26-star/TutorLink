import {render,screen} from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import {beforeEach,expect,it,vi} from "vitest";
import {AuthForm} from "@/components/auth-form";
import {TutorOnboardingForm} from "@/components/tutor-onboarding-form";
import {ApiClientError} from "@/lib/api/client";
import * as services from "@/lib/data/services";
import type {TutorOnboardingData,TutorProfile} from "@/lib/data/types";
const {push}=vi.hoisted(()=>({push:vi.fn()}));
vi.mock("next/navigation",()=>({useRouter:()=>({push})}));
vi.mock("@/lib/data/services",async original=>({...await original<typeof import("@/lib/data/services")>(),login:vi.fn(),getCurrentUser:vi.fn(),register:vi.fn(),saveTutorProfile:vi.fn(),submitTutorProfile:vi.fn()}));
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
const profile:TutorProfile={id:1,user_id:2,specialization_id:1,avatar_file_id:4,verification_document_file_id:5,headline:"Approved headline",bio:"Bio",experience_years:3,status:"approved",revision:{id:2,revision_no:1,status:"changes_requested",version:7,payload:{headline:"Revision headline",bio:"New bio"},review_reason:"Add detail"}};
function data(p:TutorProfile):TutorOnboardingData{return {profile:p,user:{id:2,role:"tutor",status:"active",full_name:"Tutor Anna",email:"tutor@example.test",email_verified_at:"2026-01-01T00:00:00Z"},specializations:[{id:1,subcategory_id:1,name:"English",slug:"english",status:"active"}]};}
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
