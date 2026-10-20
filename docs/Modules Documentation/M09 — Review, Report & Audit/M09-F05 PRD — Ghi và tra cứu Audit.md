# M09-F05 · Ghi và tra cứu Audit

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Lưu dấu vết bất biến cho các quyết định/moderation quan trọng và cho phép người được cấp quyền đọc trong scope.

# 2\. Audit event

* Record tối thiểu actor, resource, action, old/new state khi phù hợp, reason và timestamp.  
* Audit ghi cùng transaction với mutation; nếu audit write fail thì mutation rollback. Khi mutation sinh notification, outbox row cho recipient/type/resource/event\_key cũng nằm trong transaction đó; worker không tự ghi audit nghiệp vụ và không đọc private content để dựng thông báo.  
* Audit là append-only: API không sửa/xóa sự kiện lịch sử.  
* admin.audit\_logs.view chỉ đọc theo scope; quyền Admin chung không cấp toàn bộ log.  
* Không đưa secret, object key hoặc toàn bộ private content vào audit payload.

# 3\. Tiêu chí chấp nhận

* Report decision, Review moderation và các mutation quan trọng tạo đúng audit record.  
* Transaction rollback không để audit orphan; audit failure không để mutation không có trace.  
* Người không có scope không đọc được audit; actor hợp lệ đọc được trường cần thiết.  
* Read API không sửa lịch sử.

# 4\. Chênh lệch/câu hỏi mở

* Admin approval audit đã có; Report/Review audit API và scoped log reader chưa có.  
* Chốt action catalog, masking, retention, pagination và cross-module audit schema.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit

F03 — Audit assignment  
Mỗi lần giao, chuyển hoặc thu hồi Tutor Profile/Report tạo audit\_logs row append-only trong cùng transaction với cập nhật assignment. Ghi resource\_type/id, actor\_id, action, before/after tối thiểu gồm assigned\_admin\_id và assignment\_version, reason và created\_at; không chép nội dung hồ sơ, tin nhắn hoặc file riêng tư vào audit. Assignment history không được sửa/xóa.
