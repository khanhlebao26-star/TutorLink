# OpenAPI operation and schema delta

Baseline: `d157a8eeae662c01d6bd6b031ea53c26a5b05688`.
Operation count: 123 → 123. Added 0, removed 0, changed 34.
Schema components: 60 → 57. Added 0, removed 3, changed 0.

## Changed operations

- `GET /announcements/{announcement}`
- `GET /fee-records/{feeRecord}`
- `GET /invitations`
- `GET /invitations/{invitation}`
- `GET /listings/{slug}`
- `GET /programs/{program}/announcements`
- `GET /programs/{program}/fee-records`
- `GET /programs/{program}/overview`
- `GET /programs/{program}/progress-records`
- `GET /programs/{program}/tasks`
- `GET /tasks/{task}`
- `GET /tasks/{task}/submission`
- `GET /tasks/{task}/submission/versions`
- `PATCH /announcements/{announcement}`
- `PATCH /fee-records/{feeRecord}`
- `PATCH /progress-records/{progressRecord}`
- `PATCH /tasks/{task}`
- `POST /announcements/{announcement}/publish`
- `POST /fee-records/{feeRecord}/mark-paid`
- `POST /fee-records/{feeRecord}/mark-unpaid`
- `POST /fee-records/{feeRecord}/void`
- `POST /programs/{program}/announcements`
- `POST /programs/{program}/cancel`
- `POST /programs/{program}/complete`
- `POST /programs/{program}/fee-records`
- `POST /programs/{program}/pause`
- `POST /programs/{program}/progress-records`
- `POST /programs/{program}/resume`
- `POST /programs/{program}/tasks`
- `POST /tasks/{task}/cancel`
- `POST /tasks/{task}/start`
- `POST /tasks/{task}/submission/request-revision`
- `POST /tasks/{task}/submission/review`
- `PUT /tasks/{task}/submission`

## Removed schemas

- `AnnouncementResource`
- `FeeRecordResource`
- `ProgressRecordResource`

These three resource component definitions were flattened into the operation response schemas by Scramble. Their fields remain documented inline; no route or response envelope was removed. The operation-level changes are metadata/schema updates on existing method/path pairs, not new endpoints.
