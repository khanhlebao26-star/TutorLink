# M09-F01 · Tạo Review sau Program

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Customer phản hồi về dịch vụ sau khi Program hoàn thành để hỗ trợ người dùng đánh giá chất lượng thật.

# 2\. Eligibility và dữ liệu

* Chỉ Customer member của Program completed tạo Review; Program cancelled, active/paused hoặc outsider không đủ điều kiện.
* Mỗi Program/reviewer chỉ có một Review; rating từ 1 đến 5; comment tùy chọn, nếu có phải không trắng và tối đa 2.000 ký tự. Unique `(program_id,customer_id)` chặn gửi đồng thời trùng.
* Review thuộc Program và giữ actor, thời điểm, nội dung đã gửi; API không cho sửa/xóa nếu Product chưa định nghĩa.
* Program completed không tự thu hồi membership, để Customer có thể đọc và tạo Review.
* MVP không hỗ trợ sửa hoặc rút Review sau khi tạo. Customer chỉ xem Review của mình nếu bị ẩn, kèm trạng thái hidden; không hiện lý do moderation.

# 3\. Tiêu chí chấp nhận

* Customer đủ điều kiện tạo một Review; duplicate submit/race không tạo record thứ hai.
* Customer của Program chưa complete hoặc cancelled bị từ chối.
* Rating ngoài 1–5 và body sai validation trả lỗi rõ ràng.
* Review không lộ thông tin riêng tư của reporter hoặc hồ sơ xác minh.

# 4\. Chênh lệch/câu hỏi mở

* Giới hạn và policy không sửa/rút Review đã chốt ngày 21/10/2026; Review API chưa có trong repo.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
