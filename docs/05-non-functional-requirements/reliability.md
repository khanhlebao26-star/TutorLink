# Reliability

DB là nguồn sự thật; event/notification sau commit và retry idempotent. Critical mutation atomic: accept/member/Active, Admin decision/audit, review unique, submission/feedback version. Constraint DB và service validation cùng giữ invariant.

Kiểm failure injection tại audit/notification dispatch/storage, reconnect REST, queue retry, accept/cancel/expire races. Có scheduler cho expiry và worker cho queued slice; process phải restart/health/log rõ.

Backup DB + file objects/metadata cùng mốc hoặc có quy trình reconcile; restore vào môi trường riêng, smoke journey/ownership/file links sau restore. Không thử restore đè DB nhóm. Mục tiêu availability nội bộ 99% tháng chưa được đo; RPO/RTO do nhóm chốt ở open questions, không tự hứa.
