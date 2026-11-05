import {expect,it} from "vitest";
import {retryIdentity,feeLabel} from "@/lib/data/workspace";
it("keeps the UUID for the same retry but replaces it when payload changes",()=>{const previous=retryIdentity(null,{expected_version:2,content:"first"});expect(retryIdentity(previous,{expected_version:2,content:"first"})).toBe(previous);expect(retryIdentity(previous,{expected_version:2,content:"new"}).key).not.toBe(previous.key);});
it("keeps void separate from a tutor payment declaration",()=>{expect(feeLabel({id:1,version:1,status:"voided",tutor_payment_mark:"unmarked"})).toContain("Đã hủy khai báo");expect(feeLabel({id:1,version:1,status:"voided",tutor_payment_mark:"unmarked"})).not.toContain("Tutor đánh dấu đã trả");});
