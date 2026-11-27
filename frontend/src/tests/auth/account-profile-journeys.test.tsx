import LearningProfilePage from '@/app/learning-profile/page';
import ChangePasswordPage from '@/app/settings/password/page';
import SettingsPage from '@/app/settings/page';
import { LogoutButton } from '@/components/auth/logout-button';
import { request,ApiClientError } from '@/lib/api/client';
import { logout } from '@/lib/data/services';
import { render,screen,fireEvent,waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach,expect,it,vi } from 'vitest';
const nav=vi.hoisted(()=>({push:vi.fn()}));
vi.mock('next/navigation',()=>({useSearchParams:()=>new URLSearchParams(),usePathname:()=>'/settings',useRouter:()=>({push:nav.push})}));
vi.mock('@/components/layout/app-shell',()=>({AppShell:({children}:{children:React.ReactNode})=><>{children}</>,useActor:()=>({id:7,role:'customer',full_name:'Learner One'})}));
vi.mock('@/lib/api/client',async original=>({...await original<typeof import('@/lib/api/client')>(),request:vi.fn()}));
vi.mock('@/lib/data/services',async original=>({...await original<typeof import('@/lib/data/services')>(),logout:vi.fn()}));
beforeEach(()=>vi.resetAllMocks());
it('edits learning preferences and saves the returned server profile',async()=>{
 const profile={subjects_of_interest:'English',learning_level:'A2',learning_goals:'Conversation',preferred_schedule:'Evenings',preferred_mode:'online',city:null,district:null};vi.mocked(request).mockResolvedValue({data:{data:profile}} as never);render(<LearningProfilePage/>);const subject=await screen.findByLabelText('Môn học hoặc kỹ năng quan tâm');fireEvent.change(subject,{target:{value:'English and mathematics'}});await userEvent.click(screen.getByRole('button',{name:'Lưu hồ sơ học tập'}));await waitFor(()=>expect(request).toHaveBeenCalledWith({method:'PUT',url:'/me/learning-profile',data:{...profile,subjects_of_interest:'English and mathematics'}},true));
});
it('keeps learning preferences after a failed save',async()=>{
 vi.mocked(request).mockResolvedValueOnce({data:{data:{subjects_of_interest:'English'}}} as never).mockRejectedValueOnce(new ApiClientError('Profile unavailable'));render(<LearningProfilePage/>);await screen.findByDisplayValue('English');await userEvent.click(screen.getByRole('button',{name:'Lưu hồ sơ học tập'}));expect(await screen.findByText('Profile unavailable')).toBeVisible();expect(screen.getByDisplayValue('English')).toBeVisible();
});
it('validates password confirmation, handles current-password errors and clears secrets after success',async()=>{
 vi.mocked(request).mockRejectedValueOnce(new ApiClientError('Incorrect password',{status:422,fieldErrors:{current_password:['Current password invalid']}})).mockResolvedValueOnce({data:{}} as never);render(<ChangePasswordPage/>);fireEvent.change(screen.getByLabelText('Mật khẩu hiện tại'),{target:{value:'OldPassword123!'}});fireEvent.change(screen.getByLabelText('Mật khẩu mới'),{target:{value:'NewPassword123!'}});fireEvent.change(screen.getByLabelText('Xác nhận mật khẩu mới'),{target:{value:'Mismatch123!'}});await userEvent.click(screen.getByRole('button',{name:'Cập nhật mật khẩu'}));expect(await screen.findByText('Mật khẩu xác nhận chưa khớp.')).toBeVisible();expect(request).not.toHaveBeenCalled();fireEvent.change(screen.getByLabelText('Xác nhận mật khẩu mới'),{target:{value:'NewPassword123!'}});await userEvent.click(screen.getByRole('button',{name:'Cập nhật mật khẩu'}));expect(await screen.findByText('Current password invalid')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:'Cập nhật mật khẩu'}));expect(await screen.findByText('Mật khẩu đã được cập nhật.')).toBeVisible();expect(screen.getByLabelText('Mật khẩu hiện tại')).toHaveValue('');
});
it('loads account settings from the authorized API',async()=>{
 vi.mocked(request).mockResolvedValue({data:{data:{id:7,full_name:'Learner One',email:'learner@example.com',role:'customer',status:'active',date_of_birth:'2000-01-01',email_verified_at:'2026-01-01'}}} as never);render(<SettingsPage/>);expect(await screen.findByDisplayValue('Learner One')).toBeVisible();expect(request).toHaveBeenCalledWith({method:'GET',url:'/me'});
});
it('validates a short display name before sending a profile update',async()=>{
 vi.mocked(request).mockResolvedValueOnce({data:{data:{id:7,full_name:'Learner One',email:'learner@example.com',role:'customer',status:'active'}}} as never);render(<SettingsPage/>);const name=await screen.findByLabelText('Họ và tên');fireEvent.change(name,{target:{value:'A'}});await userEvent.click(screen.getByRole('button',{name:'Lưu thay đổi'}));expect(await screen.findByText('Vui lòng nhập tên hiển thị có ít nhất 2 ký tự.')).toBeVisible();expect(request).toHaveBeenCalledTimes(1);
});
it('saves a valid display name and reports an API field error without losing the draft',async()=>{
 vi.mocked(request).mockResolvedValueOnce({data:{data:{id:7,full_name:'Learner One',email:'learner@example.com',role:'customer',status:'active',email_verified_at:null}}} as never).mockRejectedValueOnce(new ApiClientError('Name rejected',{status:422,fieldErrors:{full_name:['This name is unavailable']}}));render(<SettingsPage/>);const name=await screen.findByLabelText('Họ và tên');fireEvent.change(name,{target:{value:'Learner Two'}});await userEvent.click(screen.getByRole('button',{name:'Lưu thay đổi'}));expect(await screen.findByText('Name rejected')).toBeVisible();expect(screen.getByLabelText('Họ và tên')).toHaveValue('Learner Two');expect(screen.getByText('This name is unavailable')).toBeVisible();
});
it('shows the read-only account summary for an administrator',async()=>{
 vi.mocked(request).mockResolvedValue({data:{data:{id:1,full_name:'Admin User',email:'admin@example.com',role:'admin',status:'active',email_verified_at:'2026-01-01'}}} as never);render(<SettingsPage/>);expect(await screen.findByText('Thông tin tài khoản quản trị')).toBeVisible();expect(screen.getByText('Admin User')).toBeVisible();expect(screen.queryByLabelText('Họ và tên')).not.toBeInTheDocument();expect(screen.getByRole('link',{name:'Đổi mật khẩu'})).toHaveAttribute('href','/settings/password');
});
it('reports a logout failure and redirects after a successful retry',async()=>{
 vi.mocked(logout).mockRejectedValueOnce(new ApiClientError('Cannot logout')).mockResolvedValueOnce();render(<LogoutButton/>);await userEvent.click(screen.getByRole('button',{name:'Đăng xuất'}));expect(await screen.findByText('Cannot logout')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:'Đăng xuất'}));expect(nav.push).toHaveBeenCalledWith('/auth/login');
});
