/* eslint-disable @next/next/no-img-element -- DOM-only Image test double. */
import { MarketplaceSearch } from '@/components/marketplace/marketplace-search';
import { PublicTutor } from '@/components/tutors/public-tutor';
import { ListingDetail } from '@/components/marketplace/listing-detail';
import { ListingForm } from '@/components/marketplace/listing-form';
import { OwnerListings, OwnerListingDetail } from '@/components/marketplace/owner-listings';
import * as api from '@/lib/data/marketplace';
import { ApiClientError } from '@/lib/api/client';
import { catalog, listing, meta, owner, tutor, reviews } from './fixtures';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, expect, it, vi } from 'vitest';
const nav=vi.hoisted(()=>({push:vi.fn(),params:new URLSearchParams()}));
vi.mock('next/navigation',()=>({useRouter:()=>({push:nav.push}),usePathname:()=>'/marketplace',useSearchParams:()=>nav.params}));
vi.mock('@/components/layout/app-shell',()=>({useActor:()=>null}));
vi.mock('next/image',()=>({default:({alt,src}:{alt:string;src:string})=><img alt={alt} src={src}/>}));
vi.mock('@/lib/data/marketplace',async original=>({...await original<typeof import('@/lib/data/marketplace')>(),getMarketplaceCatalog:vi.fn(),searchListings:vi.fn(),getPublicTutor:vi.fn(),getTutorReviews:vi.fn(),getPublicListing:vi.fn(),getOwnerListings:vi.fn(),getOwnerListing:vi.fn(),createOwnerListing:vi.fn(),updateOwnerListing:vi.fn(),transitionOwnerListing:vi.fn(),archiveOwnerListing:vi.fn()}));
beforeEach(()=>{vi.resetAllMocks();nav.params=new URLSearchParams();vi.mocked(api.getMarketplaceCatalog).mockResolvedValue(catalog);vi.mocked(api.searchListings).mockResolvedValue({data:[listing],meta});vi.mocked(api.getPublicTutor).mockResolvedValue(tutor);vi.mocked(api.getTutorReviews).mockResolvedValue(reviews);vi.mocked(api.getPublicListing).mockResolvedValue(listing);vi.mocked(api.getOwnerListing).mockResolvedValue(owner);vi.mocked(api.getOwnerListings).mockResolvedValue({data:[owner],meta:{...meta,status_counts:{all:1,draft:1,active:0,inactive:0,cancelled:0},allowed_actions:['create']}});});
it('searches and navigates with URL filters rather than filtering a partial result in memory',async()=>{
 render(<MarketplaceSearch/>);await screen.findByRole('heading',{name:listing.title});await userEvent.type(screen.getByLabelText('Tìm dịch vụ'),'IELTS');await userEvent.click(screen.getByRole('button',{name:/Tìm kiếm/}));expect(nav.push).toHaveBeenCalledWith('/marketplace?q=IELTS',{scroll:false});await userEvent.selectOptions(screen.getByLabelText('Lĩnh vực'),'1');expect(nav.push).toHaveBeenLastCalledWith(expect.stringContaining('category=1'),{scroll:false});
});
it('shows a recoverable API failure and an empty marketplace',async()=>{
 vi.mocked(api.searchListings).mockRejectedValueOnce(new ApiClientError('Search offline'));render(<MarketplaceSearch/>);expect(await screen.findByText(/Search offline/)).toBeVisible();vi.mocked(api.searchListings).mockResolvedValue({data:[],meta:{...meta,total:0}});await userEvent.click(screen.getByRole('button',{name:/Thử lại/}));await waitFor(()=>expect(api.searchListings).toHaveBeenCalledTimes(2));
});
it('renders a public tutor and switches between service, review and schedule views',async()=>{
 render(<PublicTutor slug="anna"/>);await screen.findByRole('heading',{name:'Anna Tutor'});expect(screen.getByRole('link',{name:'Facebook của Anna Tutor'})).toHaveAttribute('href','https://facebook.com/example');
 for(const tab of ['Dịch vụ','Đánh giá','Lịch khả dụng']){const buttons=screen.queryAllByRole('button',{name:new RegExp(tab)});if(buttons.length)await userEvent.click(buttons[0]);}
 expect(api.getPublicTutor).toHaveBeenCalledWith('anna',1);expect(api.getTutorReviews).toHaveBeenCalledWith('anna',1);
});
it('renders a server-prerendered tutor profile immediately without a duplicate first-page API read',async()=>{
 render(<PublicTutor slug="anna" initialTutor={tutor}/>);
 expect(screen.getByRole('heading',{name:'Anna Tutor'})).toBeVisible();
 await waitFor(()=>expect(api.getPublicTutor).not.toHaveBeenCalled());
});
it('keeps direct tutor tab links working with static profile output',async()=>{
 window.history.replaceState(null,'','/tutors/anna?tab=reviews');
 render(<PublicTutor slug="anna" initialTutor={tutor}/>);
 expect(screen.getByRole('tab',{name:'Đánh giá'})).toHaveAttribute('aria-selected','true');
 window.history.replaceState(null,'','/marketplace');
});
it('uses the optional initial tab only as a starting value',async()=>{
 window.history.replaceState(null,'','/tutors/anna');
 render(<PublicTutor slug="anna" initialTutor={tutor} initialTab="reviews"/>);
 expect(screen.getByRole('tab',{name:'Đánh giá'})).toHaveAttribute('aria-selected','true');
 await userEvent.click(screen.getByRole('tab',{name:'Dịch vụ'}));
 expect(screen.getByRole('tab',{name:'Dịch vụ'})).toHaveAttribute('aria-selected','true');
 window.history.replaceState(null,'','/marketplace');
});
it('renders user-provided tutor text as escaped React text rather than raw HTML',()=>{
 const unsafeText='<img src=x onerror=alert(1)>';
 const view=render(<PublicTutor slug="anna" initialTutor={{...tutor,bio:unsafeText}}/>);
 expect(view.container.textContent).toContain(unsafeText);
 expect(view.container.querySelector('img[src="x"]')).toBeNull();
});
it('does not invent profile statistics for a former demo listing',async()=>{
 vi.mocked(api.getPublicTutor).mockResolvedValue({...tutor,listings:[{...listing,slug:'demo-featured-math-online'}]});
 const view=render(<PublicTutor slug="anna"/>);await screen.findByRole('heading',{name:'Anna Tutor'});
 expect(view.container.querySelector('.tutor-profile-student-total strong')).toHaveTextContent('—');
 expect(view.container.querySelector('.tutor-profile-response strong')).toHaveTextContent('chưa cập nhật');
});
it('displays profile statistics supplied by the API, including zero students',async()=>{
 vi.mocked(api.getPublicTutor).mockResolvedValue({...tutor,students_taught_count:0,response_time_minutes:30});
 const view=render(<PublicTutor slug="anna"/>);await screen.findByRole('heading',{name:'Anna Tutor'});
 expect(view.container.querySelector('.tutor-profile-student-total strong')).toHaveTextContent('0');
 expect(view.container.querySelector('.tutor-profile-response strong')).toHaveTextContent('< 30 phút');
});
it('suggests distinct tutors and keeps the related cards useful when their reviews fail',async()=>{
 const ben={...listing,slug:'ben-english',tutor_slug:'ben',tutor_name:'Ben Tutor',category_name:'Language'};
 const benOther={...ben,slug:'ben-math'};const noTutor={...listing,slug:'no-tutor',tutor_slug:null,tutor_name:null};
 vi.mocked(api.searchListings).mockResolvedValue({data:[listing,ben,benOther,noTutor],meta} as never);vi.mocked(api.getTutorReviews).mockImplementation(async slug=>{if(slug==='ben')throw new ApiClientError('Reviews unavailable');return reviews;});
 render(<PublicTutor slug="anna"/>);expect(await screen.findByRole('link',{name:/Ben Tutor/})).toHaveAttribute('href','/tutors/ben');expect(screen.getByText('Gia sư TutorLink')).toBeVisible();expect(api.getTutorReviews).toHaveBeenCalledWith('ben',1);expect(screen.queryByRole('link',{name:/no-tutor/})).not.toBeInTheDocument();
});
it('shows review recovery and requests the next page of tutor reviews',async()=>{
 const secondPage={...reviews,data:[{...reviews.data[0],id:2,author_name:'Learner Two',comment:null,created_at:'invalid-date'}],meta:{...reviews.meta,total:10,current_page:1,per_page:5,last_page:2}};
 vi.mocked(api.getTutorReviews).mockRejectedValueOnce(new ApiClientError('Reviews unavailable')).mockResolvedValueOnce(secondPage).mockResolvedValueOnce({...secondPage,meta:{...secondPage.meta,current_page:2}});
 const view=render(<PublicTutor slug="anna" initialTab="reviews"/>);expect(await screen.findByText(/Không tải được đánh giá/)).toBeVisible();await userEvent.click(screen.getByRole('button',{name:'Thử lại'}));expect(await screen.findByText('Learner Two')).toBeVisible();expect(screen.getByText('Học viên đã gửi đánh giá cho buổi học này.')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:/Tiếp/}));await waitFor(()=>expect(api.getTutorReviews).toHaveBeenCalledWith('anna',2));expect(view.container.querySelector('.review-pagination')).toHaveTextContent('Trang 2 / 2');
});
it.each([401,403,404,429,500])('does not display a tutor after API error %s',async(status)=>{
 vi.mocked(api.getPublicTutor).mockRejectedValue(new ApiClientError('Denied',{status}));render(<PublicTutor slug="anna"/>);await screen.findByRole('alert');expect(screen.queryByRole('heading',{name:'Anna Tutor'})).not.toBeInTheDocument();
});
it('loads listing price, tutor and public reviews',async()=>{
 render(<ListingDetail slug="english"/>);await screen.findByRole('heading',{name:listing.title});expect(await screen.findByText('Helpful lessons')).toBeVisible();expect(screen.getAllByText(/200.000/).length).toBeGreaterThan(0);
});
it('publishes an owned draft and reloads server capabilities',async()=>{
 vi.mocked(api.transitionOwnerListing).mockResolvedValue({...owner,status:'active'});render(<OwnerListings/>);await screen.findByRole('heading',{name:owner.title});await userEvent.click(screen.getByRole('button',{name:'Công khai'}));expect(await screen.findByText('Đã cập nhật dịch vụ.')).toBeVisible();expect(api.transitionOwnerListing).toHaveBeenCalledWith(1,'active');await waitFor(()=>expect(api.getOwnerListings).toHaveBeenCalledTimes(2));
});
it('loads an owner detail and presents denied management access',async()=>{
 const view=render(<OwnerListingDetail id={1}/>);await screen.findByRole('heading',{name:owner.title});view.unmount();vi.mocked(api.getOwnerListings).mockRejectedValue(new ApiClientError('Denied',{status:403}));render(<OwnerListings/>);expect(await screen.findByRole('link',{name:'Mở Hồ sơ Tutor'})).toHaveAttribute('href','/profile');
});
it('does not archive until confirmed, then refreshes the owner listing collection',async()=>{
 const confirm=vi.spyOn(window,'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);vi.mocked(api.archiveOwnerListing).mockResolvedValue();render(<OwnerListings/>);await screen.findByRole('heading',{name:owner.title});
 await userEvent.click(screen.getByRole('button',{name:'Lưu trữ'}));expect(api.archiveOwnerListing).not.toHaveBeenCalled();
 await userEvent.click(screen.getByRole('button',{name:'Lưu trữ'}));expect(api.archiveOwnerListing).toHaveBeenCalledWith(owner.id);expect(await screen.findByText('Đã cập nhật dịch vụ.')).toBeVisible();await waitFor(()=>expect(api.getOwnerListings).toHaveBeenCalledTimes(2));confirm.mockRestore();
});
it('reloads the listing after a conflict and leaves other action errors visible',async()=>{
 vi.mocked(api.transitionOwnerListing).mockRejectedValueOnce(new ApiClientError('Listing changed',{status:409})).mockRejectedValueOnce(new ApiClientError('Network unavailable'));
 render(<OwnerListingDetail id={1}/>);await screen.findByRole('heading',{name:owner.title});await userEvent.click(screen.getByRole('button',{name:'Công khai'}));expect(await screen.findByText('Listing changed')).toBeVisible();await waitFor(()=>expect(api.getOwnerListing).toHaveBeenCalledTimes(2));
 await userEvent.click(screen.getByRole('button',{name:'Công khai'}));expect(await screen.findByText('Network unavailable')).toBeVisible();
});
it('filters owner services and shows the empty state for a server-filtered status',async()=>{
 vi.mocked(api.getOwnerListings).mockResolvedValueOnce({data:[owner],meta:{...meta,status_counts:{all:1,draft:1,active:0,inactive:0,cancelled:0},allowed_actions:['create']}}).mockResolvedValueOnce({data:[],meta:{...meta,total:0,status_counts:{all:1,draft:1,active:0,inactive:0,cancelled:0},allowed_actions:['create']}});
 const view=render(<OwnerListings/>);await screen.findByRole('heading',{name:owner.title});await userEvent.click(screen.getByRole('button',{name:/Đang hoạt động/}));expect(nav.push).toHaveBeenCalledWith('/marketplace?status=active',{scroll:false});nav.params=new URLSearchParams('status=active');view.rerender(<OwnerListings/>);await waitFor(()=>expect(api.getOwnerListings).toHaveBeenLastCalledWith({page:1,status:'active'}));expect(await screen.findByText('Không có dịch vụ ở trạng thái “Đang hoạt động”.')).toBeVisible();
});
it('paginates owner services from server metadata and retries an unavailable collection',async()=>{
 const paged={...meta,total:30,current_page:1,per_page:10};
 vi.mocked(api.getOwnerListings).mockResolvedValueOnce({data:[owner],meta:{...paged,status_counts:{all:30,draft:1,active:10,inactive:10,cancelled:9},allowed_actions:['create']}}).mockResolvedValueOnce({data:[{...owner,id:2,title:'Second page'}],meta:{...paged,current_page:2,status_counts:{all:30,draft:1,active:10,inactive:10,cancelled:9},allowed_actions:[]}});
 const view=render(<OwnerListings/>);await screen.findByRole('heading',{name:owner.title});await userEvent.click(screen.getByRole('button',{name:'Sau →'}));expect(nav.push).toHaveBeenCalledWith('/marketplace?page=2',{scroll:false});nav.params=new URLSearchParams('page=2');view.rerender(<OwnerListings/>);expect(await screen.findByRole('heading',{name:'Second page'})).toBeVisible();await waitFor(()=>expect(api.getOwnerListings).toHaveBeenLastCalledWith({page:2,status:'all'}));
 vi.mocked(api.getOwnerListings).mockRejectedValueOnce(new ApiClientError('Services offline'));view.unmount();render(<OwnerListings/>);expect(await screen.findByText('Services offline')).toBeVisible();vi.mocked(api.getOwnerListings).mockResolvedValue({data:[],meta:{...meta,total:0,status_counts:{all:0,draft:0,active:0,inactive:0,cancelled:0},allowed_actions:[]}});await userEvent.click(screen.getByRole('button',{name:/Thử lại/}));expect(await screen.findByText('Bạn chưa có dịch vụ nào.')).toBeVisible();
});
it('renders owner detail not-found recovery and can refresh the record',async()=>{
 vi.mocked(api.getOwnerListing).mockRejectedValueOnce(new ApiClientError('Missing',{status:404})).mockResolvedValueOnce(owner);
 render(<OwnerListingDetail id={1}/>);expect(await screen.findByText('Không tìm thấy dịch vụ này.')).toBeVisible();await userEvent.click(screen.getByRole('button',{name:/Thử lại/}));expect(await screen.findByRole('heading',{name:owner.title})).toBeVisible();expect(api.getOwnerListing).toHaveBeenCalledTimes(2);
});
it('creates a validated draft and preserves input when the server rejects it',async()=>{
 vi.mocked(api.createOwnerListing).mockRejectedValueOnce(new ApiClientError('Title rejected',{status:422,fieldErrors:{title:['Title rejected']}})).mockResolvedValueOnce(owner);
 render(<ListingForm/>);await screen.findByRole('heading',{name:'Tạo dịch vụ mới'});
 fireEvent.change(screen.getByLabelText('Tiêu đề dịch vụ *'),{target:{value:listing.title}});fireEvent.change(screen.getByLabelText('Mô tả dịch vụ *'),{target:{value:listing.description}});
 await userEvent.selectOptions(screen.getByLabelText('Danh mục *'),'1');await userEvent.selectOptions(screen.getByLabelText('Nhóm chuyên môn *'),'2');await userEvent.selectOptions(screen.getByLabelText('Chuyên ngành *'),'3');fireEvent.change(screen.getByLabelText('Giá từ (VND) *'),{target:{value:'200000'}});
 await userEvent.click(screen.getByRole('button',{name:'Lưu bản nháp'}));await screen.findAllByText('Title rejected');expect(screen.getByLabelText('Tiêu đề dịch vụ *')).toHaveValue(listing.title);await userEvent.click(screen.getByRole('button',{name:'Lưu bản nháp'}));await waitFor(()=>expect(nav.push).toHaveBeenCalledWith('/me/listings/1'));
});
it('edits an owned listing with its loaded content',async()=>{
 vi.mocked(api.updateOwnerListing).mockResolvedValue(owner);render(<ListingForm id={1}/>);await screen.findByDisplayValue(listing.title);await userEvent.click(screen.getByRole('button',{name:'Lưu thay đổi'}));await waitFor(()=>expect(api.updateOwnerListing).toHaveBeenCalledWith(1,expect.objectContaining({price_from_minor:200000,specialization_id:3})));
});
