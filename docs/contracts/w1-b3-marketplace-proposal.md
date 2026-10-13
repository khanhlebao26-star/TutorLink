# B3 → A3 integration proposal

Ngày 13/10/2026. Issue [#15](https://github.com/khanhlebao26-star/TutorLink/issues/15), baseline `5871441`. **Đề xuất frontend, chưa phải contract A3 được phê duyệt.** Người dùng yêu cầu làm B3 trước A3 đang triển khai. Không sửa OpenAPI/backend hoặc tuyên bố API đã có. Khi A3 bàn giao, đối chiếu từng field/action/query và sửa adapter trước nghiệm thu G1.

## Quyết định và giả định

- Giữ `/marketplace` và `/marketplace/[slug]`; thêm owner `/me/listings`, `/me/listings/new`, `/me/listings/[id]`, `/me/listings/[id]/edit`, `/me/availability`; public `/tutors/[slug]`, `/tutors/[slug]/reviews`; landing `/become-a-tutor`.
- Tái sử dụng client Axios, Sanctum cookie/CSRF, token, Inter, UI controls và `useAsyncData`. Không thêm state library; URL là nguồn search state. Hook hiện hữu bỏ response cũ theo loader/attempt.
- Marketplace luôn gọi API, kể cả preview Auth được bật. Mặc định client chuyển sang `api`; mock chỉ còn chế độ opt-in của màn hình Auth/onboarding cũ. Owner shell kiểm `/me` thật trước mount children.
- Không suy diễn quyền từ approval/status. Owner action chỉ từ `allowed_actions`; create từ `meta.allowed_actions`. Draft eligibility và publish predicate thuộc A3, chưa xác nhận.
- Giá VND integer exponent 0; `price_unit` đề xuất `hour/session/package`. Chỉ lọc/sort giá khi chọn unit. A3 cần chốt whitelist này, query key và sort value.
- Weekly availability đề xuất ISO weekday 1 (Monday) … 7 (Sunday), `HH:mm`, timezone IANA, start < end cùng ngày; khung liền nhau được phép, overlap bị chặn. Đổi timezone giữ giờ địa phương, không convert sang timezone trình duyệt. A3 vẫn phải validate và trả 422.
- Mục tiêu NFR kế thừa docs: 5.000 accounts/50 concurrent, API thường p95 <2s/search <3s là mục tiêu chưa đo; phân trang server; không thêm cache/search engine. Backend chịu role/owner/privacy/visibility và lifecycle atomic. Frontend giữ input khi lỗi, cho retry/re-auth và không log credential. B3 sở hữu UI/adapter; Khanh xác nhận A3, Hoàng review behavior/G1.

Đã cân nhắc chỉ dựng mock UI (không đáp ứng API thật), mở rộng backend trong B3 (vượt ownership), và FE gọi API theo adapter/DTO đề xuất. Chọn phương án thứ ba theo yêu cầu làm trước A3. Block thiếu nguồn dữ liệu được ẩn; trạng thái lỗi thật vẫn hiển thị và cho retry.

## Wire proposal

Executable types: `frontend/src/lib/data/marketplace-types.ts`; requests: `frontend/src/lib/data/marketplace.ts`. Naming và cấu trúc dưới đây phải được A3 xác nhận, không dùng làm bằng chứng contract final.

| API | Request | Response dự kiến |
| --- | --- | --- |
| GET `/catalog/categories` | Không query | `{data: CatalogCategory[]}` nested subcategories/specializations, chỉ active; đây là endpoint hiện hữu |
| GET `/listings` | `q`, `category_id`, `subcategory_id`, `specialization_id`, `delivery_mode`, `city`, `min_fee`, `max_fee`, `price_unit`, `sort=newest/price_asc/price_desc`, `page`, `per_page` | `{data: PublicListing[], meta:{current_page,per_page,total}}`; total toàn kết quả sau filter |
| GET `/listings/{slug}` | slug encoded | `{data:PublicListing}` active/public predicate; optional `tutor`, category/subcategory/specialization names, rating, weekly `availability` |
| GET `/me/listings` | `status`, `page` | `{data:OwnerListing[],meta:{current_page,per_page,total,status_counts:{all,draft,active,paused,hidden,archived},allowed_actions:["create"]}}` |
| GET `/me/listings/{id}` | positive integer id | `{data:OwnerListing}` với `allowed_actions:[edit,publish,pause,archive]` là tập con theo actor/state |
| POST `/me/listings` | `title`, `description`, `specialization_id`, `delivery_mode`, `city`, `district`, `price_from_minor`, `currency=VND`, `price_unit`; **không gửi status** | `{data:OwnerListing}` Draft, quyền create vẫn được kiểm server |
| PATCH `/me/listings/{id}` | Edit fields như create; hoặc riêng `{status:"active"}` publish/`{status:"paused"}` pause | `{data:OwnerListing}`; kiểm action/publish predicate server; 409 conflict |
| DELETE `/me/listings/{id}` | Không body | 204 archive terminal. **Khác OpenAPI cũ “delete draft”; A3 phải đổi semantics để giữ FK/Program. Chưa được nghiệm thu.** |
| GET `/tutors/{slug}` | public slug encoded | `{data:{slug,full_name,headline,experience_years,avatar_url?,bio?,rating?,listings:PublicListing[],availability?}}` |
| GET `/tutors/{slug}/reviews` | `page`, `per_page=5` | `{data:[{id,author_name,rating,comment,created_at}],meta:{current_page,per_page,total},aggregate?:{average,count,histogram?}}` |
| GET/PUT `/me/availability-rules` | PUT `{timezone,rules:[{weekday,start_time,end_time}]}` | `{data:{timezone,rules}}`; 422 field errors indexed `rules.0.start_time` |

Listing base fields reuse `Listing` in OpenAPI: `id,tutor_profile_id,specialization_id,slug,title,description,delivery_mode,city?,district?,price_from_minor,currency,price_unit,status,created_at,updated_at`. Owner proposal replaces legacy status with `draft/active/paused/hidden/archived`, never maps `inactive` ambiguously. Public status `active` only. Public DTO is distinct from authenticated TutorProfile; no email, verification IDs/documents, private object key, moderation reason or permission data is rendered.

`TutorSummary` = public slug, full_name, headline, experience_years, optional avatar_url/rating. `rating` = `{average,count,histogram?}`; histogram only when supplied as aggregate on all visible reviews, never computed from a page. Service detail uses supplied Listing rating and optional Tutor summary; no claim that Tutor reviews are service-specific.

## Errors and lifecycle

Error envelope reuses `{message,errors?}` with 401/419/403/404/409/422/429. Cookie mutation always initializes CSRF. Form stays mounted on save failure; 422 maps fields; 409 re-reads detail/permissions while preserving edits; user can explicitly choose the newer server data. Missing counts/actions/pagination fails closed instead of inferring totals or permissions. Frontend controls are UX only: backend must reject crafted calls, owner cross-access, hidden/archived edit, pending/suspended publish.

Availability has no booking/reservation, neither a timezone conversion nor date-specific exception contract. Empty weekly rules clears availability under this proposal; A3 must confirm replacement semantics. No owner status select allows Hidden, Archived or initial Active creation. Archive button says **Lưu trữ**, never Xóa.

## Chưa chốt với A3

Draft eligibility for pending/unverified/rejected Tutor; statuses/actions and transition payloads; DELETE archive semantics/terminal behavior; per-page limits and sorting whitelist; price units and parameter spelling; optional public Tutor/Listing names/avatar policy; review aggregate scope and DTO; availability weekday/timezone/replacement rules; concurrency/version handling; featured query/config. UI hides featured/statistics/testimonials/gallery/video/service-specific FAQ/curriculum/related/Favorites/newsletter/chat until corresponding source/contract exists. Marketing FAQ is static informational copy, using native details/summary.

Browser URL uses `category`, `subcategory`, `specialization`; the adapter sends `category_id`, `subcategory_id`, `specialization_id` to the API. These are IDs from the live nested catalog, never slug/ID guesses.
