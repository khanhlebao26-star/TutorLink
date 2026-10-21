# M03-F08 · Xem hồ sơ Gia sư công khai

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft · D06 đã chốt, runtime còn lệch

# 1\. Mục tiêu và người dùng

Cho người chưa đăng nhập và Customer xem phần giới thiệu công khai của Gia sư đã đủ điều kiện, các Specialization và Listing active liên quan.

# 2\. Điều kiện công khai

* Tutor Profile phải approved, giấy tờ bắt buộc còn hiệu lực theo BR-16, User phải active, email đã verified và Tutor không suspended; hồ sơ có public\_slug để định tuyến. Điều kiện email verified cho public Profile/Listing được Dũng xác nhận ngày 18/10/2026, thống nhất với OpenAPI/D06.
* Tutor Profile bị suspended hoặc User bị suspended thì hồ sơ và Listing liên quan bị ẩn công khai; Program đang hoạt động không tự hủy.  
* Khôi phục User hoặc Tutor Profile không đổi `ListingStatus`. Listing vốn `active` tự hiện lại khi mọi public gate thỏa; `inactive` vẫn ẩn cho tới khi Tutor tự publish; `archived` là terminal. Đây là tính lại khả năng hiển thị, không là action kích hoạt lại Listing.
* Gỡ moderation hold là luồng khác khôi phục Tutor Profile: nếu Listing inactive chỉ do Admin hide và đủ gate, restore hold chuyển lại active; nếu Tutor tự pause thì giữ inactive cho Tutor publish lại. Trong lúc hold, mọi public surface đều không trả Listing.
* Public visibility luôn tính theo User active, email verified, Profile approved/không suspended, giấy tờ bắt buộc còn hiệu lực, catalog chain active, Listing active và không có moderation hold. Admin restore hold là luồng riêng của M03-F10; khi hold đã chuyển Listing sang inactive, chỉ luồng restore hold có thể tự chuyển inactive do Admin về active theo điều kiện đã chốt.

# 3\. Dữ liệu công khai

Allow-list hồ sơ công khai đã chốt: `slug`, tên hiển thị (`name`), `headline`, `bio`, `experience_years`, `specializations`, `availability_rules`, `avatar` đủ điều kiện và trang `listings` đủ public gate. Specialization chỉ gồm tên/slug cùng tên Category/Subcategory để hiển thị; availability chỉ gồm weekday, start_time, end_time và timezone, gắn nhãn tham khảo, không là booking.

Avatar công khai chỉ khi thuộc Tutor đó, purpose `avatar`, File complete và toàn bộ public gate của Tutor đạt; dùng đường tải/URL công khai được M10 cấp theo policy, không trả object key hoặc storage path. Thiếu avatar hợp lệ thì `avatar: null`. Verification documents, email/điện thoại riêng tư, thông tin phân công/kiểm duyệt và dữ liệu xác minh nội bộ không xuất hiện trong public response.

Mỗi Listing nhúng là card tối thiểu: `slug`, `title`, `delivery_mode`, `city`, `district`, `price_from_minor`, `currency`, `price_unit` và tên Specialization. Không trả owner-only `allowed_actions`, trạng thái moderation hoặc dữ liệu chưa công khai. Availability được phép lưu trước khi Profile approved nhưng chỉ trả trong public response khi Tutor đủ public gate M03-F09; đình chỉ giữ rules riêng và ẩn khỏi public.

# 4\. Hành vi và API hiện có

Public route hiện có: GET /api/v1/tutors/{slug}. `listings_page` là query số nguyên từ 1, mặc định 1; mỗi trang nhúng tối đa 10 Listing, sắp xếp `id` giảm dần. Response giữ mảng `listings` và thêm `listings_meta` gồm `page`, `per_page=10`, `has_more`, `next_page` (null nếu hết). Trang sau gọi lại chính route này với `listings_page`; không thêm endpoint. Phân trang và đếm chỉ xét Listing vượt cùng public gate như GET /api/v1/listings, không trả một array vô hạn.

`public_slug` được server tạo một lần khi tạo Tutor Profile từ tên và hậu tố ID duy nhất; đổi tên hiển thị không đổi slug. Tutor không tự đổi slug trong MVP, nên URL cũ vẫn dùng chính slug đó và không cần redirect/alias. Slug không tồn tại hoặc hồ sơ không đủ điều kiện công khai trả not-found, không lộ hồ sơ.

# 5\. Tiêu chí chấp nhận

* Người dùng chưa đăng nhập xem được hồ sơ Tutor approved, active và không suspended theo slug công khai.  
* Profile suspended, User không active hoặc slug sai không làm lộ profile; Listing không active không xuất hiện trong danh sách nhúng.  
* Public response không chứa email/điện thoại riêng tư, giấy tờ xác minh, dữ liệu moderation nội bộ hoặc trạng thái kiểm duyệt chi tiết ngoài điều đã được duyệt công khai.  
* Availability được gắn nhãn tham khảo và không có thao tác đặt lịch.
* Trang Listing nhúng không quá 10 phần tử, `has_more`/`next_page` dẫn đến đúng trang tiếp theo trong cùng hồ sơ; thay page không đưa Listing đã mất public gate vào response.
* Đổi tên Tutor giữ nguyên slug; khôi phục User/Profile làm Listing `active` đủ gate tự hiện lại mà không đổi status, Listing `inactive` và `archived` vẫn ẩn.

# 6\. Khoảng cách triển khai

* Runtime hiện chưa kiểm email verified ở PublicTutorController/visiblePublic; phải thêm cùng public gate cho hồ sơ, search, detail, avatar và Listing nhúng.
* Runtime tải toàn bộ Listing visible bằng `get()`, chưa có `listings_page`/`listings_meta`; PublicTutorResource đang dùng ListingResource đầy đủ, cần DTO card allow-list và giới hạn 10.
* PublicTutorResource chưa trả avatar; FileController chưa cấp quyền tải avatar công khai theo M10. Đường tải phải kiểm lại public gate, không dùng URL công khai vĩnh viễn. Cache public phải invalidation khi User/Profile/Listing/catalog/hold đổi trạng thái; nếu chưa bảo đảm, không cache response riêng tư hoặc đã mất eligibility.
* Slug tạo một lần kèm ID đã phù hợp quy tắc đích; cần kiểm thử đổi tên không đổi slug và URL cũ còn hoạt động.

# 7\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edi  
D06 đã được Product chốt ngày 20/10/2026. Trang Listing nhúng tối đa 10 phần tử và lấy tiếp trên cùng route; chỉ coi response đạt PRD sau khi code, kiểm thử và OpenAPI Scramble được đồng bộ.
