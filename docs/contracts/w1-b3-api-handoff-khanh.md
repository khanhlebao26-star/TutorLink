# Bàn giao endpoint FE B3 cho Khanh (A3)

Ngày 13/10/2026, issue #15. FE làm trước BE theo yêu cầu. **Các shape dưới đây là đề xuất cần Khanh xác nhận, chưa sửa OpenAPI/backend.** Base path `/api/v1`. API local đang có catalog; toàn bộ endpoint Listing/Tutor/availability kiểm tra dưới đây trả 404.

## GET FE đã nối nhưng BE chưa có

| Endpoint                 | Query FE gửi                                                                                                          | Response FE cần                                                                                     |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `/listings`              | `q,category_id,subcategory_id,specialization_id,delivery_mode,city,min_price,max_price,price_unit,sort,page,per_page` | `{data:PublicListing[],meta:{current_page,per_page,total}}`; total toàn kết quả sau filter          |
| `/listings/{slug}`       | slug URL-encoded                                                                                                      | `{data:PublicListing}`; public active only                                                          |
| `/tutors/{slug}`         | public slug URL-encoded                                                                                               | `{data:TutorPublic}`; DTO public riêng, không dùng TutorProfile private                             |
| `/tutors/{slug}/reviews` | `page,per_page=5`                                                                                                     | `{data:VisibleReview[],meta:{current_page,per_page,total},aggregate?}`; chỉ visible reviews         |
| `/me/listings`           | `status?`, `page`                                                                                                     | `{data:OwnerListing[],meta:{current_page,per_page,total,status_counts,allowed_actions:["create"]}}` |
| `/me/listings/{id}`      | owner Listing ID positive integer                                                                                     | `{data:OwnerListing}`; quyền owner kiểm server, dùng cả detail và edit deep link                    |
| `/me/availability-rules` | không query                                                                                                           | `{data:{timezone,rules:[{weekday,start_time,end_time}]}}`                                           |

`GET /catalog/categories`, `GET /me`, và `GET /sanctum/csrf-cookie` đã có trong BE; không cần tạo lại. Catalog đang chỉ có một category local; A3 cần seed idempotent các nhánh Education/Fitness/Sports theo mapping được duyệt. `/sanctum/csrf-cookie` nằm ngoài `/api/v1`.

## Mutation FE cũng đã nối, chờ BE

| Method + endpoint            | Request                                                                                                        | Response và semantics cần chốt                                                                                                                                    |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `POST /me/listings`          | `{title,description,specialization_id,delivery_mode,city,district,price_from_minor,currency:"VND",price_unit}` | 201 `{data:OwnerListing}` Draft; **không gửi status**. Chốt eligibility Tutor pending/unverified/rejected.                                                        |
| `PATCH /me/listings/{id}`    | Edit fields như POST; hoặc riêng `{status:"active"}` publish / `{status:"paused"}` pause                       | 200 `{data:OwnerListing}`; kiểm quyền và lifecycle/publish predicate; 409 khi conflict.                                                                           |
| `DELETE /me/listings/{id}`   | Không body                                                                                                     | Đề xuất 204 **archive terminal**, không hard-delete, giữ FK/snapshot Program. **OpenAPI cũ là delete Draft: đây là khác biệt bắt buộc chốt trước tích hợp thật.** |
| `PUT /me/availability-rules` | `{timezone,rules:[{weekday,start_time,end_time}]}`                                                             | 200 `{data:Availability}`; replacement weekly schedule; 422 nếu invalid/overlap. Chốt clear bằng `rules:[]` và conflict semantics.                                |

## DTO và rule FE đang đọc

- `PublicListing`: fields Listing hiện hữu (`id,tutor_profile_id,specialization_id,slug,title,description,delivery_mode,city?,district?,price_from_minor,currency,price_unit,status,created_at,updated_at`); `status=active`; optional `category_name,subcategory_name,specialization_name,tutor,rating,availability`. FE ẩn block khi thiếu optional source.
- `OwnerListing`: Listing fields, `status=draft/active/paused/hidden/archived`, `allowed_actions` là tập con của `edit,publish,pause,archive`; taxonomy names optional. Hidden/Archived phải trả actions phù hợp quyền và không cho owner bypass lifecycle.
- `status_counts`: `{all,draft,active,paused,hidden,archived}` từ server; collection `allowed_actions:["create"]` hoặc `[]`. FE không tính count từ một page và không đoán quyền từ approval.
- `TutorPublic`: `{slug,full_name,headline,experience_years,avatar_url?,bio?,rating?,listings:PublicListing[],availability?}`. `tutor` summary trong Listing gồm `slug,full_name,headline,experience_years,avatar_url?,rating?`. Không trả email riêng, verification document/ID/object_key/moderation reason.
- `VisibleReview`: `{id,author_name,rating,comment,created_at}`. Aggregate `{average,count,histogram?:{"1":n,"2":n,"3":n,"4":n,"5":n}}` trên **toàn bộ visible reviews**, không từ page hiện tại. Listing rating có scope của Listing; Tutor aggregate có scope Tutor.
- Giá VND integer exponent 0 (`200000` → `200.000 VND`). FE đề xuất unit `hour/session/package`, sort `newest/price_asc/price_desc`; A3 chốt whitelist. Lọc min/max hoặc sort giá bắt buộc `price_unit`, không so sánh lẫn unit. URL frontend dùng `category/subcategory/specialization`; request API dùng `*_id`.
- Availability: ISO weekday **1=Thứ Hai … 7=Chủ Nhật**, `HH:mm`, IANA timezone (ví dụ `Asia/Ho_Chi_Minh`), start < end trong cùng ngày. Không overlap cùng weekday; hai khung liền nhau được phép. Lịch tham khảo, không booking, giờ hiển thị theo timezone DTO.
- Lỗi `{message,errors?}`; 422 indexed fields như `rules.0.start_time`, 401/419 re-auth/CSRF, 403/404 quyền/visibility, 409 refetch mà giữ draft, 429 throttling. Backend chịu enforcement; FE ẩn nút không thay backend policy.

Implementation: [marketplace.ts](../../frontend/src/lib/data/marketplace.ts), [marketplace-types.ts](../../frontend/src/lib/data/marketplace-types.ts). Proposal đầy đủ: [w1-b3-marketplace-proposal.md](w1-b3-marketplace-proposal.md). Chưa có gallery/Favorites/chat/featured query/config hoặc booking contract; FE không gửi các request này.
