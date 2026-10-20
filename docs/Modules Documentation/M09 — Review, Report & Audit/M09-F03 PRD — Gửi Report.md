# M09-F03 · Gửi Report

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Người dùng đăng nhập báo cáo tài nguyên thuộc allow-list để Admin có thể xem xét vi phạm.

# 2\. Dữ liệu và quyền

* Report gồm reporter, resource\_type/resource\_id, reason\_code, description, status open/resolved/dismissed, thời điểm và decision metadata.  
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

* message là ReportResourceType; conversation không phải resource type được report. M09-F04 cần chốt riêng khi nào Admin được xem phần Conversation liên quan làm evidence, phạm vi, retention, assignment/permission và audit. Report một message không mở quyền đọc chat riêng tư ngoài scope đó.  
* Chốt report duplicate policy, giới hạn description, rate limit và quyền guest/customer/tutor gửi.  
* Report API chưa có trong repo.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edi  
Đồng bộ 18/10/2026 — Theo WF06, reason=other yêu cầu description; wire canonical là details. Khi reason=other, details phải là string có ít nhất một ký tự không phải whitespace; thiếu/null/rỗng/toàn whitespace trả 422\. Các reason khác giữ details optional/nullable. Chưa chốt giới hạn độ dài hoặc duplicate policy thì tiếp tục giữ các phần đó Pending; không mở thêm reason/resource type.  
t
