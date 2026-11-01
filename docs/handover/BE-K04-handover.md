# BE-K04 — Sessions planned/actual và replay

**Owner:** Khanh · **Reviewer:** Hoàng  
**Branch:** `be-k04` · **Ngày bàn giao:** 01/11/2026 (Asia/Ho_Chi_Minh)  
**Trạng thái:** K04.1–K04.7 đã được triển khai/đối chiếu theo evidence local; sẵn sàng review. Chưa xác nhận issue đã cập nhật, PR đã review/merge, hoặc triển khai staging/production.

## Phạm vi đã bàn giao

Session API dùng `program_sessions.id` làm ID ổn định. Sáu route đã đăng ký và xuất hiện trong OpenAPI:

| Method | Route | Hành vi |
|---|---|---|
| GET | `/api/v1/programs/{program}/sessions` | Danh sách theo Program, phân trang |
| POST | `/api/v1/programs/{program}/sessions` | Tạo lịch `scheduled`, chưa tạo actual |
| GET | `/api/v1/sessions/{session}` | Chi tiết theo planned ID |
| PATCH | `/api/v1/sessions/{session}` | Sửa lịch `scheduled` khi Program Active |
| POST | `/api/v1/sessions/{session}/complete` | Complete và tạo một actual nguyên tử |
| POST | `/api/v1/sessions/{session}/cancel` | Cancel, giữ lịch sử, không tạo actual |

Tutor owner active được mutation; owner/member được đọc trong scope; outsider không đọc được Session. Paused chặn create/edit nhưng cho complete/cancel lịch scheduled. Program terminal chặn mutation mới. Replay tương đương chỉ được trả sau khi kiểm identity/scope/quyền hiện tại.

Bốn mutation yêu cầu UUID `Idempotency-Key`; edit/complete/cancel yêu cầu JSON integer `expected_version`. Stale hoặc payload/action không tương đương trả 409. Create bắt đầu version 1; mutation thực sự thành công tăng version đúng một lần; PATCH không đổi nội dung không tăng version/audit. Complete tạo tối đa một `lesson_sessions` trong cùng transaction với planned status/version, audit, replay metadata và receipt. Cancel không tạo actual. Replay không lặp actual/audit/event/version; có thể ghi receipt mới cho key mới. Không tự complete theo giờ.

Receipt được giữ 30 ngày. `program_sessions.terminal_replay` lưu metadata bền vững để nhận diện complete/cancel tương đương sau TTL hoặc với key mới. Nếu terminal record cũ không có metadata thì không suy ra replay chỉ từ status và không tự backfill payload lịch sử. Session không phát notification/outbox theo contract K04.1.

Timestamp API là UTC/RFC 3339, lưu/serialize tới microseconds; timezone Program là nguồn hiển thị và được snapshot trên lịch. `meeting_url` tùy chọn, chỉ HTTPS, tối đa 2.048 ký tự và không chứa credentials. Không bổ sung attendance, video integration hoặc Session attachment.

## API và schema delta

- `2026_11_01_014629_add_version_and_terminal_replay_to_program_sessions`: thêm `version` unsigned integer default 1, PostgreSQL CHECK `version >= 1`, `terminal_replay` JSONB nullable; đổi default status planned thành `scheduled`.
- `2026_11_01_023131_preserve_program_session_timestamp_precision`: planned start/end và created/updated timestamps của `program_sessions` dùng precision 6 để không mất microseconds.
- FK và UNIQUE trên `lesson_sessions.program_session_id` đã có trước K04; K04 không thêm constraint trùng.
- Hai migration có `down()` cố ý yêu cầu reverse migration được review vì xóa metadata hoặc giảm precision làm mất dữ liệu.
- OpenAPI resource chỉ lộ allow-list DTO; `terminal_replay` và legacy join timestamps không được lộ.
- Audit: `session.create`, `session.update`, `session.complete`, `session.cancel`. Session notification/event không được thêm theo contract đã chốt.

## Evidence

OpenAPI mới có 89 operations. Scramble export thành công, parity với Laravel routes đạt, contract check xác nhận sáu Session operation có success/error responses và header bắt buộc trên mutation. [OpenAPI hiện hành](../api/openapi.json) được sinh từ backend, không sửa tay.

