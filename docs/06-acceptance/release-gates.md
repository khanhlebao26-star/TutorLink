# Release gates

## Prerequisite A2 B2 C2 closeout

Không xác nhận phase cũ Done khi thiếu: test DB fail-fast hiệu lực kể cả config cache; Admin credential/seed idempotency; browser approval/CSRF; reject/restore permission/audit rollback/completeness evidence. Các task chuẩn bị A3/B3/C3 chỉ làm trong phạm vi nhóm cho phép, không thay closeout.

## G1 Marketplace

WF01–02 API mode đạt AC-G1-01…06, public private-file exclusion, ≥3 category, Listing/search/detail thật. C3 có thể rà schema Messaging nhưng không chuyển G2 chỉ vì schema có bảng.

## G2 Connection and Program

G1 + WF03–04, AC-G2-01…06; chat theo pair, retry/read/polling, Draft/snapshot/invitation accept, PostgreSQL atomic/race và File policies.

## G3 Workspace

G2 + WF05–06, AC-G3-01…06; sáu tab thật, Task feedback/Progress/Fee/Announcement, lifecycle/Review/Notification/Report.

## G4 End project

G3 + AC-G4-01…04; E2E/permissions/races/NFR, clean deploy/backup/restore, demo/handoff reviewed; không P0/P1 chưa xử lý. P2 nếu defer phải có owner, impact và người duyệt, không tự bỏ khỏi báo cáo.

Người nghiệm thu: nhóm/thầy theo phân công được xác nhận. AI chỉ tổng hợp evidence và đề nghị gate status; không tự ký nghiệm thu, đóng GitHub issue hoặc công bố nhóm chuyển phase.
