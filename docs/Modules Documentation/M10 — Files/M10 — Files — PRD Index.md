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
* Verification private; avatar chỉ public nếu policy cho phép; task/submission/announcement theo resource scope. Theo [K03.1 điều chỉnh 29/10](../../handover/BE-K03-K03.1-contract.md), chat chỉ text, không upload/link message; CV duyệt nội dung theo file/version riêng.
* Không xóa File làm hỏng FK hoặc historical link; outsider download denied.  
* \`clean\` hiện không đồng nghĩa malware scan.

# Implementation gaps

* Upload/complete/metadata/download/delete API đã có; link controller hiện chỉ hỗ trợ verification\_document.  
* Upload purpose whitelist chưa khớp Enum Catalog đầy đủ; resource membership download policy chưa hiện thực.  
* [K03.7a](../../handover/BE-K03-K03.7a-test-report.md) đã gỡ antivirus tự động; clean/complete chỉ kiểm tra kỹ thuật, giữ evidence quét thật cũ. [K03.7b](../../handover/BE-K03-K03.7b-test-report.md) đã kiểm tra kỹ thuật frontend/upload/complete: mọi upload mới tối đa 5.000.000 byte, không rỗng. Task/Submission/CV approval, orphan cleanup và storage compensation cần evidence riêng; retention linked files theo resource.

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
