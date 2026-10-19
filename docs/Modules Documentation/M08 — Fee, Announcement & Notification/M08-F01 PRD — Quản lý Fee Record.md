# M08-F01 · Quản lý Fee Record

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Tutor ghi nhận khoản phí đã thỏa thuận trong Program theo tháng; sản phẩm không xử lý thanh toán.

# 2\. Quy tắc nghiệp vụ

* Tối đa một Fee Record mỗi Program/tháng YYYY-MM; amount là số nguyên VND không âm.  
* Tutor owner nhập tay. Không tính từ Session, không gọi cổng thanh toán và không tuyên bố đã thanh toán.  
* UI/response luôn ghi rõ “Do Tutor khai báo, không phải bằng chứng thanh toán”.  
* FeeRecordStatus: declared → voided, không mở lại. TutorPaymentMark độc lập: unmarked → marked\_paid/marked\_unpaid; paid ↔ unpaid cần audit và reason khi đổi paid sang unpaid.  
* Void giữ nguyên payment mark, giữ kỳ tháng và record để lịch sử không bị ghi đè.  
* Program Paused cấm tạo Fee Record mới nhưng cho xử lý record có sẵn theo policy; terminal readonly.

# 3\. Tiêu chí chấp nhận

* Owner tạo đúng một record cho kỳ tháng; duplicate request trả conflict hoặc replay theo idempotency contract.  
* Amount âm, float, currency khác VND hoặc kỳ sai format bị từ chối.  
* Mark paid/unpaid và void tuân state machine; voided không sửa hoặc chiếm kỳ mới.  
* Customer member xem đúng phạm vi và disclaimer; outsider bị từ chối.

# 4\. Chênh lệch/câu hỏi mở

* Schema cũ dùng declared/confirmed/cancelled; target tách FeeRecordStatus và TutorPaymentMark. Mapping migration/API phải được duyệt.  
* Chốt ngày/tháng theo timezone, edit policy cho record declared và action khi đổi marked\_paid → marked\_unpaid.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edi  
Đồng bộ 18/10/2026 — Chống stale update: PATCH Fee hiện chỉ có amount\_minor/reason, chưa có giao thức version/ETag được chốt. Row lock và kiểm status không tự chứng minh không ghi đè thay đổi từ tab cũ. Theo D22, phần sửa phí bị chặn phát hành cho đến khi owner kỹ thuật xác nhận cơ chế và test PostgreSQL; lượt đồng bộ này không thêm cột, field, endpoint hoặc hạn chế nghiệp vụ mới.  
t
