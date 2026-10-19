# M10 — Files

## PRD Index · 5 chức năng · Draft

# Mục tiêu

Quản lý vòng đời File từ upload, complete, resource attachment, authorization download đến xóa File chưa liên kết.

# PRD chức năng

[M10-F01 PRD — Upload File](https://docs.google.com/document/d/1kEG6PVvmTOXgfOpZSIDPCZS7zAl1Jvet4UngvURU3Ew/edit)  
[M10-F02 PRD — Complete và xác thực File](https://docs.google.com/document/d/1THxZ7uEwytDs6Af2hUGk9HcVZJqSFVe8fS9IlQ0pC7I/edit)  
[M10-F03 PRD — Gắn File vào resource](https://docs.google.com/document/d/1B7g6pahZQkRk6oWB0F4b0nFuNQooML_XG-gTgmPDInE/edit)  
[M10-F04 PRD — Xem metadata và tải File](https://docs.google.com/document/d/1x7v5cRAeci3LcxkrW_UxSN2ix89UrHybG2PjlM8a0gQ/edit)  
[M10-F05 PRD — Xóa File chưa liên kết](https://docs.google.com/document/d/1vBIwwaAJm5xJl19NGICTn9wutHx_XG4xJz94BaKbyfo/edit)

# Quy tắc đích

* Owner, purpose, visibility, completeness và resource permission phải được kiểm tra tại server.  
* Verification private; avatar chỉ public nếu policy cho phép; chat/task/submission/announcement theo membership/resource scope.  
* Không xóa File làm hỏng FK hoặc historical link; outsider download denied.  
* \`clean\` hiện không đồng nghĩa malware scan.

# Implementation gaps

* Upload/complete/metadata/download/delete API đã có; link controller hiện chỉ hỗ trợ verification\_document.  
* Upload purpose whitelist chưa khớp Enum Catalog đầy đủ; resource membership download policy chưa hiện thực.  
* Cần chốt MIME/size/scan policy và orphan cleanup.

# Nguồn

Product/Domain docs hoàn tất, Security Design và M04/M07/M08/M02 policies; nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
