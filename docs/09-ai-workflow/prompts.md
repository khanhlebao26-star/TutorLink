# Prompt mẫu để giao AI

Thay các placeholder bằng issue/slice thật. Prompt implementation là authorization cho phạm vi điền trong brief, không cho cả project. Startup/review chỉ đọc.

## Startup read only

```text
Bạn làm việc trong TutorLink. Trước khi hành động, đọc docs/README.md,
docs/08-project/current-status.md, docs/01-product/product-scope.md,
docs/09-ai-workflow/working-rules.md và context-map.md, cùng AGENTS.md áp dụng.
Kiểm branch/HEAD/git status; giữ nguyên dirty changes. Task của tôi là [issue/plan code].
Chọn PRD/workflow/BR/AC liên quan và đối chiếu code/routes/migrations/OpenAPI.
Chỉ báo scope, dependencies, chênh lệch và test plan; chưa sửa code/chạy destructive test.
Không coi snapshot hoặc test cũ là bằng chứng current Accepted.
```

## Implementation một slice

```text
Triển khai [issue/plan code] theo task brief đã thống nhất: [dán brief].
Đọc startup context và PRD/workflow/AC liên quan. Chỉ sửa files trong scope;
cập nhật contract/types/docs/tests cùng slice; không feature optional/refactor ngoài phạm vi.
Trước test kiểm effective DB/config cache/guard; không RefreshDatabase/migrate:fresh DB dev/shared.
PG constraint/race dùng DB disposable riêng. Nếu scope cần quyền mới, dừng hỏi.
Bàn giao theo docs/09-ai-workflow/handoff-template.md với actual test/evidence,
known defects/tests not run. Không tự push/merge/đóng issue/deploy hoặc nhận Accepted.
```

## Review read only

```text
Review diff của [issue/commit/working changes được chỉ rõ] theo PRD/BR/AC.
Không sửa code, không cài tool hay chạy test destructive. Đọc context và giữ dirty changes.
Tìm bug permission/state/data loss/contract/idempotency/race; ghi severity,
file:line, trigger, impact và test thiếu. Phân biệt verified finding với giả thuyết.
Không yêu cầu refactor chỉ vì style. Kết luận đủ Verified hay còn blocker,
không ký Accepted hoặc tự cập nhật GitHub.
```

## Acceptance có test được giao

```text
Nghiệm thu [gate/slice] với môi trường [DB test/fixture được xác nhận].
Đọc release-gates/test-scenarios/traceability và từng workflow liên quan.
Trước suite xác nhận DB guard/config cache an toàn; chưa xác nhận thì không chạy destructive test.
Chạy các kiểm tra được giao, browser API mode, permission/error/race phù hợp.
Ghi từng AC Pass/Fail/Not run/Blocked theo evidence-template, commit/environment và actual results.
Không dùng mock/số test cũ. Tổng hợp P0/P1 và điều kiện còn thiếu để người nghiệm thu quyết định.
```
