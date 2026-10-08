# Ownership và phối hợp

Phân công kế thừa backlog v3.1; chưa thay đổi người làm. Team phải xác nhận integration owner/người ký gate và mọi thay đổi assignment. Code A/B/C không là issue ID.

## W1 A3 B3 C3

Khanh A3: Catalog/Listing/public/availability API + seed + contract. Dũng B3: Marketplace owner/public UI/API integration. Hoàng C3: prerequisite evidence/G1 và Messaging schema review. A2/C2 closeout do owner cũ xử lý, không C3 tự tuyên bố Done thay.

## W2

Khanh A1 schema/state/OpenAPI delta; A2 File policy; A3 queue/scheduler/event writer + PG harness. Dũng B1 chat; B2 Draft/snapshot UI; B3 invitation/G2. Hoàng C1 Messaging API; C2 Draft/snapshot API; C3 invitation/locks/atomic accept.

## W3

Khanh A1 Session/dashboard; A2 Fee; A3 Report API + Admin UI nhỏ. Dũng B1 Workspace/Session; B2 Task; B3 Progress/Announcement/Fee/lifecycle/Review/notification integration + G3. Hoàng C1 Task/feedback; C2 Progress/Announcement API và CRUD nhỏ/Notification; C3 lifecycle/Review/suspension.

## W4

Khanh A1 deploy; A2 load/observability; A3 backup/restore/setup/handoff. Dũng B1 browser E2E; B2 responsive/a11y; B3 demo/FE handoff. Hoàng C1 permission regression; C2 PG race/failure/queue tests; C3 G4 evidence/triage/signoff preparation.

## Shared file rule

Khanh coordinate migrations/OpenAPI; Dũng FE types/services/UI tokens; Hoàng tests/state/acceptance. Routes là shared hotspot: thông báo file claim trong task brief và reviewer trước edit, không tự refactor routes để tránh conflict. Contract review cần A/C và FE B. Một issue/scoped PR; merge dependency trước consumer; rebase/conflict là thao tác cần theo yêu cầu nhóm, không tự force-push.
