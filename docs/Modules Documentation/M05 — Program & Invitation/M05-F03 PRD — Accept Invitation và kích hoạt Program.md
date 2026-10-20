# M05-F03 · Accept Invitation và kích hoạt Program

## PRD chức năng · M05 Program & Invitation · Draft · Race-sensitive

# 1\. Mục tiêu

Customer đích chấp nhận offer còn hạn; hệ thống tạo membership và chuyển Program sang Active đúng một lần.

# 2\. Quy tắc

* Chỉ Customer đúng target được accept Invitation pending trước expires\_at; Invitation hết hạn hoặc đã có terminal decision không thể accept.  
* Máy chủ kiểm lại Customer active, Tutor eligibility, owner/target, Program/Invitation state, snapshot version và expiry trong transaction.  
* Invitation → accepted, Program → active và ProgramMember customer được tạo trong cùng transaction.  
* Idempotent retry cùng request/key trả kết quả đã commit; không tạo member/active đôi.  
* Sử dụng lock order thống nhất theo ADR-09: Customer User → Tutor Profile → Program → Invitation; side effect phát sau commit.

# 3\. Tiêu chí chấp nhận

* Đúng Customer accept khi còn hạn tạo đúng một membership và Program Active.  
* Sai Customer, outsider, Tutor, expired, rejected/cancelled hoặc stale state bị từ chối.  
* Hai accept cạnh tranh chỉ một transaction thắng; trạng thái invitation, membership, Program nhất quán.  
* Accept tranh chấp cancel/expiry/suspend không để dữ liệu nửa chừng; notification không phát khi transaction rollback.

# 4\. Chênh lệch/câu hỏi mở

* Schema legacy dùng Program pending/Invitation active; target là Program draft/active và Invitation accepted. Mapping/backfill cần được review trước khi viết API.  
* Chốt response khi cùng key nhưng payload khác và cách client refetch capability sau 409\.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edi  
Đồng bộ 18/10/2026 — ADR-09 còn là bản nháp kỹ thuật: cần kiểm chứng accept cạnh tranh đình chỉ riêng tài khoản Tutor (users.status), không chỉ đình chỉ Tutor Profile. Phải xác định giao thức khóa/isolation dùng chung và có test PostgreSQL trước phát hành; không coi thứ tự Customer User → Tutor Profile → Program → Invitation là bằng chứng đã bảo vệ mọi eligibility race.  
t
