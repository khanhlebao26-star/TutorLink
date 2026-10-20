# M08 — Fee, Announcement & Notification

## PRD Index · 4 chức năng · Draft

# Mục tiêu

Ghi nhận khoản phí Tutor khai báo, công bố Announcement trong Program, phát Notification sau commit và cung cấp inbox riêng theo recipient.

# PRD chức năng

[M08-F01 PRD — Quản lý Fee Record](https://docs.google.com/document/d/1fb9VxGqYJ-G_6bv-uzT7dzY8gjw9V5z1Kz0iOA46_Hs/edit)  
[M08-F02 PRD — Tạo và công bố Announcement](https://docs.google.com/document/d/1NxmriEAiAsw43_jHRwu_qH8iBVOgjyBo6oYO2418Gd4/edit)  
[M08-F03 PRD — Phát sinh và chuyển Notification](https://docs.google.com/document/d/1bjM83iXY_-z7utMdN7qMvPzS-GOI0cbUtJoNt-FpIBU/edit)  
[M08-F04 PRD — Notification Inbox và trạng thái đã đọc](https://docs.google.com/document/d/19JpjWOLQwwdC3Lv-smGDz_90bX1ZUpHKvixXfHHrCUk/edit)

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
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
