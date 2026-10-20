# M03-F08 · Xem hồ sơ Gia sư công khai

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft · Public field allowlist còn mở

# 1\. Mục tiêu và người dùng

Cho người chưa đăng nhập và Customer xem phần giới thiệu công khai của Gia sư đã đủ điều kiện, các Specialization và Listing active liên quan.

# 2\. Điều kiện công khai

* Tutor Profile phải approved, User phải active, email đã verified và Tutor không suspended; hồ sơ có public\_slug để định tuyến. Điều kiện email verified cho public Profile/Listing được Dũng xác nhận ngày 18/10/2026, thống nhất với OpenAPI/D06.  
* Tutor Profile bị suspended hoặc User bị suspended thì hồ sơ và Listing liên quan bị ẩn công khai; Program đang hoạt động không tự hủy.  
* Khôi phục Tutor Profile không tự động kích hoạt lại Listing; mỗi Listing cần thỏa điều kiện visibility riêng  
* Đề xuất làm rõ (chưa chốt): khôi phục Tutor Profile không đổi ListingStatus; public visibility được tính lại từ gate hiện hành. Listing đang active chỉ hiện lại khi User active, email verified, Profile approved/không suspended, catalog chain active và không còn moderation hold nếu được duyệt. Listing inactive vẫn ẩn, archived không thể restore. Quy tắc hiện lại/publish lại và moderation hold vẫn chờ Product xác nhận; không tự thêm state.  
* .

# 3\. Dữ liệu công khai

API hiện trả name, headline, bio, experience\_years, specializations, availability\_rules và các Listing visible của Tutor. Public resource hiện không trả avatar; verification documents và dữ liệu xác minh không được công khai.  
Profile slug, avatar policy và allowlist cuối cùng cho public response cần Product xác nhận. Không suy ra rằng mọi field trong Tutor Profile có thể công khai.

# 4\. Hành vi và API hiện có

Public route hiện có: GET /api/v1/tutors/{slug}. Danh sách Listing nhúng trong hồ sơ phải áp dụng cùng visibility gate như GET /api/v1/listings.

# 5\. Tiêu chí chấp nhận

* Người dùng chưa đăng nhập xem được hồ sơ Tutor approved, active và không suspended theo slug công khai.  
* Profile suspended, User không active hoặc slug sai không làm lộ profile; Listing không active không xuất hiện trong danh sách nhúng.  
* Public response không chứa email/điện thoại riêng tư, giấy tờ xác minh, dữ liệu moderation nội bộ hoặc trạng thái kiểm duyệt chi tiết ngoài điều đã được duyệt công khai.  
* Availability được gắn nhãn tham khảo và không có thao tác đặt lịch.

# 6\. Câu hỏi mở cần Product chốt

* Cho phép avatar công khai hay không; nguồn avatar và quyền hiển thị.  
* Public slug do ai đặt/đổi, collision xử lý thế nào và khi đổi slug URL cũ phản hồi ra sao.  
* Chốt allowlist field công khai tối thiểu và policy cache/invalidate khi Tutor hoặc Listing bị suspend/inactive.

# 7\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edi  
PublicTutor.listings chưa có giới hạn/cách lấy tiếp được Product duyệt. D06 chặn phát hành response nhúng này cho đến khi chốt giới hạn trong contract hiện có; lượt này không thêm filter, cursor hoặc endpoint. Không coi schema array hiện tại là cho phép tải danh sách vô hạn theo PC-05.  
t
