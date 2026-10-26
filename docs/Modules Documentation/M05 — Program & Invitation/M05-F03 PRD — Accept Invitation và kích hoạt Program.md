# M05-F03 · Accept Invitation và kích hoạt Program

## PRD chức năng · M05 Program & Invitation · Draft · Race-sensitive

# 1\. Mục tiêu

Customer đích chấp nhận offer còn hạn; hệ thống tạo membership và chuyển Program sang Active đúng một lần.

# 2\. Quy tắc

* Chỉ Customer đúng target được accept khi server time `< expires_at`; tại/qua `expires_at` không thể accept kể cả worker expire chưa chạy. Invitation terminal không thể accept.
* Máy chủ kiểm lại Customer active, Tutor eligibility, owner/target, Program/Invitation state, Invitation.program\_version = Program.version, snapshot và expiry trong transaction. Mismatch hoặc stale state không tạo member/Program active.
* Nếu source Listing đang có moderation hold, accept trả 409 và không đổi Invitation/snapshot. Pending Invitation giữ hạn gốc; sau khi hold được gỡ, accept chỉ hợp lệ trước expiry và khi Customer/Tutor còn đủ điều kiện; không yêu cầu source Listing active lại vì offer snapshot đã khóa theo BR-18.
* Invitation → accepted, Program → active và ProgramMember customer được tạo trong cùng transaction.  
* Idempotent retry cùng request/key trả kết quả đã commit; không tạo member/active đôi.  
* Sử dụng lock order thống nhất theo ADR-09: Customer User → Tutor Profile → Program → Invitation; side effect phát sau commit.

# 3\. Tiêu chí chấp nhận

* Đúng Customer accept khi còn hạn tạo đúng một membership và Program Active.  
* Sai Customer, outsider, Tutor, expired, rejected/cancelled hoặc stale state bị từ chối.  
* Accept cạnh tranh với accept khác, reject/cancel, expiry, Tutor/Customer suspend hoặc Listing moderation chỉ có một transaction thắng; status Invitation, membership và Program nhất quán.
* Notification chỉ phát sau commit; rollback/409 không tạo membership, Program Active hoặc notification giả.

# 4\. Chênh lệch/câu hỏi mở

* Schema legacy dùng Program pending/Invitation active; target là Program draft/active và Invitation accepted. Mapping/backfill cần được review trước khi viết API.
* ADR-09 thứ tự khóa hiện là draft, chưa chứng minh được tương tác với suspend, expiry/cancel và Listing hold. Trước triển khai cần thống nhất protocol khóa/isolation dùng chung, rồi kiểm accept-vs-accept/cancel/expiry/suspend/moderation trên PostgreSQL; không coi việc có transaction hoặc lock order hiện ghi là bằng chứng đủ.
* Response khi cùng idempotency key nhưng payload khác và cách client refetch capability sau 409 cần chốt trong API contract.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)  
Đồng bộ 18/10/2026 — ADR-09 còn là bản nháp kỹ thuật: cần kiểm chứng accept cạnh tranh đình chỉ riêng tài khoản Tutor (users.status), không chỉ đình chỉ Tutor Profile. Phải xác định giao thức khóa/isolation dùng chung và có test PostgreSQL trước phát hành; không coi thứ tự Customer User → Tutor Profile → Program → Invitation là bằng chứng đã bảo vệ mọi eligibility race.  
t
