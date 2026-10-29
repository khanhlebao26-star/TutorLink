# M10-F02 · Complete và xác thực File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Chỉ cho phép attach/download File khi upload hoàn tất, object tồn tại và metadata khớp dữ liệu thật trong storage.

# 2\. Quy tắc

* Chỉ owner gọi POST /files/{id}/complete.  
* Máy chủ xác minh object tồn tại, kích thước, MIME và checksum khớp metadata; trạng thái rejected không complete được.  
* Điều chỉnh 29/10/2026 theo [K03.1](../../handover/BE-K03-K03.1-contract.md): không antivirus tự động. FileScanStatus `pending/clean/rejected`; clean chỉ sau object/size/extension/MIME/chữ ký/checksum và archive policy hợp lệ. Pending chưa attach/download; metadata/archive vi phạm → rejected. Storage hoặc validation tạm lỗi → pending, cho retry. clean/complete không có nghĩa đã antivirus-scan; không tự ghi scan evidence giả.
* File chưa complete không được attach hoặc tải như tệp hợp lệ.  
* Validation complete phải idempotent và chịu được request lặp.

# 3\. Tiêu chí chấp nhận

* Owner active complete đúng file sau kiểm tra kỹ thuật và xác nhận hash object không thay đổi; trả 200/clean mà không tạo evidence antivirus.
* Object không đọc được trả 503 file_storage_unavailable/pending; validation timeout/lỗi môi trường trả 503 file_validation_unavailable/pending, Retry-After: 10. Metadata/archive vi phạm trả 422 file_rejected/rejected; không cấp clean khi kiểm tra chưa hoàn tất.
* Rejected file không complete; non-owner bị từ chối.
* Retry complete không tạo link hoặc side effect trùng.

# 4\. Implementation hiện tại và câu hỏi mở

* [K03.7a](../../handover/BE-K03-K03.7a-test-report.md) đã gỡ ClamAV, giữ ZIP guard có giới hạn; guard không phân tích đệ quy container nhúng và không phải antivirus. Evidence quét thật cũ và schema được giữ; file legacy thiếu evidence không bị gán bằng chứng giả.
* K03.7b còn phải đồng bộ upload/complete về 5.000.000 byte; runtime vẫn avatar 2 MiB/tài liệu 10 MiB. Cleanup/storage compensation theo M10-F05 và CV approval theo phiên bản là công việc riêng, không được chứng nhận bởi complete.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
