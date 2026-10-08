# Kịch bản nghiệm thu

Mọi scenario hiện là Required, chưa được đánh dấu Pass trong bộ docs mới. Fixture tối thiểu: Tutor Draft/Submitted/Changes Requested/Approved/Suspended; Customer target/outsider; Admin đủ/thiếu permission; Listing khác unit/status; Program/Invitation các state. Tạo fixture trên môi trường riêng sau preflight.

## G1

- AC-G1-01: register/verify/login/reset qua browser và Mailpit; invalid/expired/signed/age/429; user state đúng và password không bị đổi bởi token sai.
- AC-G1-02: Draft → submit → changes → resubmit → approve; reject fixture riêng; edit locks; reason/audit/state đúng.
- AC-G1-03: owner Listing CRUD/archive, unapproved publish denied, outsider denied; public visibility cùng Profile/User/catalog state.
- AC-G1-04: filter/sort/page/slug/availability thật; VND 200000 hiển thị 200.000, cùng price_unit; private fields không public.
- AC-G1-05: file owner/purpose/complete/download, Admin thiếu permission; mutation thiếu CSRF qua middleware thật không thay state.
- AC-G1-06: seed toàn bộ chạy hai lần không duplicate; restore thiếu permission denied; audit write failure rollback decision; guard fail trước test migration khi config cache trỏ DB không an toàn.

## G2

- AC-G2-01: Listing A/B cùng chat; concurrent pair creation một record; outsider không access.
- AC-G2-02: UUID retry/dedupe/polling/cursor timestamp bằng nhau/read monotonic cùng thread/429/reconnect.
- AC-G2-03: Draft/snapshot/version immutable; send fail giữ Draft; Listing archive không đổi offer.
- AC-G2-04: đúng target accept trước expiry một lần; wrong actor/expired/terminal denied; unique membership/Active atomic.
- AC-G2-05: accept/accept, accept/cancel, accept/expire, accept/suspend trên PostgreSQL hai connection; failure rollback, notification không trùng.
- AC-G2-06: complete storage validation, cross-resource attachment denied, delete giữ historical FK/link; private stream đúng quyền.

## G3

- AC-G3-01: six tabs/Overview Session DTO/timezone/cancel; owner/member/outsider; Active/Paused/terminal capability và counts đúng.
- AC-G3-02: Task assign/submit/resubmit/feedback/version race; stale 409; overdue/submitted_late đúng.
- AC-G3-03: Progress quyền/state/filter; Paused allowed; terminal readonly; Complete không bỏ sót pending work.
- AC-G3-04: Announcement attachment, Fee period unique/VND/disclaimer; Paused/terminal create denied.
- AC-G3-05: recipient notifications/read/read-all, after commit, queue retry/event_key, expiry scheduler vs accept.
- AC-G3-06: Complete/Review once; Cancel no Review; report evidence permission/decision/audit rollback/public exclusion.

## G4

- AC-G4-01: E2E WF01–06 API mode; đầy đủ authorization/state/IDOR/race; không còn P0/P1 trong scope release.
- AC-G4-02: viewport 360/768/desktop, keyboard/focus/labels, loading/empty/retry/401/403/404/409/422/429.
- AC-G4-03: load target 50/peak100, p95 thường<2s/search<3s; log/correlation/error/query evidence theo môi trường.
- AC-G4-04: clean setup/migrate/seed/build, HTTPS/cookie/worker/scheduler smoke, backup DB+files và restore riêng, handoff/runbook reviewed.

Unit/SQLite feature tests không chứng minh PostgreSQL lock/index/race. Browser screenshot không chứng minh atomic mutation. Ghi Pass/Fail/Not run/Blocked từng assertion trong evidence, không một nhãn Pass cho cả nhóm còn thiếu nhánh lỗi.
