# M05 — Program & Invitation

## PRD Index · 6 chức năng · Draft

# Mục tiêu

Tạo thỏa thuận riêng giữa Tutor và một Customer, gửi Invitation có snapshot bất biến, kích hoạt Program nguyên tử khi đúng Customer accept, rồi quản lý lifecycle Program.

# PRD chức năng

[M05-F01 PRD — Tạo và chỉnh sửa Program Draft](https://docs.google.com/document/d/1TWKqkStzG96RcZ5zPrHieOiplsOH2U_Dbpowvk2YyYw/edit)  
[M05-F02 PRD — Gửi Invitation và khóa snapshot](https://docs.google.com/document/d/1jsjP2VyMgYIFan8w-TEkpffGTsEF1tOzjmaYDnygz5g/edit)  
[M05-F03 PRD — Accept Invitation và kích hoạt Program](https://docs.google.com/document/d/13ptX86UtirBpjJb5PNF4zcChVDHDrhXnE7HZtVa7oKA/edit)  
[M05-F04 PRD — Reject hoặc cancel Invitation](https://docs.google.com/document/d/1rAsm-iB-0WH4S0MIHzD9GReY3xdbq85TSRiFEYUp_YQ/edit)  
[M05-F05 PRD — Hết hạn và gửi lại Invitation](https://docs.google.com/document/d/1ujKIomvbF4163NgZxHELoMwz7RnYMxortfbmXvIJnWE/edit)  
[M05-F06 PRD — Quản lý lifecycle Program](https://docs.google.com/document/d/1SDcNif47y887z3uR7HVYwwfTjAQMee8Sv3DN98J-N5I/edit)

# Quy tắc Product/Domain

* Tutor đủ điều kiện tạo Program; draft có một Customer target và offer VND.  
* Invitation có hạn 72 giờ, snapshot bất biến, tối đa một pending mỗi Program.  
* Accept đúng Customer tạo membership và Program Active trong một transaction; retry không tạo trùng.  
* Program: draft → active/paused/completed/cancelled theo lifecycle; terminal readonly; complete không tự hoàn thành Task.
* BR-18: offer Draft version tăng theo edit, `expected_version` bảo vệ PATCH/send; pending khóa Draft. Expiry UTC chặt tại `expires_at`; Draft cancel thu hồi Invitation pending nguyên tử; Program Active/Paused chỉ cancel sau khi xử lý Session/Task mở. Invitation cũ không accept trong lúc source Listing hold, nhưng có thể accept sau khi gỡ hold trước expiry.

# Implementation gaps

* Schema/migration có vocabulary legacy: Program pending và Invitation active; thiếu/mapping target\_customer\_id, snapshot/version, target uniqueness cần được review.  
* Program API chưa có trong routes; migration tồn tại không đồng nghĩa endpoint/transaction đã được triển khai.  
* Expiry worker/scheduler và event\_key idempotency chưa có trong baseline. Lock/isolation của accept với expiry/cancel/suspend/moderation cần test cạnh tranh PostgreSQL; route/API chưa được triển khai.

F19 — Bằng chứng current-state (18/10/2026): nội dung mục này là baseline của lượt rà soát trước, chưa được kiểm tra lại repository/commit trong lượt Drive-only này. Owner xác minh: chưa gán; ngày/commit kiểm chứng: chưa có; trạng thái: chưa xác minh. Migration tồn tại không tự chứng minh API, transaction hoặc UI đã được triển khai. Phân biệt ghi nhận này với “Quy tắc đích”; mô tả nháp ADR-09/BR-10 nằm trong Business Rules.

# Nguồn chuẩn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
