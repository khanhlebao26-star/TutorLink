import * as services from '@/lib/data/services';
import { ApiClientError, request, setAuthHint } from '@/lib/api/client';
import { beforeEach, expect, it, vi } from 'vitest';
vi.mock('@/lib/api/client',async original=>({...await original<typeof import('@/lib/api/client')>(),dataMode:'api',request:vi.fn(),setAuthHint:vi.fn()}));
beforeEach(()=>vi.resetAllMocks());
const respond=(data:unknown)=>vi.mocked(request).mockResolvedValueOnce({data:{data}} as never);
it('loads customer and tutor profile contexts and denies a non-tutor onboarding',async()=>{
 respond({id:1,role:'customer',profile:{id:2}});expect((await services.getProfile('customer')).profile.id).toBe(2);
 respond({id:1,profile:null});await expect(services.getProfile('customer')).rejects.toMatchObject({status:404});
 respond({id:1,role:'customer'});await expect(services.getTutorOnboarding()).rejects.toMatchObject({status:403});
 respond({id:1,role:'tutor'});respond([]);vi.mocked(request).mockRejectedValueOnce(new ApiClientError('Missing',{status:404}));expect((await services.getTutorOnboarding()).profile).toBeNull();
 respond({id:1,role:'tutor'});respond([]);vi.mocked(request).mockRejectedValueOnce(new ApiClientError('Forbidden',{status:403}));await expect(services.getTutorOnboarding()).rejects.toMatchObject({status:403});
 respond({id:1,role:'tutor'});respond({id:8});expect((await services.getProfile('tutor')).profile.id).toBe(8);
});
it('updates session hints only after successful login/logout and encodes verification URLs',async()=>{
 respond({id:7});await services.login('a@example.com','Password123!',true);expect(setAuthHint).toHaveBeenCalledWith(true);
 vi.mocked(request).mockRejectedValueOnce(new ApiClientError('Network'));await expect(services.logout()).rejects.toThrow('Network');expect(setAuthHint).not.toHaveBeenCalledWith(false);
 respond(null);await services.logout();expect(setAuthHint).toHaveBeenLastCalledWith(false);
 respond({id:7});await services.verifyEmail('7','a/b',{expires:'123',signature:'a+b'});expect(request).toHaveBeenLastCalledWith({method:'GET',url:'/auth/email/verify/7/a%2Fb?expires=123&signature=a%2Bb'});
});
it('uses scoped admin reads and versioned decisions',async()=>{
 respond([]);expect(await services.getAdminTutorProfiles()).toEqual([]);respond([]);expect(await services.getAdminTutorQueue()).toEqual([]);
 vi.mocked(request).mockResolvedValueOnce({data:{data:[{id:1}],meta:{total:12}}} as never);expect(await services.getAdminTutorReviewPage('mine','submitted',2)).toMatchObject({total:12,page:2,perPage:10});
 respond(null);await services.assignAdminTutorProfile(3,4,5);expect(request).toHaveBeenLastCalledWith(expect.objectContaining({method:'PUT',data:expect.objectContaining({assignment_version:5})}),true);
 await expect(services.performAdminTutorAction(3,'approve','ok',{} as never)).rejects.toThrow('Tải lại');
 respond(null);respond({profile:{id:3}});await services.performAdminTutorAction(3,'approve','ok',{assignment:{assignment_version:5},revision:{version:2}} as never);expect(request).toHaveBeenCalledWith(expect.objectContaining({url:'/admin/tutor-profiles/3/approve',data:{reason:'ok',assignment_version:5,expected_version:2}}),true);
 respond({id:4});expect((await services.performAdminAccountAction(4,'suspend','reason')).id).toBe(4);
});
it('validates upload size before HTTP and reports upload progress',async()=>{
 await expect(services.uploadFile(new File([],'empty.pdf'),'profile_document')).rejects.toThrow('5.000.000');expect(request).not.toHaveBeenCalled();
 const progress=vi.fn();respond({id:3});await services.uploadFile(new File(['test'],'notes.pdf',{type:'application/pdf'}),'profile_document',progress);
 const config=vi.mocked(request).mock.calls[0][0];expect((config.data as FormData).get('purpose')).toBe('profile_document');config.onUploadProgress?.({loaded:2,total:4} as never);expect(progress).toHaveBeenCalledWith(50);
 respond({id:3,complete:true});expect((await services.completeFile(3)).complete).toBe(true);
 respond({id:8});await services.attachTutorAvatar(3,2);expect(request).toHaveBeenLastCalledWith(expect.objectContaining({data:{file_id:3,expected_version:2}}),true);
 respond({id:9});await services.createVerificationDocument({fileId:3,documentType:'passport'});expect(request).toHaveBeenLastCalledWith(expect.objectContaining({data:{file_id:3,document_type:'passport'}}),true);
 respond({id:8,status:'draft'});await services.saveTutorProfile({headline:'Tutor',experience_years:2,specialization_id:3});respond({status:'submitted'});expect((await services.submitTutorProfile()).status).toBe('submitted');
});