| Kiểm tra | Kết quả | Log |
|---|---|---|
| OpenAPI export | Exit 0; Scramble migrations và export thành công | [Export](BE-K04-K04.7-openapi-export.txt), [exit](BE-K04-K04.7-openapi-export-exit.txt) |
| Route parity | 89 operations, passed, exit 0 | [Parity](BE-K04-K04.7-route-parity.txt), [exit](BE-K04-K04.7-route-parity-exit.txt) |
| Contract check K04.7 | 6 route pass; DTO không lộ metadata/legacy join fields | [Contract check](BE-K04-K04.7-contract-check.txt) |
| Redocly CLI 2.60.0 | Valid, exit 0, 59 warnings toàn artifact | [Lint](BE-K04-K04.7-openapi-lint.txt), [exit](BE-K04-K04.7-openapi-lint-exit.txt) |
| Docker PostgreSQL app DB `tutorlink` | Cả 32 migration `Ran`; hai migration K04 ở batch 10/11 | [Status](BE-K04-K04.7-migrations-docker.txt), [exit](BE-K04-K04.7-migrations-docker-exit.txt) |
| Windows PostgreSQL `127.0.0.1:5432/tutorlink` | Cả hai migration K04 `Ran` (batch 2/3) | [Status](BE-K04-K04.7-migrations-windows.txt), [exit](BE-K04-K04.7-migrations-windows-exit.txt) |
| K04.6 test nhóm PostgreSQL | 109 passed, 913 assertions, exit 0; có 17 race cases | [Báo cáo và log](BE-K04-K04.6-test-report.md) |
| Full backend | 625 passed, 4.516 assertions, exit 0 | [Báo cáo và log](BE-K04-K04.6-test-report.md) |

Các test race dùng PostgreSQL và process/connection độc lập, kiểm tra double complete, complete × cancel, Session mutation × Program terminal và stale edit; test rollback xác nhận không lưu một phần khi phát sinh lỗi query ở các bước mutation.

## Tài liệu đã đồng bộ

- [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md): sáu route Session đều `Đã đăng ký` theo OpenAPI.
- [Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md): precision, version/check, metadata replay và runtime relationship đã được ghi rõ.
- [M06 PRD Index](../Modules%20Documentation/M06%20%E2%80%94%20Session%20%26%20Workspace/M06%20%E2%80%94%20Session%20%26%20Workspace%20%E2%80%94%20PRD%20Index.md), [F02](../Modules%20Documentation/M06%20%E2%80%94%20Session%20%26%20Workspace/M06-F02%20PRD%20%E2%80%94%20L%E1%BA%ADp%20v%C3%A0%20qu%E1%BA%A3n%20l%C3%BD%20Session.md), [F03](../Modules%20Documentation/M06%20%E2%80%94%20Session%20%26%20Workspace/M06-F03%20PRD%20%E2%80%94%20Ho%C3%A0n%20t%E1%BA%A5t%20ho%E1%BA%B7c%20h%E1%BB%A7y%20Session.md): tách runtime K04 khỏi phạm vi UI/production chưa nghiệm thu.
- [API README](../api/README.md) và [docs README](../README.md): cập nhật OpenAPI 89 operations và liên kết handover.
- [K04.1 contract](BE-K04-K04.1-contract.md) và [K04.6 test report](BE-K04-K04.6-test-report.md) là tài liệu chi tiết về quyết định và kiểm thử.

## Known limitations và blocker

- Redocly lint hợp lệ nhưng còn 59 warnings trên OpenAPI toàn dự án, chủ yếu thiếu summary/metadata hoặc 4xx mô tả ở các endpoint khác. Cảnh báo K04 `JR001` đã hết sau khi bổ sung kiểu model cho `SessionResource`.
- Schema OpenAPI của các planned timestamp vẫn thể hiện nullable string do helper resource dùng chung cho thời điểm null; API runtime của planned fields luôn có timestamp. Đây là giới hạn suy luận schema hiện tại, không phải thay đổi dữ liệu runtime.
- Race harness kiểm tra action/database với thứ tự cạnh tranh được điều phối; không phải load test, không chứng minh mọi interleaving hoặc mọi deadlock dưới tải.
- Rollback kiểm qua query fault injection; chưa diễn tập mất điện/process kill hoặc lỗi hạ tầng production.
- Migration status chỉ áp dụng hai database local được nêu trong evidence; không suy ra staging/production đã migrate.
- Test/API evidence không chứng nhận trình duyệt, UI Workspace, notification delivery, triển khai production hay các feature Workspace ngoài Session.
- Chưa ghi nhận blocker còn lại cho review K04.7. Reviewer cần kiểm tra hai migration có reverse thủ công, metadata terminal legacy null, cảnh báo lint và các giới hạn nêu trên.

## Reviewer checklist

- [ ] Đọc contract K04.1 và rà policy/validation, transition/replay trong code.
- [ ] Xác nhận migrations, OpenAPI delta và sáu trạng thái Catalog.
- [ ] Đọc log race PostgreSQL, full backend và giới hạn trong K04.6.
- [ ] Xác nhận không thêm attendance, video integration hoặc Session attachment.
- [ ] Ghi kết quả review và blocker/việc tiếp theo lên issue BE-K04.
