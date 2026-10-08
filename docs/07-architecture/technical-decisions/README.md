# Technical decision records

ADR ghi context, quyết định đích/baseline và hệ quả; không thay permission người dùng hoặc công bố implementation hoàn tất. Các ADR mới là bản chuyển hóa proposal v3.1 để nhóm review, chưa có chữ ký review trong lượt tạo docs.

- [ADR001 monolith and REST](ADR-001-monolith-rest-baseline.md): baseline đã thấy trong code; realtime optional.
- [ADR002 chat and idempotency](ADR-002-chat-idempotency.md): constraint nền có, service chưa có.
- [ADR003 snapshot and atomic accept](ADR-003-snapshot-atomic-accept.md): thiết kế đích, schema delta còn mở.
- [ADR004 test database isolation](ADR-004-test-database-isolation.md): requirement an toàn, guard chưa đóng.

Khi sửa quyết định: ghi status Proposed/Approved/Superseded, người duyệt, ngày, task link, migration/contract impact và test. Không âm thầm sửa quyết định cũ để hợp thức hóa code.
