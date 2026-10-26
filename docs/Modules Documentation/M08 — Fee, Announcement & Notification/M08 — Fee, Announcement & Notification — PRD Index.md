# M08 — Fee, Announcement & Notification

## PRD Index · 4 chức năng · Draft

# Mục tiêu

Ghi nhận khoản phí Tutor khai báo, công bố Announcement trong Program, phát Notification sau commit và cung cấp inbox riêng theo recipient.

# PRD chức năng

[M08-F01 PRD — Quản lý Fee Record](<M08-F01 PRD — Quản lý Fee Record.md>)  
[M08-F02 PRD — Tạo và công bố Announcement](<M08-F02 PRD — Tạo và công bố Announcement.md>)  
[M08-F03 PRD — Phát sinh và chuyển Notification](<M08-F03 PRD — Phát sinh và chuyển Notification.md>)  
[M08-F04 PRD — Notification Inbox và trạng thái đã đọc](<M08-F04 PRD — Notification Inbox và trạng thái đã đọc.md>)

# Quy tắc đích

* Fee: BR-22 chốt khai báo từ tháng Program kích hoạt đến hiện tại, không tạo kỳ tương lai; số tiền 0 là miễn phí, không có record là chưa khai báo; User chỉ xem đây là thông tin Tutor tự khai báo. Customer xem được lần cập nhật/lý do; mutation thành công phát `fee_record_changed` in-app sau commit.
* FeeRecordStatus declared/voided tách TutorPaymentMark unmarked/marked\_paid/marked\_unpaid; audit before/after/reason, void terminal vẫn chiếm kỳ. Khi Paused chỉ xử lý record có sẵn; sửa amount khi declared và chưa marked\_paid. Fee mutation dùng Idempotency-Key + `expected_version`; replay không nhân version/audit/notification. Amount 0 không được mark paid/unpaid và chỉ sửa về 0 khi mark vẫn unmarked.
* Announcement Draft/Published trong Program; Tutor owner được tạo/sửa Draft và publish khi Active/Paused; title 1–180, body 1–10.000 ký tự, tối đa 5 tệp 5 MiB/tệp; published bất biến, đính chính bằng Announcement mới. Draft chỉ owner đọc; Published chỉ member hiện tại đọc/tải file. Publish chốt recipient notification tại thời điểm đăng (trừ Tutor), ghi audit/outbox nguyên tử; member gia nhập sau đọc bài nhưng không nhận thông báo hồi tố. Danh sách mới nhất trước theo `(published_at,id)`; không có read state riêng. Fee/Announcement mutation dùng `version`/`expected_version` theo BR-19.
* Notification recipient riêng; event sau commit và event\_key unique; in\_app là kênh MVP.

# Implementation gaps

* Migration có fee\_records, announcements, notifications; API, worker/scheduler và event writer chưa có baseline.  
* Dữ liệu legacy `confirmed` phải được kiểm kê/xác minh ý nghĩa trước migration; không tự coi là payment thật. BR-22 chốt nghiệp vụ Fee; Enum/API/DB/runtime và NotificationType cần đồng bộ. Runtime hiện chưa có Fee API/event writer baseline.

# Nguồn

Product/Domain docs hoàn tất và WF-05; nguồn cụ thể liệt kê ở từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
