# M07-F04 · Ghi và xem Progress

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Tutor ghi nhận tiến độ học theo thời gian; Tutor owner quản lý record và các Program members được đọc.

# 2\. Dữ liệu và quyền

* Progress MVP chỉ dùng `metric_name=progress_percent`; `value` là số nguyên 0–100 do Tutor tự đánh giá, không suy từ điểm hoặc số Session. `note` tùy chọn; `recorded_at` do server đặt. Không thêm ProgressStatus enum.
* Tutor owner tạo/sửa record trong Program; Tutor và Customer member đọc; outsider bị từ chối. PATCH yêu cầu `expected_version`; sửa ghi audit before/after. Không có DELETE.
* Active/Paused cho phép ghi/sửa Progress theo BR-10/BR-20; Completed/Cancelled readonly.
* Timestamp lưu theo UTC và hiển thị theo `Program.timezone`.
* MVP không có chart nâng cao hoặc grading engine.

# 3\. Tiêu chí chấp nhận

* Progress record gắn đúng Program và actor; response/API giữ đủ metric/value/note/time.  
* Member xem được dữ liệu đúng Program nhưng không sửa thay Tutor.  
* Paused không chặn cập nhật Progress được phép; terminal Program từ chối mutation.  
* Overview latest\_progress phản ánh record mới nhất mà không làm hỏng các tab khác.
* Giá trị ngoài số nguyên 0–100, metric khác `progress_percent`, sửa thiếu/sai `expected_version` hoặc DELETE bị từ chối; sửa thành công tăng version một lần và audit giá trị trước/sau.

# 4\. Chênh lệch/câu hỏi mở

* Kiểu metric, timezone, lịch sử sửa và stale update được chốt tại BR-20. Schema/endpoint/UI chưa có đầy đủ và cần đồng bộ.
* Schema hiện cần chuyển value sang số nguyên 0–100 và thêm version; endpoint và UI chưa có. Runtime/schema chưa đồng bộ BR-20.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
