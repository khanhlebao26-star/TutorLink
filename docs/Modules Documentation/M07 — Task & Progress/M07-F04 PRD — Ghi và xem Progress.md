# M07-F04 · Ghi và xem Progress

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Tutor ghi nhận tiến độ học theo thời gian; Tutor owner quản lý record và các Program members được đọc.

# 2\. Dữ liệu và quyền

* Progress MVP chỉ dùng `metric_name=progress_percent`; `value` là số nguyên 0–100 do Tutor tự đánh giá, không suy từ điểm hoặc số Session. `note` tùy chọn; `recorded_at` do server đặt. Không thêm ProgressStatus enum.
* Tutor owner tạo/sửa record trong Program; Tutor và Customer member đọc; outsider bị từ chối. PATCH yêu cầu `expected_version`; sửa thực tế ghi audit before/after. PATCH cùng dữ liệu, đúng version trả projection hiện tại, không đổi `updated_at`/version/audit; stale vẫn 409. Không có DELETE.
* Active/Paused cho phép ghi/sửa Progress theo BR-10/BR-20; Completed/Cancelled readonly.
* Timestamp lưu theo UTC và hiển thị theo `Program.timezone`. List chỉ lọc theo Program scope, sort ổn định `(recorded_at DESC NULLS LAST,id DESC)`; `page` mặc định 1, `per_page` mặc định 20/tối đa 50 theo BR-26, response có `data` và `meta`.
* MVP không có chart nâng cao hoặc grading engine.

# 3\. Tiêu chí chấp nhận

* Progress record gắn đúng Program và actor; response/API giữ đủ metric/value/note/time.  
* Member xem được dữ liệu đúng Program nhưng không sửa thay Tutor.  
* Paused không chặn cập nhật Progress được phép; terminal Program từ chối mutation.  
* Overview latest\_progress phản ánh record mới nhất mà không làm hỏng các tab khác.
* Giá trị ngoài số nguyên 0–100, metric khác `progress_percent`, sửa thiếu/sai `expected_version` hoặc DELETE bị từ chối; sửa thành công tăng version một lần và audit giá trị trước/sau.

# 4\. Chênh lệch/câu hỏi mở

* BE-D04 branch (02/11/2026) đã có API/schema Progress và Overview; migration cùng regression đã chạy trên `tutorlink_test`. Đây là bằng chứng của test DB, không xác nhận runtime môi trường thực tế.
* UI Progress vẫn ngoài phạm vi backend D04. Contract cụ thể về request/response, no-op, audit, error và đồng bộ lifecycle nằm tại [BE-D04 contract](../../handover/BE-D04-contract.md) mục 13–16.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
