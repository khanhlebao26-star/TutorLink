import { request } from "@/lib/api/client";
import { attachTutorAvatar, logout, requestPasswordReset, resetPassword, uploadFile, verifyEmail } from "@/lib/data/services";
import { beforeEach, expect, it, vi } from "vitest";
vi.mock("@/lib/api/client",async original=>({...await original<typeof import("@/lib/api/client")>(),request:vi.fn()}));
beforeEach(()=>{vi.mocked(request).mockReset();vi.mocked(request).mockResolvedValue({data:{data:{id:1},message:"ok"}} as never);});
it("rejects empty or oversized uploads before making any API call",async()=>{
 await expect(uploadFile(new File([],"empty.pdf",{type:"application/pdf"}),"verification_document")).rejects.toThrow("5.000.000");
 await expect(uploadFile(new File([new Uint8Array(5000001)],"large.pdf",{type:"application/pdf"}),"verification_document")).rejects.toThrow("5.000.000");expect(request).not.toHaveBeenCalled();
});
it("uses the approved revision version when attaching an avatar",async()=>{await attachTutorAvatar(4,7);expect(request).toHaveBeenCalledWith(expect.objectContaining({data:{file_id:4,expected_version:7}}),true);});
it("keeps reset and signed verification fields aligned with Fortify endpoints",async()=>{
 await requestPasswordReset("me@example.test");expect(request).toHaveBeenLastCalledWith({method:"POST",url:"/auth/forgot-password",data:{email:"me@example.test"}},true);
 const payload={token:"reset-token",email:"me@example.test",password:"new-test-password",password_confirmation:"new-test-password"};await resetPassword(payload);expect(request).toHaveBeenLastCalledWith({method:"POST",url:"/auth/reset-password",data:payload},true);
 await verifyEmail("7","hash",{expires:"10",signature:"signed"});expect(request).toHaveBeenLastCalledWith({method:"GET",url:"/auth/email/verify/7/hash?expires=10&signature=signed"});
 await logout();expect(request).toHaveBeenLastCalledWith({method:"POST",url:"/auth/logout"},true);
});
