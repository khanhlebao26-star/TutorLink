# M10-F02 · Complete và xác thực File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Chỉ cho phép attach/download File khi upload hoàn tất, object tồn tại và metadata khớp dữ liệu thật trong storage.

# 2\. Quy tắc

* Chỉ owner gọi POST /files/{id}/complete.  
* Máy chủ xác minh object tồn tại, kích thước, MIME và checksum khớp metadata; trạng thái rejected không complete được.  
* FileScanStatus `pending/clean/rejected`; `clean` chỉ được ghi sau khi object tồn tại, size/MIME/chữ ký/checksum khớp và malware scanner thực tế xác nhận clean. File pending được quarantine, chưa attach/download; scanner lỗi giữ pending, malware hoặc vi phạm policy chuyển rejected. Không tuyên bố đã scan nếu scanner chưa được tích hợp.
* File chưa complete không được attach hoặc tải như tệp hợp lệ.  
* Validation complete phải idempotent và chịu được request lặp.

# 3\. Tiêu chí chấp nhận

* Owner complete đúng file sau khi đối chiếu storage; file chỉ chuyển clean sau khi scanner trả kết quả clean.
* Object thiếu, size/MIME/chữ ký/checksum lệch hoặc scanner chưa trả kết quả clean thì không chuyển clean.
* Rejected file không complete; non-owner bị từ chối.
* Retry complete không tạo link hoặc side effect trùng.

# 4\. Implementation hiện tại và câu hỏi mở

* API complete hiện kiểm tra tồn tại, size, MIME, checksum và scan\_status=rejected; sau đó ghi clean. Chưa có malware scanner toàn diện.  
* BR-21 chốt scanner, quarantine và không fail-open. Runtime còn phải triển khai scanner, rejected transition và dùng lịch cleanup theo M10-F05.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
