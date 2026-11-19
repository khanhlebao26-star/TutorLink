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
