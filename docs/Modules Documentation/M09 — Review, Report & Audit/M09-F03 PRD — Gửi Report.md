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

# 3\. Tiêu chí chấp nhận

* Resource/reason code trong allow-list tạo Report open.  
* Unknown type/id hoặc user không đủ quyền bị từ chối mà không rò rỉ resource.  
* Public response không lộ reporter identity hoặc evidence private.  
* Duplicate, rate limit và report cùng resource policy được thực thi sau khi Product chốt.

# 4\. Chênh lệch/câu hỏi mở

* `message` là ReportResourceType; `conversation` không phải resource type được report. BR-11 đã chốt chỉ Admin active có `admin.reports.view`, assignment hiện tại và Report `open` được đọc message bị báo cáo cùng tối đa 10 tin trước/5 tin sau trong đúng Conversation; mỗi lần đọc phải audit, Report đóng hoặc đổi assignment thì ngừng quyền evidence. M09-F04/M04-F08 mô tả chi tiết; field masking và retention vẫn cần đặc tả riêng.
* Chốt report duplicate policy, giới hạn `description` và rate limit. Quyền gửi thuộc Customer/Tutor đã đăng nhập theo Roles & Actors; guest không được gửi Report.
* Report API chưa có trong repo.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit

Khi `reason_code=other`, API yêu cầu `description` là chuỗi có ít nhất một ký tự không phải khoảng trắng; thiếu/null/rỗng/toàn khoảng trắng trả 422. Với lý do khác, `description` tùy chọn và có thể null. Cột lưu trữ vẫn là `reports.details`; không tạo thêm cột `description`. Giới hạn độ dài và duplicate policy còn chờ chốt; không mở thêm reason/resource type.
