# M07-F04 · Ghi và xem Progress

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Tutor ghi nhận tiến độ học theo thời gian; Tutor owner quản lý record và các Program members được đọc.

# 2\. Dữ liệu và quyền

* Progress lưu metric, value, note và thời điểm; không thêm ProgressStatus enum.  
* Tutor owner tạo/sửa record trong Program; Tutor và Customer member đọc; outsider bị từ chối.  
* Active/Paused cho phép ghi Progress theo BR-10; Completed/Cancelled readonly.  
* MVP không có chart nâng cao hoặc grading engine.

# 3\. Tiêu chí chấp nhận

* Progress record gắn đúng Program và actor; response/API giữ đủ metric/value/note/time.  
* Member xem được dữ liệu đúng Program nhưng không sửa thay Tutor.  
* Paused không chặn cập nhật Progress được phép; terminal Program từ chối mutation.  
* Overview latest\_progress phản ánh record mới nhất mà không làm hỏng các tab khác.

# 4\. Chênh lệch/câu hỏi mở

* Chốt kiểu dữ liệu/validation từng metric, timezone hiển thị và sửa/xóa record cũ.  
* Schema progress\_records có; endpoint và UI chưa có.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
