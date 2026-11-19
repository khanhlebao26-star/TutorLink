import { AuthSupportForm } from '@/components/auth/auth-support-form';
import * as services from '@/lib/data/services';
import { ApiClientError } from '@/lib/api/client';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
const state=vi.hoisted(()=>({params:new URLSearchParams(),push:vi.fn()}));
vi.mock('next/navigation',()=>({useSearchParams:()=>state.params,useRouter:()=>({push:state.push})}));
vi.mock('@/lib/data/services',()=>({verifyEmail:vi.fn(),resendVerification:vi.fn(),requestPasswordReset:vi.fn(),resetPassword:vi.fn()}));
beforeEach(()=>{vi.resetAllMocks();state.params=new URLSearchParams();});
it('requests reset email and presents API validation without losing the email',async()=>{
 vi.mocked(services.requestPasswordReset).mockRejectedValueOnce(new ApiClientError('Invalid email',{status:422,fieldErrors:{email:['Account email invalid']}})).mockResolvedValueOnce('Check your inbox');
 render(<AuthSupportForm mode="forgot"/>);await userEvent.type(screen.getByLabelText('Email'),'anna@example.com');
 await userEvent.click(screen.getByRole('button',{name:'Gửi liên kết đặt lại'}));expect(await screen.findByText('Account email invalid')).toBeVisible();
 await userEvent.click(screen.getByRole('button',{name:'Gửi liên kết đặt lại'}));expect(await screen.findByText('Check your inbox')).toBeVisible();expect(services.requestPasswordReset).toHaveBeenLastCalledWith('anna@example.com');
});
it('verifies a signed link and offers login',async()=>{
 state.params=new URLSearchParams('id=7&hash=abc&expires=123&signature=signed');vi.mocked(services.verifyEmail).mockResolvedValue({id:7} as never);
 render(<AuthSupportForm mode="verify"/>);expect(await screen.findByRole('link',{name:'Tiếp tục đăng nhập'})).toHaveAttribute('href','/auth/login');expect(services.verifyEmail).toHaveBeenCalledWith('7','abc',{expires:'123',signature:'signed'});
});
it('resends verification after an invalid link',async()=>{
 state.params=new URLSearchParams('id=7&hash=bad&email=anna@example.com');vi.mocked(services.verifyEmail).mockRejectedValue(new ApiClientError('Link expired'));vi.mocked(services.resendVerification).mockResolvedValue('Sent again');
 render(<AuthSupportForm mode="verify"/>);await screen.findByText('Link expired');await userEvent.click(screen.getByRole('button',{name:'Gửi lại email xác minh'}));expect(await screen.findByText('Sent again')).toBeVisible();
});
it('validates matching passwords locally then submits the email token',async()=>{
 state.params=new URLSearchParams('token=secret&email=anna@example.com');vi.mocked(services.resetPassword).mockResolvedValue('Password changed');
 render(<AuthSupportForm mode="reset"/>);const password=screen.getByLabelText('Mật khẩu mới'),confirm=screen.getByLabelText('Xác nhận mật khẩu');
 fireEvent.change(password,{target:{value:'Password123!'}});fireEvent.change(confirm,{target:{value:'Different123!'}});await userEvent.click(screen.getByRole('button',{name:'Cập nhật mật khẩu'}));expect(await screen.findByText('Mật khẩu xác nhận chưa khớp.')).toBeVisible();expect(services.resetPassword).not.toHaveBeenCalled();
 await userEvent.click(screen.getAllByRole('button',{name:'Hiện mật khẩu'})[0]);expect(password).toHaveAttribute('type','text');
 fireEvent.change(confirm,{target:{value:'Password123!'}});await userEvent.click(screen.getByRole('button',{name:'Cập nhật mật khẩu'}));expect(await screen.findByText('Password changed')).toBeVisible();expect(services.resetPassword).toHaveBeenCalledWith({token:'secret',email:'anna@example.com',password:'Password123!',password_confirmation:'Password123!'});
});
