# ADR003 Snapshot và atomic accept

Status: target v3.1, cần review delta trước W2. Context: Program history phải không đổi theo Listing/Profile; double accept không được tạo member trùng.

Decision: target Customer cố định, một Pending mỗi Program, snapshot khóa khi invitation Pending/Program Active. Accept locks/rechecks eligibility/state/expiry/version và cập nhật member+Active một transaction; notify after commit/event_key unique.

Consequences: schema pending/active và index program/version/invitee hiện tại chưa đủ. Migration/mapping/backfill/rollback phải được A/C review. Cancel Pending → edit Draft → resend giữ invitation cũ bất biến. Concurrent commands và suspension cùng lock order; PostgreSQL race tests bắt buộc.
