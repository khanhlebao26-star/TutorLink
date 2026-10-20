# M09 — Review, Report & Audit

## PRD Index · 5 chức năng · Draft

# Mục tiêu

Tạo Review đủ điều kiện sau Program Completed, hiển thị Review tin cậy, tiếp nhận Report, xử lý moderation theo permission và lưu audit bất biến.

# PRD chức năng

[M09-F01 PRD — Tạo Review sau Program](https://docs.google.com/document/d/1Scoyodvny48odQj3ofgsrk0Oa7ok4G6iSr5lmhzS78E/edit)  
[M09-F02 PRD — Hiển thị và kiểm duyệt Review](https://docs.google.com/document/d/1zGbxUtlkQNO0vcOZytDXUvs-ActcxsPKr5UFikmcEB4/edit)  
[M09-F03 PRD — Gửi Report](https://docs.google.com/document/d/1s1mglr_5mrI4gTj9vafrtZNkfXPeRbrvSGVczozwey4/edit)  
[M09-F04 PRD — Admin xử lý Report và evidence](https://docs.google.com/document/d/1y8To6rzVW2iF8JMyPVHDdcEhovRVxFOibVlWtqcxoPo/edit)  
[M09-F05 PRD — Ghi và tra cứu Audit](https://docs.google.com/document/d/1sr_FGZNzMeF-ogrA9Be0Dnse_NjQoOcCHWhTt6A6W-U/edit)

# Quy tắc đích

* Review một lần bởi Customer member sau Completed; rating 1–5; chỉ Review visible hợp lệ tham gia public aggregate.  
* ReportStatus open/resolved/dismissed; ResourceType tutor\_profile/listing/message/program/review; ReasonCode spam/harassment/inappropriate\_content/fraud\_suspected/misleading\_information/privacy\_violation/other; report không tự động hide resource.  
* Admin view/decide/review moderation/audit phải có permission cụ thể và scope; evidence tối thiểu.  
* Audit cùng transaction, append-only; mutation rollback nếu audit write thất bại.

# Implementation gaps

* Admin approval audit có; Review/Report APIs và scoped Admin evidence workflow chưa có.  
* Schema legacy Report pending/reviewing cần map về open/resolved/dismissed.  
* message đã là resource có thể report; conversation không phải ReportResourceType. BR-11 đã chốt quyền đọc evidence chat khi Report open và cửa sổ tối đa 10 tin trước/5 tin sau; field masking và thời hạn lưu dữ liệu vẫn cần chốt theo M09-F04/M10.

# Nguồn

Product/Domain docs hoàn tất và WF-06; nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
