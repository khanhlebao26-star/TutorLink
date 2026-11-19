/* eslint-disable @next/next/no-img-element -- DOM-only Image test double. */
import { AvailabilityEditor } from '@/components/availability/availability-editor';
import { FavoritesList } from '@/components/favorites/favorites-list';
import { FavoriteTutorButton } from '@/components/favorites/favorite-tutor-button';
import * as market from '@/lib/data/marketplace';
import * as favorites from '@/lib/data/favorites';
import { validateAvailability } from '@/lib/data/availability-validation';
import { readListingQuery,updateListingQuery } from '@/lib/data/listing-query';
import { ApiClientError } from '@/lib/api/client';
import { tutor,reviews } from './fixtures';
import { render,screen,fireEvent,waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach,expect,it,vi } from 'vitest';
vi.mock('@/components/layout/app-shell',()=>({useActor:()=>({id:7,role:'customer'})}));
vi.mock('next/image',()=>({default:({alt,src}:{alt:string;src:string})=><img alt={alt} src={src}/>}));
vi.mock('@/lib/data/marketplace',async original=>({...await original<typeof import('@/lib/data/marketplace')>(),getOwnerAvailability:vi.fn(),saveOwnerAvailability:vi.fn(),getPublicTutor:vi.fn(),getTutorReviews:vi.fn()}));
vi.mock('@/lib/data/favorites',async original=>({...await original<typeof import('@/lib/data/favorites')>(),getFavoriteTutors:vi.fn(),addFavoriteTutor:vi.fn(),removeFavoriteTutor:vi.fn()}));
beforeEach(()=>{vi.resetAllMocks();vi.mocked(market.getOwnerAvailability).mockResolvedValue({timezone:'Asia/Ho_Chi_Minh',rules:[]});vi.mocked(favorites.getFavoriteTutors).mockResolvedValue([{slug:'anna',name:'Anna Tutor',headline:'English teacher'}] as never);vi.mocked(market.getPublicTutor).mockResolvedValue(tutor);vi.mocked(market.getTutorReviews).mockResolvedValue(reviews);});
it('rejects overlaps, invalid timezone and malformed hours before saving',()=>{
 expect(validateAvailability({timezone:'Bad/Zone',rules:[{weekday:9,start_time:'25:00',end_time:'01:00'}]})).toHaveProperty('timezone');expect(validateAvailability({timezone:'Asia/Ho_Chi_Minh',rules:[{weekday:0,start_time:'09:00',end_time:'11:00'},{weekday:0,start_time:'10:00',end_time:'12:00'}]})).toHaveProperty('rules.1.start_time');
 expect(readListingQuery(new URLSearchParams('page=-2&price_unit=hour&min_price=0&max_price=100&sort=price_asc&per_page=999'))).toMatchObject({page:1,per_page:50,min_price:0,max_price:100,sort:'price_asc'});expect(updateListingQuery('page=8&category=1&subcategory=2&specialization=3&unknown=x',{category:'4'})).toBe('category=4');expect(updateListingQuery('price_unit=hour&min_price=100&sort=price_asc',{price_unit:''})).toBe('');
});
it('adds a weekly slot and saves a validated schedule',async()=>{
 vi.mocked(market.saveOwnerAvailability).mockResolvedValue({timezone:'Asia/Ho_Chi_Minh',rules:[]});render(<AvailabilityEditor/>);await userEvent.click(await screen.findByRole('button',{name:'+ Thêm khung giờ Thứ Hai'}));fireEvent.change(screen.getByLabelText('Thứ Hai — kết thúc 1'),{target:{value:'11:00'}});await userEvent.click(screen.getByRole('button',{name:'Lưu lịch khả dụng'}));expect(await screen.findByText('Đã lưu lịch khả dụng.')).toBeVisible();expect(market.saveOwnerAvailability).toHaveBeenCalledWith({timezone:'Asia/Ho_Chi_Minh',rules:[{weekday:0,start_time:'09:00',end_time:'11:00'}]});
});
it('explains the profile prerequisite and keeps server failures recoverable',async()=>{
 vi.mocked(market.getOwnerAvailability).mockRejectedValue(new ApiClientError('Missing',{status:422}));render(<AvailabilityEditor/>);expect(await screen.findByRole('link',{name:'Mở Hồ sơ Tutor'})).toHaveAttribute('href','/profile');
});
it('removes a saved tutor after rendering public details',async()=>{
 vi.mocked(favorites.removeFavoriteTutor).mockResolvedValue(undefined);render(<FavoritesList/>);await screen.findByRole('heading',{name:'English conversation'});vi.mocked(favorites.getFavoriteTutors).mockResolvedValue([]);await userEvent.click(screen.getByRole('button',{name:'Bỏ yêu thích'}));await waitFor(()=>expect(favorites.removeFavoriteTutor).toHaveBeenCalledWith('anna'));await waitFor(()=>expect(screen.queryByRole('heading',{name:'English conversation'})).not.toBeInTheDocument());
});
it('preserves favorite state when a mutation fails and allows retry',async()=>{
 vi.mocked(favorites.removeFavoriteTutor).mockRejectedValueOnce(new ApiClientError('Try later')).mockResolvedValue(undefined);render(<FavoriteTutorButton slug="anna"/>);await userEvent.click(await screen.findByRole('button',{name:'Đã yêu thích'}));expect(await screen.findByText('Try later')).toBeVisible();expect(screen.getByRole('button',{name:'Đã yêu thích'})).toHaveAttribute('aria-pressed','true');await userEvent.click(screen.getByRole('button',{name:'Đã yêu thích'}));expect(await screen.findByRole('button',{name:'Yêu thích'})).toHaveAttribute('aria-pressed','false');
});
