# M10-F02 · Complete và xác thực File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Chỉ cho phép attach/download File khi upload hoàn tất, object tồn tại và metadata khớp dữ liệu thật trong storage.

# 2\. Quy tắc

* Chỉ owner gọi POST /files/{id}/complete.  
* Máy chủ xác minh object tồn tại, kích thước, MIME và checksum khớp metadata; trạng thái rejected không complete được.  
* FileScanStatus đích pending/clean/rejected. \`clean\` chỉ có nghĩa vượt kiểm tra đang triển khai; không tuyên bố đã malware-scan nếu scanner chưa tích hợp.  
* File chưa complete không được attach hoặc tải như tệp hợp lệ.  
* Validation complete phải idempotent và chịu được request lặp.

# 3\. Tiêu chí chấp nhận

* Owner complete đúng file sau khi đối chiếu storage; trạng thái/complete response cập nhật.  
* Object thiếu hoặc size/MIME/checksum lệch trả lỗi conflict, không chuyển clean.  
* Rejected file không complete; non-owner bị từ chối.  
* Retry complete không tạo link hoặc side effect trùng.

# 4\. Implementation hiện tại và câu hỏi mở

* API complete hiện kiểm tra tồn tại, size, MIME, checksum và scan\_status=rejected; sau đó ghi clean. Chưa có malware scanner toàn diện.  
* Chốt scanner/security scanning thật, quarantine/rejected transition và retention của failed upload.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
