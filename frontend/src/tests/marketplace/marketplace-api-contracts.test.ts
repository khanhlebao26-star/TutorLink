import * as market from '@/lib/data/marketplace';
import { request,publicRequest } from '@/lib/api/client';
import { listing,owner,tutor,reviews,meta,catalog } from './fixtures';
import { beforeEach,expect,it,vi } from 'vitest';
vi.mock('@/lib/api/client',async original=>({...await original<typeof import('@/lib/api/client')>(),request:vi.fn(),publicRequest:vi.fn()}));
beforeEach(()=>vi.resetAllMocks());
const respond=(data:unknown)=>vi.mocked(request).mockResolvedValueOnce({data} as never);
it('enforces valid public listing DTO and explicit price units',async()=>{
 await expect(market.searchListings({min_price:1})).rejects.toMatchObject({status:422});expect(publicRequest).not.toHaveBeenCalled();
 vi.mocked(publicRequest).mockResolvedValueOnce({data:{data:[listing],meta}} as never);expect((await market.searchListings({price_unit:'hour',min_price:1})).data).toEqual([listing]);
 vi.mocked(publicRequest).mockResolvedValueOnce({data:{data:[{...listing,status:'draft'}],meta}} as never);await expect(market.searchListings({})).rejects.toThrow('công khai');
 vi.mocked(publicRequest).mockResolvedValueOnce({data:{data:[],meta:{...meta,total:-1}}} as never);await expect(market.searchListings({})).rejects.toThrow('phân trang');
 vi.mocked(publicRequest).mockResolvedValueOnce({data:{data:listing}} as never);expect((await market.getPublicListing('a/b')).id).toBe(1);expect(publicRequest).toHaveBeenLastCalledWith({method:'GET',url:'/listings/a%2Fb'});
 vi.mocked(publicRequest).mockResolvedValueOnce({data:{data:catalog}} as never);expect(await market.getMarketplaceCatalog()).toEqual(catalog);
});
it('validates owner capabilities and scopes all mutations to the owner API',async()=>{
 const collection={data:[owner],meta:{...meta,status_counts:{all:1,draft:1,active:0,inactive:0,cancelled:0},allowed_actions:['create']}};respond(collection);expect((await market.getOwnerListings({page:2,status:'draft'})).data).toEqual([owner]);
 respond({...collection,meta:{...collection.meta,allowed_actions:['delete_user']}});await expect(market.getOwnerListings()).rejects.toThrow('quản lý');respond({data:{...owner,allowed_actions:['unknown']}});await expect(market.getOwnerListing(1)).rejects.toThrow('quyền');
 respond({data:owner});expect(await market.getOwnerListing(1)).toEqual(owner);respond({data:owner});await market.createOwnerListing({...owner,city:null,district:null,currency:"VND",price_unit:"hour"});respond({data:owner});await market.updateOwnerListing(1,{...owner,city:null,district:null,currency:"VND",price_unit:"hour"});respond({data:owner});await market.transitionOwnerListing(1,'inactive');respond({});await market.archiveOwnerListing(1);expect(request).toHaveBeenLastCalledWith({method:'DELETE',url:'/me/listings/1'},true);
});
it('preserves review histogram integrity and tutor pagination',async()=>{
 respond({data:tutor});expect(await market.getPublicTutor('anna',2)).toEqual(tutor);expect(request).toHaveBeenLastCalledWith({method:'GET',url:'/tutors/anna',params:{listings_page:2}});
 respond(reviews);expect(await market.getTutorReviews('anna',1)).toEqual(reviews);respond({...reviews,aggregate:{...reviews.aggregate,count:99}});await expect(market.getTutorReviews('anna',2)).rejects.toThrow('nhận xét');
 respond({data:[{id:9,name:'Anna',headline:'English',specializations:[]}],meta});expect((await market.searchReportableTutors()).data).toHaveLength(1);respond({data:[{id:'bad'}],meta});await expect(market.searchReportableTutors()).rejects.toThrow('gia sư');
});
it('round-trips availability timezone and rules without forwarding unrelated fields',async()=>{
 const rules=[{weekday:0,start_time:'09:00',end_time:'10:00',timezone:'Asia/Ho_Chi_Minh'}];respond({data:rules});const availability=await market.getOwnerAvailability();expect(availability.timezone).toBe('Asia/Ho_Chi_Minh');respond({data:rules});expect(await market.saveOwnerAvailability(availability)).toEqual(availability);expect(request).toHaveBeenLastCalledWith({method:'PUT',url:'/me/availability-rules',data:{rules}},true);
});
