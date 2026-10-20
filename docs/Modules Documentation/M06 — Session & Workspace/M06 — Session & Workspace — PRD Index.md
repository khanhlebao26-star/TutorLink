# M06 — Session & Workspace

## PRD Index · 3 chức năng · Draft

# Mục tiêu

Tổ chức Workspace của Program và điều phối Session, với quyền đọc/ghi theo owner/member và lifecycle Program.

# PRD chức năng

[M06-F01 PRD — Workspace Overview và capability](https://docs.google.com/document/d/1tuaX_rejlPeYChOhhdfUDPmGIRwF5k-Y1PlMr1BGNL0/edit)  
[M06-F02 PRD — Lập và quản lý Session](https://docs.google.com/document/d/1jL7DG9Jlpz2eT3zMgMEuLHE4IdtBBUiPCUCrCtYvepM/edit)  
[M06-F03 PRD — Hoàn tất hoặc hủy Session](https://docs.google.com/document/d/1Ui26-CxN6yjeBKtRguDx1eTpg7KIOnvABylsBEdqCOY/edit)

# Quy tắc đích

* Workspace có sáu tab; Overview là read model gọn, không N+1.  
* SessionStatus scheduled/completed/cancelled; hủy giữ lịch sử; UTC storage/API, hiển thị theo timezone.  
* Program Paused chặn tạo Session mới; terminal readonly; owner quản lý, member đọc.  
* Không tính planned Session như delivered lesson; không thêm attendance/session attachment/video MVP.

# Implementation gaps

* Migration program\_sessions/lesson\_sessions có; Program/Workspace/Session API và UI chưa có.  
* ID mapping và transition đã chốt ở M06-F02/F03: API dùng program\_sessions.id; hoàn tất tạo actual 0..1 nguyên tử, hủy không tạo actual. Khoảng trống còn lại là triển khai và bằng chứng kiểm thử, không phải chọn lại mô hình.

F19 — Bằng chứng current-state (18/10/2026): nội dung mục này là baseline của lượt rà soát trước, chưa được kiểm tra lại repository/commit trong lượt Drive-only này. Owner xác minh: chưa gán; ngày/commit kiểm chứng: chưa có; trạng thái: chưa xác minh. Migration tồn tại không tự chứng minh API, transaction hoặc UI đã được triển khai. Phân biệt ghi nhận này với “Quy tắc đích”; mô tả nháp ADR-09/BR-10 nằm trong Business Rules.

# Nguồn

Product & Domain docs hoàn tất; xem danh sách nguồn trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
