# PRD File Lifecycle

FR-13, BR-14. POST multipart /files → POST complete → attach đúng owner/purpose → GET download qua backend authorization. Hiện complete chuyển scan_status clean; chưa có malware scanner/validation storage toàn diện.

Trước mở attachment: kiểm object tồn tại, MIME thực/size/extension theo allow-list và giới hạn đã chốt; purpose phù hợp; visibility public chỉ avatar được policy cho phép. Verification private; message/task/submission/announcement theo membership và resource scope. Không Program library/Session attachment.

AC-G1-05 và AC-G2-06: incomplete không attach; wrong owner/purpose/resource denied; public payload không verification/object key; storage missing/type/size mismatch không clean; mọi FK và historical link được bảo vệ trước delete; outsider download denied; admin thiếu download permission denied. Dữ liệu demo giả, không giấy tờ thật.

Response/error phải khớp contract thực; không gọi File clean là “đã scan” trong UI hay handoff.
