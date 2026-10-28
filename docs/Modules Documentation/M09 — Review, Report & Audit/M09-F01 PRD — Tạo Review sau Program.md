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

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
