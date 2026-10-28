# M09-F03 · Gửi Report

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Người dùng đăng nhập báo cáo tài nguyên thuộc allow-list để Admin có thể xem xét vi phạm.

# 2\. Dữ liệu và quyền

* API Report dùng `reason_code` và `description` trong request/response; máy chủ ánh xạ lần lượt sang cột `reports.reason` và `reports.details`. Report còn có reporter, resource\_type/resource\_id, status open/resolved/dismissed, thời điểm và decision metadata.
* ReportResourceType theo Enum Catalog/Database: tutor\_profile, listing, message, program, review. ReportReasonCode: spam, harassment, inappropriate\_content, fraud\_suspected, misleading\_information, privacy\_violation, other.  
* Reporter chỉ gửi report cho resource tồn tại và resource type được phép; không dùng Report để đọc resource private.  
* Một report không làm resource tự động ẩn/đình chỉ; moderation là quyết định riêng theo permission.  
* Mọi quyết định/đổi trạng thái cần reason và audit.
* Mỗi reporter chỉ có tối đa một Report `open` cho cùng `resource_type/resource_id`; gửi lại cùng Idempotency-Key trả kết quả đã lưu, còn gửi key mới khi đã có Report open trả 409. Sau Report đóng, có thể gửi Report mới cho resource đó.
* Quota tối đa 10 Report/người/giờ và 30 Report/người/ngày. Vượt quota trả 429 cùng `Retry-After`; retry cùng Idempotency-Key đã ghi nhận không tính quota lần nữa.
* `description` tối đa 2.000 ký tự. Khi `reason_code=other`, description bắt buộc có ký tự không phải khoảng trắng; lý do khác được để null hoặc lược bỏ.

# 3\. Tiêu chí chấp nhận

* Resource/reason code trong allow-list tạo Report open.  
* Unknown type/id hoặc user không đủ quyền bị từ chối mà không rò rỉ resource.  
* Public response không lộ reporter identity hoặc evidence private.  
* Duplicate, quota, giới hạn description và Report open trên cùng resource tuân các quy tắc trên.

# 4\. Chênh lệch/câu hỏi mở

* `message` là ReportResourceType; `conversation` không phải resource type được report. BR-11/BR-24 chốt chỉ Admin active có `admin.reports.view`, assignment hiện tại và Report `open` được đọc evidence allow-list; với message chỉ gồm message bị báo cáo cùng tối đa 10 tin trước/5 tin sau trong đúng Conversation. Mỗi lần đọc phải audit; đóng Report hoặc đổi assignment thì ngừng quyền evidence. M09-F04/M04-F08 mô tả chi tiết.
* Quyền gửi thuộc Customer/Tutor đã đăng nhập theo Roles & Actors; guest không được gửi Report. Các giới hạn trên đã chốt ngày 21/10/2026; API còn phải triển khai.
* Report API chưa có trong repo.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
