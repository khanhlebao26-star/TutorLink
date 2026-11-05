# Nội dung tạo PR BE-K06

Tiêu đề: **BE-K06: kiểm thử tích hợp backend trên PostgreSQL**.

Base: `main`. Compare: `be-k06`. Reviewer đề nghị: `Hoang271205`.

Trạng thái: chưa tạo PR. Khanh tự commit/push cập nhật tài liệu rồi tạo tại [GitHub compare](https://github.com/khanhlebao26-star/TutorLink/compare/main...be-k06?expand=1). Copy phần bên dưới vào mô tả PR.

---

Backend lane Khanh có kết quả regression/integration trên PostgreSQL, bổ sung test nhánh lỗi Session và smoke HTTP/notification/Slack thật với dữ liệu demo riêng.

- Thêm 5 case Session: version tối đa khi edit/complete/cancel, no-op tại giới hạn và actual conflict; kiểm tra không ghi dữ liệu một phần.
- Toàn suite: **827 tests / 7.227 assertions, 0 failures/errors/skips**. Focused Session: 44 tests / 317 assertions.
- Coverage toàn backend **93,58% (8.317/8.888)**; nhóm Session **88,94% → 90,50%**. Source include giữ nguyên.
- Live smoke: 25 request checks + 14 assertions; cookie/CSRF thật, chat replay không trùng, Session permissions/version/replay, Program pause → regular outbox-worker → một notification.
- Slack thật qua job ứng dụng, database queue smoke riêng và native Laravel worker; HTTP 200/ok=true, có permalink. Fake tests và live receipt được ghi riêng.
- Không đổi API/schema hoặc source nghiệp vụ.

## Phiên bản và evidence

- Commit code/test đã kiểm: `2ba90470e47629f218da85b99ef59b7361ee8a55`.
- Log/JUnit/Clover cuối: `55d08b0897eafd49c158f802dd4635417aab9499`.
- Coverage text: `b9e73e323762fa44d44610651b1037917d0ab4af`.
- Báo cáo: [BE-K06-test-report.md](https://github.com/khanhlebao26-star/TutorLink/blob/be-k06/docs/handover/BE-K06-test-report.md).
- Evidence: [20261104-151838](https://github.com/khanhlebao26-star/TutorLink/tree/be-k06/docs/handover/evidence/be-k06/20261104-151838).
- [Tin Slack thật](https://laraveltelesc-cjr9660.slack.com/archives/C0C8BM660TB/p1793780886888319).

## Review và bàn giao

Đề nghị Hoàng (`Hoang271205`) review tính đúng của 5 case mới, evidence race/retry/error và giới hạn smoke. Bộ bàn giao gồm endpoint/payload/errors, event contract, hướng dẫn fixture/smoke và known limitations trong báo cáo; source/test đã kiểm không thay đổi.

Giới hạn: fixtures đặt sẵn verified/approved/active; không thay thế registration/approval smoke. Slack live dùng queue riêng, chưa chứng nhận regular queue `slack` cho tin thử này hoặc người dùng đã đọc trong UI. Không chứng nhận production/load/UI/WebSocket. Chưa có xác nhận review hoặc BE-H06 tiếp nhận; chưa kết luận toàn issue hoàn tất.

Refs #85. Không tự đóng issue trước khi chốt review và bàn giao.
