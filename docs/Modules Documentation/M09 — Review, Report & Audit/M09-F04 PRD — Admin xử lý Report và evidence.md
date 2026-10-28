# M09-F04 · Admin xử lý Report và evidence

## PRD chức năng · M09 Review, Report & Audit · Draft · Permission-scoped

# 1\. Mục tiêu

Admin có quyền được phân công triage Report, xem evidence tối thiểu và resolve hoặc dismiss kèm lý do.

# 2\. Quy tắc

* Permission `admin.reports.view` cùng assignment hiện tại giới hạn list/detail/evidence theo scope; `admin.reports.decide` cho phép resolve/dismiss. Evidence chỉ đọc khi Report còn `open`.
* Danh sách chỉ trả summary; evidence chỉ qua route `/admin/reports/{report}/evidence` có kiểm tra permission, assignment, status và resource.
* Target ReportStatus: open → resolved/dismissed; trạng thái terminal theo workflow, không xóa Report.  
* Decision lưu resolution\_note, actor, thời điểm, before/after/reason cùng Audit transaction.  
* Report về message chỉ trả message bị báo cáo và tối đa 10 message trước/5 message sau cùng Conversation theo BR-11. Với target khác, evidence là allow-list theo loại resource: Listing/Profile chỉ field công khai và lịch sử moderation liên quan; Review chỉ nội dung Review và quyết định moderation liên quan; Program chỉ metadata cần xác minh Report. Không trả email, điện thoại, giấy tờ xác minh, object key, file không liên quan, private content khác hoặc toàn bộ resource.
* Nếu evidence gốc đã bị xóa hoặc không còn được phép đọc, trả `evidence_unavailable` cùng metadata tối thiểu; không xóa Report hay quyết định. Evidence được đọc trực tiếp từ resource có kiểm quyền hiện tại, không sao chép thành kho riêng.
* Evidence chỉ đọc khi Report `open`. Khi Report resolved/dismissed, mọi evidence endpoint bị từ chối; Admin được phân công vẫn có thể xem summary, resolution note và audit theo quyền tương ứng. Audit reader chỉ thấy metadata allow-list, không được kế thừa quyền xem evidence.
* Report và quyết định được giữ 24 tháng từ lúc đóng; audit truy cập evidence/decision được giữ 24 tháng từ lúc tạo. Dữ liệu nguồn tuân retention riêng của resource. Purge sau hạn phải được kiểm soát và audit.

# 3\. Tiêu chí chấp nhận

* Admin ngoài scope bị từ chối; role Admin đơn thuần không bypass permission.  
* Admin có view scope chỉ thấy summary trong phạm vi được giao; evidence chỉ được trả khi Report `open`. Không quyết định khi thiếu decide permission.
* Resolve/dismiss từ open cập nhật một lần; sai state trả 409\.  
* Audit failure rollback decision; list không nhúng evidence đầy đủ.
* Report đóng hoặc assignment bị chuyển/thu hồi làm quyền đọc evidence chấm dứt từ request tiếp theo; kết luận và audit vẫn xem theo quyền riêng.

# 4\. Chênh lệch/câu hỏi mở

* BR-11 cùng quyết định 21/10/2026 chốt permission, assignment hiện tại, allow-list evidence, thời hạn lưu, quyền xem kết luận và DTO cho evidence ngoài message.
* Enum có open/resolved/dismissed; local legacy notes chứa pending/reviewing. Cần mapping migration trước API.  
* Admin report endpoints chưa có; không xây dashboard BI hoặc report evidence tables riêng khi chưa cần.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)

F03 — Nguồn assignment và giới hạn evidence  
Nguồn current assignment là reports.assigned\_admin\_id; permission để giao là admin.reports.assign, xem/triage cần admin.reports.view, resolve/dismiss cần admin.reports.decide. Assignment không tự cấp quyền đọc toàn bộ Conversation, File hoặc giấy tờ Tutor. Evidence chỉ trả tối thiểu theo allow-list và còn phải qua permission/resource policy của M04/M10. Reassignment/revoke thu hồi quyền cũ ở request kế tiếp; resolve/dismiss giữ handled\_by/handled\_at như lịch sử quyết định. BR-11 chặn đọc evidence sau khi Report đóng; retention Report/audit là 24 tháng theo quyết định 21/10/2026.
`GET /api/v1/admin/users` vẫn là route Dự kiến; permission, exact lookup, DTO tối thiểu và điều kiện Admin active để phân công đã chốt tại BR-27/Catalog. Assignment mutation nhận assigned_admin_id; server luôn kiểm active status và permission xử lý Report.
