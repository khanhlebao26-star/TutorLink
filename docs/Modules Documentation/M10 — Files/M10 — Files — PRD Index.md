# M10 — Files

## PRD Index · 5 chức năng · Draft

# Mục tiêu

Quản lý vòng đời File từ upload, complete, resource attachment, authorization download đến xóa File chưa liên kết.

# PRD chức năng

[M10-F01 PRD — Upload File](<M10-F01 PRD — Upload File.md>)  
[M10-F02 PRD — Complete và xác thực File](<M10-F02 PRD — Complete và xác thực File.md>)  
[M10-F03 PRD — Gắn File vào resource](<M10-F03 PRD — Gắn File vào resource.md>)  
[M10-F04 PRD — Xem metadata và tải File](<M10-F04 PRD — Xem metadata và tải File.md>)  
[M10-F05 PRD — Xóa File chưa liên kết](<M10-F05 PRD — Xóa File chưa liên kết.md>)

# Quy tắc đích

* Owner, purpose, visibility, completeness và resource permission phải được kiểm tra tại server.  
* Verification private; avatar chỉ public nếu policy cho phép; chat/task/submission/announcement theo membership/resource scope.  
* Không xóa File làm hỏng FK hoặc historical link; outsider download denied.  
* \`clean\` hiện không đồng nghĩa malware scan.

# Implementation gaps

* Upload/complete/metadata/download/delete API đã có; link controller hiện chỉ hỗ trợ verification\_document.  
* Upload purpose whitelist chưa khớp Enum Catalog đầy đủ; resource membership download policy chưa hiện thực.  
* BR-20/BR-21 đã chốt MIME/size theo purpose, scanner/quarantine, orphan cleanup và compensation. Runtime cần triển khai; retention linked files tiếp tục theo policy resource.

# Nguồn

Product/Domain docs hoàn tất, Security Design và M04/M07/M08/M02 policies; nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
