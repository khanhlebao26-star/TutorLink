# M09-F04 · Admin xử lý Report và evidence

## PRD chức năng · M09 Review, Report & Audit · Draft · Permission-scoped

# 1\. Mục tiêu

Admin có quyền được phân công triage Report, xem evidence tối thiểu và resolve hoặc dismiss kèm lý do.

# 2\. Quy tắc

* Permission `admin.reports.view` cùng assignment hiện tại giới hạn list/detail/evidence theo scope; `admin.reports.decide` cho phép resolve/dismiss. Evidence chỉ đọc khi Report còn `open`.
* Danh sách chỉ trả summary; evidence chỉ qua route `/admin/reports/{report}/evidence` có kiểm tra permission, assignment, status và resource.
* Target ReportStatus: open → resolved/dismissed; trạng thái terminal theo workflow, không xóa Report.  
* Decision lưu resolution\_note, actor, thời điểm, before/after/reason cùng Audit transaction.  
* Report về message chỉ trả message bị báo cáo và tối đa 10 message trước/5 message sau cùng Conversation theo BR-11. Không cấp quyền đọc toàn bộ Conversation/File/Tutor verification; M04/M10 policy vẫn áp dụng.

# 3\. Tiêu chí chấp nhận

* Admin ngoài scope bị từ chối; role Admin đơn thuần không bypass permission.  
* Admin có view scope chỉ thấy summary trong phạm vi được giao; evidence chỉ được trả khi Report `open`. Không quyết định khi thiếu decide permission.
* Resolve/dismiss từ open cập nhật một lần; sai state trả 409\.  
* Audit failure rollback decision; list không nhúng evidence đầy đủ.
* Report đóng hoặc assignment bị chuyển/thu hồi làm quyền đọc evidence chấm dứt từ request tiếp theo; kết luận và audit vẫn xem theo quyền riêng.

# 4\. Chênh lệch/câu hỏi mở

* BR-11 đã chốt permission, assignment hiện tại, cửa sổ message và chặn đọc evidence sau khi đóng Report. Còn chốt field masking, thời hạn lưu dữ liệu, quyền xem kết luận/audit và chi tiết DTO của evidence không phải message.
* Enum có open/resolved/dismissed; local legacy notes chứa pending/reviewing. Cần mapping migration trước API.  
* Admin report endpoints chưa có; không xây dashboard BI hoặc report evidence tables riêng khi chưa cần.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit

F03 — Nguồn assignment và giới hạn evidence  
Nguồn current assignment là reports.assigned\_admin\_id; permission để giao là admin.reports.assign, xem/triage cần admin.reports.view, resolve/dismiss cần admin.reports.decide. Assignment không tự cấp quyền đọc toàn bộ Conversation, File hoặc giấy tờ Tutor. Evidence chỉ trả tối thiểu theo allow-list và còn phải qua permission/resource policy của M04/M10. Reassignment/revoke thu hồi quyền cũ ở request kế tiếp; resolve/dismiss giữ handled\_by/handled\_at như lịch sử quyết định. BR-11 chặn đọc evidence sau khi Report đóng; retention dữ liệu vẫn cần chốt riêng.
Open dependency for assignment UI: `GET /api/v1/admin/users` remains in the target Catalog but is not registered yet. Define its permission, scope and minimal DTO before implementation or UI integration. The assignment mutation still accepts assigned\_admin\_id, and the server must validate active status and Report-processing permissions.
