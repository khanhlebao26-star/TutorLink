# K03.7b — Kiểm thử giao diện và API giới hạn 5 MB

- Ngày: 30/10/2026, Asia/Saigon.
- Môi trường: Chrome headless thật; frontend http://localhost:3000, backend http://localhost:8000; Sanctum session + CSRF; email xác minh qua Mailpit local.
- Kết quả cuối: **PASS — 68 kiểm tra đạt, 0 lỗi**, gồm **8 ca giao diện và 16 ca API**. Con số 68 gồm kiểm tra thiết lập phiên và cleanup, không phải kết quả PHPUnit/Pest.
- Chi tiết thời gian từng lượt và HTTP: [results.json](results.json).
- Source đã kiểm: [source-fingerprints.json](source-fingerprints.json).

## Kết quả

| Dung lượng | Giao diện avatar và tài liệu xác minh | API trực tiếp, cả 4 purpose |
|---|---|---|
| 4.999.999 byte | Upload, complete và gắn hồ sơ thành công | Upload 201, complete 200, download 200 và SHA-256 khớp |
| 5.000.000 byte | Upload, complete và gắn hồ sơ thành công | Upload 201, complete 200, download 200 và SHA-256 khớp |
| 5.000.001 byte | Chặn trước khi gửi POST /files | 422, có errors.file |
| 0 byte | Chặn trước khi gửi POST /files | 422, có errors.file |

API kiểm avatar, verification_document, task_attachment và profile_document. Kiểm task_attachment/profile_document chỉ chứng minh File upload/complete/download của owner; không chứng nhận workflow Task/Submission, CV approval hoặc phân quyền resource đã triển khai.

Tất cả file hợp lệ mới có complete=true, antivirus_scanned=false và scanned_at=null sau complete. Các card upload không hiển thị nhãn đã antivirus-scan. Bằng chứng này không có nghĩa file đã được quét malware.

## Ảnh chụp của lượt cuối

- [Avatar 4.999.999 byte](ui-avatar-4999999.png)
- [Avatar 5.000.000 byte](ui-avatar-5000000.png)
- [Avatar 5.000.001 byte bị chặn](ui-avatar-5000001.png)
- [Avatar rỗng bị chặn](ui-avatar-0.png)
- [Tài liệu 4.999.999 byte](ui-verification_document-4999999.png)
- [Tài liệu 5.000.000 byte](ui-verification_document-5000000.png)
- [Tài liệu 5.000.001 byte bị chặn](ui-verification_document-5000001.png)
- [Tài liệu rỗng bị chặn](ui-verification_document-0.png)

## Phát hiện và thao tác môi trường

Lượt đầu quan sát localhost:3000 vẫn hiển thị/chặn ở 2 MiB, trong khi source được mount đã là 5 MB. Đã đối chiếu mount đúng workspace và backend config max_bytes=5000000, rồi restart service frontend. Lượt cuối phục vụ đúng code và nhãn 5 MB; không sửa application source trong quá trình kiểm thử.

Các lỗi thiết lập harness ban đầu (profile tạo mới trả 201, selector document_type và header của request download Sanctum) đã được điều chỉnh trong script test. Kết quả PASS ở results.json thuộc lượt cuối hoàn chỉnh; failure.png là ảnh chẩn đoán lượt chuẩn bị trước, không phải kết quả lượt cuối.

## Dữ liệu test và giới hạn

- Dùng tài khoản Tutor giả lập riêng; lượt cuối User ID 67. Không dùng tài khoản cá nhân có sẵn. Không lưu password, cookie hoặc URL xác minh trong evidence.
- Các tài khoản fixture 63–67 được tạo trong quá trình chuẩn bị/chạy. Hồ sơ và file upload UI còn trong database/storage local để giữ liên kết; một số file của lượt chuẩn bị cũng còn. Không xóa dữ liệu hoặc lịch sử của người dùng có sẵn.
- 8 file API không liên kết của lượt cuối đã được xóa qua API owner sau khi kiểm tra download; ID cụ thể ở cleaned_file_ids trong results.json. Session của lượt cuối đã logout.
- Dùng PNG/PDF fixture tổng hợp để kiểm byte boundary và lifecycle, không kiểm chứng đầy đủ cấu trúc tài liệu hoặc antivirus.
- Chưa chạy lại full backend, lint frontend, OpenAPI hoặc cập nhật handover/roadmap K03.7b trong lượt test giao diện này. Không dùng báo cáo này để đánh dấu toàn BE-K03 hoàn tất.

Script chạy lại trên máy hiện tại: `node outputs/be-k03/k03.7b-browser-tests/run.mjs`. Mỗi lần chạy tạo một tài khoản test riêng và thêm linked UI fixtures; script dùng Playwright bundled và Chrome đã cài, không dùng profile trình duyệt cá nhân.
