# K03.14 — Checklist đồng bộ và ranh giới nghiệm thu

Baseline code `8e7cdaf674f7c2a8927a243eac86a5ce1bf0f004`; [baseline-check.json](baseline-check.json) ghi 281 fingerprint K03.13 khớp trước sửa docs. Các checkbox dưới là kiểm tài liệu/evidence, không phải Hoàng đã duyệt.

## Phạm vi và tài liệu

- [x] Ba nhóm scope đã được người dùng đồng ý: backend có evidence; Echo/UI hoãn; Task/Submission/CV/cleanup/production chưa được BE-K03 chứng nhận.
- [x] [Handover](../../BE-K03-K03.14-handover.md): commit, scope, API/schema delta, log được tái sử dụng, limitations và checklist reviewer.
- [x] [Runbook](../../BE-K03-K03.14-runbook.md): environment/session/queue/Reverb, lock dependency, migration guard, service, routes/channels, test/probe và cleanup; không chạy probe lại trong task docs.
- [x] M04-F01/F02/F03/F04/F05/F07: thay trạng thái backend chưa có bằng code/evidence hiện có; UI vẫn hoãn. F06 giữ thiết kế attachment/scanner/5 MiB dưới nhãn lịch sử rõ ràng.
- [x] M04 Index: cập nhật implementation notes và dẫn tổng hợp; không dùng enum legacy để mở attachment.
- [x] Security SP-01/SP-06: session-bound channels, auth và publish quyền hiện tại, logout/suspend/restore, khóa User, stale-cookie CSRF và ranh giới packet đã phát. SP-07 giữ technical-only/5.000.000 byte.
- [x] M10 Index: link chung chỉ verification_document; purpose upload không chứng nhận resource workflow; Task/Submission/CV/cleanup cần evidence riêng.
- [x] BR-21, contract, WF03, Catalog, README và roadmap thống nhất scope; không đổi target của module ngoài scope thành trạng thái đã triển khai.

## API và evidence

- [x] Artifact OpenAPI giữ nguyên byte/SHA-256 với baseline K03.13, không export/sửa JSON thủ công vì runtime/API không đổi.
- [x] Bảy endpoint Conversation và POST /broadcasting/auth được liệt kê; auth route web không tính trong OpenAPI. Catalog vẫn phân biệt registered/planned.
- [x] Parity kiểm lại 70 operations/exit 0; Redocly 2.60.0 hợp lệ/43 warnings/exit 0; log có trong thư mục này.
- [x] 55 tests/528 assertions, 461 tests/3.004 assertions và probe 12/12 dẫn về K03.13; không giả làm test vừa chạy K03.14.
- [x] Backend/runtime files so với baseline không đổi; mọi file đã có của evidence K03.13 giữ nguyên. Doc thay đổi được liệt kê trong documentation-check.json và manifest K03.14 mới.
- [x] Kiểm local links của tài liệu mới/sửa, diff check, SHA-256 manifest mới; không đọc hoặc fingerprint .env/secret.

## Publication còn mở

- [ ] Khanh commit/push K03.14, tạo/cập nhật PR/issue và gắn URL thật.
- [ ] Khanh gửi Hoàng review.
- [ ] Hoàng xác nhận review, feedback được xử lý và team nghiệm thu backend.
- [ ] K03.12 Echo/UI được thực hiện/nghiệm thu trong backlog sau; không tick bằng consumer probe.

Giữ nguyên report/manifest lịch sử K03.13. Nếu sửa runtime sau bước này, phải kiểm lại test/API tương ứng và tạo evidence version mới; kiểm tài liệu không thay thế regression cho thay đổi code.
