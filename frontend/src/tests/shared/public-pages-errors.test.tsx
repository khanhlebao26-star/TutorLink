/* eslint-disable @next/next/no-img-element -- DOM-only Image test double. */
import Home from '@/app/page';
import BecomeTutor from '@/app/become-a-tutor/page';
import About from '@/app/about/page';
import Support from '@/app/support/page';
import Monitoring from '@/app/monitoring/page';
import NotFound from '@/app/not-found';
import ErrorPage from '@/app/error';
import { onRequestError } from '@/instrumentation';
import { render,screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach,expect,it,vi } from 'vitest';
vi.mock('@/components/layout/app-shell',()=>({AppShell:({children}:{children:React.ReactNode})=><>{children}</>,useActor:()=>null}));
vi.mock('next/navigation',()=>({usePathname:()=>'/'}));
vi.mock('next/image',()=>({default:({alt,src}:{alt:string;src:string})=><img alt={alt} src={src}/>}));
vi.mock('@/lib/data/marketplace',async original=>({...await original<typeof import('@/lib/data/marketplace')>(),getMarketplaceCatalog:vi.fn().mockResolvedValue([]),getFeaturedListingCandidates:vi.fn().mockResolvedValue({data:[],meta:{total:0,current_page:1,per_page:50}})}));
afterEach(()=>vi.unstubAllEnvs());
it('offers marketplace and registration routes on the public landing page',async()=>{
 render(<Home/>);expect(screen.getAllByRole('link',{name:/Khám phá dịch vụ/})[0]).toHaveAttribute('href','/marketplace');expect(screen.getByRole('link',{name:'Tạo tài khoản'})).toHaveAttribute('href','/auth/register');await screen.findByText(/Chưa có|Chưa tìm thấy|Sẵn sàng|SẴN SÀNG/);
});
it.each([BecomeTutor,About,Support])('renders public information with accessible headings',async Component=>{render(<Component/>);expect(screen.getAllByRole('heading',{level:1})).toHaveLength(1);});
it('accepts an HTTPS monitoring dashboard and rejects embedded credentials',()=>{
 vi.stubEnv('NEXT_PUBLIC_HOST_DASHBOARD_URL','https://monitor.example.com');const view=render(<Monitoring/>);expect(screen.getByRole('link',{name:/Mở dashboard máy chủ/})).toHaveAttribute('href','https://monitor.example.com/');view.unmount();vi.stubEnv('NEXT_PUBLIC_HOST_DASHBOARD_URL','https://user:secret@monitor.example.com');render(<Monitoring/>);expect(screen.queryByRole('link',{name:/Mở dashboard máy chủ/})).not.toBeInTheDocument();
});
it('provides 404 recovery and retries without displaying internal exception details',async()=>{
 const view=render(<NotFound/>);expect(screen.getByRole('link',{name:'Về trang chủ'})).toHaveAttribute('href','/');view.unmount();const reset=vi.fn();render(<ErrorPage error={Object.assign(new Error('Sensitive server data'),{digest:'abc123'})} reset={reset}/>);expect(screen.queryByText('Sensitive server data')).not.toBeInTheDocument();await userEvent.click(screen.getByRole('button',{name:'Thử lại'}));expect(reset).toHaveBeenCalledOnce();expect(screen.getByText('abc123')).toBeVisible();
});
it('records a structured server error without request credentials or query values',()=>{
 const log=vi.spyOn(console,'error').mockImplementation(()=>{});onRequestError(Object.assign(new Error('Secret content'),{digest:'abc123'}),{method:'GET',path:'/private?token=secret',headers:{cookie:'private-session'}},{routePath:'/private',routeType:'render',routerKind:'App Router'} as never);const record=JSON.parse(log.mock.calls[0][0]);expect(record).toMatchObject({event:'tutorlink.server_error',route:'/private',digest:'abc123'});expect(JSON.stringify(record)).not.toContain('secret');
});
