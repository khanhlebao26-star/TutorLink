/* eslint-disable @next/next/no-img-element -- DOM-only Image test double. */
import { ReportSubmissionForm } from '@/components/reports/report-submission-form';
import { ReportsPage } from '@/components/reports/reports-page';
import * as workspace from '@/lib/data/workspace';
import * as market from '@/lib/data/marketplace';
import * as services from '@/lib/data/services';
import { ApiClientError } from '@/lib/api/client';
import { listing,meta } from '../marketplace/fixtures';
import { render,screen,fireEvent,waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach,expect,it,vi } from 'vitest';
vi.mock('@/components/layout/app-shell',()=>({useActor:()=>({id:7,role:'customer',email_verified_at:'2026-01-01'}),AppShell:({children}:{children:React.ReactNode})=><>{children}</>}));
vi.mock('next/image',()=>({default:({alt,src}:{alt:string;src:string})=><img alt={alt} src={src}/>}));
vi.mock('@/lib/data/marketplace',async original=>({...await original<typeof import('@/lib/data/marketplace')>(),searchListings:vi.fn(),searchReportableTutors:vi.fn()}));
vi.mock('@/lib/data/services',()=>({uploadFile:vi.fn(),completeFile:vi.fn()}));
vi.mock('@/lib/data/workspace',async original=>({...await original<typeof import('@/lib/data/workspace')>(),workspaceRead:vi.fn(),workspaceWrite:vi.fn()}));
beforeEach(()=>{vi.resetAllMocks();vi.stubGlobal('URL',URL);URL.createObjectURL=vi.fn(()=> 'blob:example');URL.revokeObjectURL=vi.fn();vi.mocked(market.searchListings).mockResolvedValue({data:[listing],meta});vi.mocked(market.searchReportableTutors).mockResolvedValue({data:[{id:9,name:'Anna',headline:'English',specializations:['English']}],meta});vi.mocked(workspace.workspaceWrite).mockResolvedValue({} as never);});
it('submits a selected listing with checked image evidence',async()=>{
 const saved=vi.fn();vi.mocked(services.uploadFile).mockResolvedValue({id:8} as never);vi.mocked(services.completeFile).mockResolvedValue({id:8,complete:true,scan_status:'clean'} as never);const {container}=render(<ReportSubmissionForm onSaved={saved}/>);await userEvent.click(await screen.findByRole('option',{name:/English conversation/}));await userEvent.selectOptions(screen.getByLabelText('Lý do báo cáo'),'spam');fireEvent.change(screen.getByLabelText('Mô tả chi tiết'),{target:{value:'Misleading content in this listing'}});fireEvent.change(container.querySelector('input[type=file]')!,{target:{files:[new File(['image'],'evidence.png',{type:'image/png'})]}});await userEvent.click(screen.getByRole('button',{name:'Gửi báo cáo'}));await waitFor(()=>expect(saved).toHaveBeenCalled());expect(workspace.workspaceWrite).toHaveBeenCalledWith('/reports','POST',expect.objectContaining({resource_type:'listing',resource_id:1,file_ids:[8],reason_code:'spam'}),expect.any(String));
});
it('retains a system report and its retry identity after an API failure',async()=>{
 const saved=vi.fn();vi.mocked(workspace.workspaceWrite).mockRejectedValueOnce(new ApiClientError('Reports unavailable')).mockResolvedValue({} as never);render(<ReportSubmissionForm onSaved={saved}/>);await userEvent.click(screen.getByRole('button',{name:'Hệ thống'}));await userEvent.selectOptions(screen.getByLabelText('Lý do báo cáo'),'bug_report');fireEvent.change(screen.getByLabelText('Mô tả chi tiết'),{target:{value:'Search returns an error'}});await userEvent.click(screen.getByRole('button',{name:'Gửi báo cáo'}));expect(await screen.findByText('Reports unavailable')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:'Gửi báo cáo'}));await waitFor(()=>expect(saved).toHaveBeenCalled());expect(vi.mocked(workspace.workspaceWrite).mock.calls[0]).toEqual(vi.mocked(workspace.workspaceWrite).mock.calls[1]);
});
it('changes target to a tutor and rejects unsupported evidence',async()=>{
 const {container}=render(<ReportSubmissionForm onSaved={vi.fn()}/>);await userEvent.click(screen.getByRole('button',{name:'Một gia sư'}));await userEvent.click(await screen.findByRole('option',{name:/Anna/}));fireEvent.change(container.querySelector('input[type=file]')!,{target:{files:[new File(['bad'],'bad.pdf',{type:'application/pdf'})]}});expect(await screen.findByText('Bằng chứng chỉ nhận ảnh JPG hoặc PNG.')).toBeVisible();expect(services.uploadFile).not.toHaveBeenCalled();
});
it('loads the owner report history and exposes resolution details',async()=>{
 vi.mocked(workspace.workspaceRead).mockResolvedValue({data:[{id:5,resource_type:'system',resource_id:1,reason_code:'bug_report',description:'Search fixed',status:'resolved',resolution_note:'Repaired search',created_at:'2026-11-18T00:00:00Z'}],meta:{total:1,current_page:1,per_page:50}});render(<ReportsPage/>);await userEvent.click(await screen.findByRole('button',{name:/Chi tiết/}));expect(await screen.findByText('Search fixed')).toBeVisible();expect(workspace.workspaceRead).toHaveBeenCalledWith('/me/reports',{page:1,per_page:50});const details=screen.queryAllByRole('button',{name:/Chi tiết|Xem/});if(details.length)await userEvent.click(details[0]);
});
