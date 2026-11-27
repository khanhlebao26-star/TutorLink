import { Programs } from '@/components/programs/program-list';
import { ProgramQuizzesPage, QuizDetailPage } from '@/components/programs/program-quizzes';
import { TaskWorkspace } from '@/components/programs/task-workspace';
import * as collaboration from '@/lib/data/collaboration';
import * as workspace from '@/lib/data/workspace';
import { ApiClientError } from '@/lib/api/client';
import { render,screen,fireEvent,waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach,expect,it,vi } from 'vitest';
const state=vi.hoisted(()=>({actor:{id:7,role:'customer'},params:new URLSearchParams(),push:vi.fn()}));
vi.mock('next/navigation',()=>({useSearchParams:()=>state.params,usePathname:()=>'/programs',useRouter:()=>({push:state.push})}));
vi.mock('@/components/layout/app-shell',()=>({useActor:()=>state.actor,AppShell:({children}:{children:React.ReactNode})=><>{children}</>}));
vi.mock('@/lib/data/collaboration',async original=>({...await original<typeof import('@/lib/data/collaboration')>(),getProgram:vi.fn(),listPrograms:vi.fn()}));
vi.mock('@/lib/data/workspace',async original=>({...await original<typeof import('@/lib/data/workspace')>(),workspaceRead:vi.fn(),workspaceWrite:vi.fn()}));
const program={id:3,title:'English program',customer_id:7,status:'active',allowed_actions:[],delivery_mode:'online',planned_session_count:8};
const quiz={id:5,program_id:3,title:'English quiz',description:'Choose the answer',due_at:null,questions:[{id:9,position:1,prompt:'Hello means?',options:['Hi','Bye']} ]};
beforeEach(()=>{vi.resetAllMocks();state.actor={id:7,role:'customer'};state.params=new URLSearchParams();vi.mocked(collaboration.getProgram).mockResolvedValue(program as never);vi.mocked(workspace.workspaceWrite).mockResolvedValue({} as never);});
it('loads programs, changes status and navigates server pages',async()=>{
 vi.mocked(collaboration.listPrograms).mockResolvedValue({data:[program],meta:{total:21,current_page:1,per_page:20,last_page:2}} as never);render(<Programs/>);await screen.findByRole('heading',{name:'English program'});await userEvent.click(screen.getByRole('button',{name:'Sau'}));expect(state.push).toHaveBeenCalledWith('/programs?page=2',{scroll:false});await userEvent.click(screen.getByRole('button',{name:/Đang học|Đang hoạt động/}));expect(state.push).toHaveBeenLastCalledWith('/programs?status=active',{scroll:false});
});
it('handles a failed program list without exposing stale rows',async()=>{
 vi.mocked(collaboration.listPrograms).mockRejectedValue(new ApiClientError('Programs unavailable'));render(<Programs/>);expect(await screen.findByText('Programs unavailable')).toBeVisible();
});
it('submits quiz answers and displays API failure before retry',async()=>{
 vi.mocked(workspace.workspaceRead).mockResolvedValue({data:quiz});vi.mocked(workspace.workspaceWrite).mockRejectedValueOnce(new ApiClientError('Quiz unavailable')).mockResolvedValue({} as never);render(<QuizDetailPage programId={3} quizId={5}/>);await screen.findByRole('heading',{name:'English quiz'});await userEvent.click(screen.getByRole('button',{name:'Nộp bài và xem kết quả'}));expect(await screen.findByText(/Hãy chọn một đáp án/)).toBeVisible();await userEvent.click(screen.getByRole('radio',{name:'Hi'}));await userEvent.click(screen.getByRole('button',{name:'Nộp bài và xem kết quả'}));expect(await screen.findByText('Quiz unavailable')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:'Nộp bài và xem kết quả'}));await waitFor(()=>expect(workspace.workspaceWrite).toHaveBeenLastCalledWith('/quizzes/5/attempts','POST',{answers:[{question_id:9,option_index:0}]},expect.any(String)));
});
it('locks completed quizzes and refuses a quiz from another program',async()=>{
 vi.mocked(workspace.workspaceRead).mockResolvedValue({data:{...quiz,attempt:{score:1,question_count:1,submitted_at:'2026-11-19T00:00:00Z',results:[{question_id:9,selected_option:0,correct_option:0,is_correct:true}]}}});const view=render(<QuizDetailPage programId={3} quizId={5}/>);await screen.findByText('Đã hoàn thành');expect(screen.getByRole('radio',{name:/Hi/})).toBeDisabled();view.unmount();vi.mocked(workspace.workspaceRead).mockResolvedValue({data:{...quiz,program_id:99}});render(<QuizDetailPage programId={3} quizId={5}/>);expect(await screen.findByRole('alert')).toHaveTextContent('Đã xảy ra lỗi không xác định.');expect(screen.queryByRole('heading',{name:'English quiz'})).not.toBeInTheDocument();
});
it('opens a quiz builder and creates a normalized quiz as the tutor',async()=>{
 state.actor={id:9,role:'tutor'};vi.mocked(workspace.workspaceRead).mockResolvedValue({data:[],meta:{total:0,per_page:20,current_page:1}});render(<ProgramQuizzesPage id={3}/>);await userEvent.click(await screen.findByRole('button',{name:/Tạo bài kiểm tra/}));fireEvent.change(screen.getByLabelText('Tên bài kiểm tra'),{target:{value:' Vocabulary '}});fireEvent.change(screen.getByLabelText('Nội dung câu hỏi'),{target:{value:' Hello? '}});for(let n=1;n<=4;n++)fireEvent.change(screen.getByPlaceholderText(`Lựa chọn ${n}`),{target:{value:`Option ${n}`}});await userEvent.click(screen.getByRole('button',{name:'Tạo bài kiểm tra'}));await waitFor(()=>expect(workspace.workspaceWrite).toHaveBeenCalledWith('/programs/3/quizzes','POST',expect.objectContaining({title:'Vocabulary',questions:[{prompt:'Hello?',options:['Option 1','Option 2','Option 3','Option 4'],correct_option:0}]}),expect.any(String)));
});
it('shows task prompt, previous submission, and history without offering terminal writes',async()=>{
 vi.mocked(collaboration.getProgram).mockResolvedValue({...program,status:'completed'} as never);vi.mocked(workspace.workspaceRead).mockImplementation(async(path)=>path==='/tasks/4'?{data:{id:4,program_id:3,title:'Essay',status:'completed',assigned_to:7,file_ids:[]}}:path==='/tasks/4/submission'?{data:{id:8,content:'My essay',status:'accepted',feedback:'Well done',submission_version:2}}:{data:[],meta:{total:0,current_page:1,per_page:20}});render(<TaskWorkspace programId={3} id={4}/>);await screen.findByRole('heading',{name:'Essay'});expect(screen.getByText('My essay')).toBeVisible();expect(screen.getByText('Well done')).toBeVisible();expect(screen.queryByRole('button',{name:'Nộp phiên bản mới'})).not.toBeInTheDocument();
});
it('requires a revision note and sends the current submission version to the tutor review endpoint',async()=>{
 state.actor={id:9,role:'tutor'};vi.mocked(collaboration.getProgram).mockResolvedValue({...program,allowed_actions:['review_submissions']} as never);vi.mocked(workspace.workspaceRead).mockImplementation(async(path)=>path==='/tasks/4'?{data:{id:4,program_id:3,title:'Essay',status:'submitted',assigned_to:7}}:path==='/tasks/4/submission'?{data:{id:8,content:'My essay',status:'submitted',submission_version:3}}:{data:[],meta:{total:0,current_page:1,per_page:20}});render(<TaskWorkspace programId={3} id={4}/>);await screen.findByRole('heading',{name:'Essay'});
 await userEvent.click(screen.getByRole('button',{name:'Yêu cầu chỉnh sửa'}));expect(await screen.findByText('Hãy ghi rõ nội dung học viên cần chỉnh sửa.')).toBeVisible();expect(workspace.workspaceWrite).not.toHaveBeenCalled();fireEvent.change(screen.getByLabelText('Nhận xét cho học viên'),{target:{value:'Please add examples'}});await userEvent.click(screen.getByRole('button',{name:'Yêu cầu chỉnh sửa'}));await waitFor(()=>expect(workspace.workspaceWrite).toHaveBeenCalledWith('/tasks/4/submission/request-revision','POST',{expected_submission_version:3,feedback:'Please add examples'},expect.any(String)));
});
