# M05-F05 · Hết hạn và gửi lại Invitation

## PRD chức năng · M05 Program & Invitation · Draft · Phụ thuộc scheduler/queue

# 1\. Mục tiêu

Hệ thống tự hết hạn Invitation pending sau 72 giờ và giữ đường tiếp tục offer mà không sửa bản Invitation cũ.

# 2\. Quy tắc

* Invitation hết hạn khi server time `>= expires_at` và trạng thái vẫn pending; transition pending → expired. `expires_at` là `sent_at + 72 giờ` theo UTC. Accept chỉ hợp lệ khi server time `< expires_at`, không phụ thuộc scheduler đã chạy hay chưa.
* Nếu worker chạy trễ, request PATCH Draft hoặc gửi Invitation mới của Tutor owner tự chuyển Invitation pending đã quá hạn sang expired trong cùng transaction trước khi tiếp tục. Khóa Program/Invitation, giữ snapshot cũ, ghi audit/outbox expiry đúng một lần; lệnh không commit thì bước expire trong transaction đó cũng rollback. `expected_version` của lệnh vẫn được kiểm tra, không gia hạn Invitation cũ.
* GET Invitation đã qua `expires_at` trả `status=expired` theo thời gian server dù row còn `pending`; GET không ghi DB. Worker hoặc mutation hợp lệ sẽ ghi transition, nên quyền Accept không phụ thuộc trạng thái lưu chậm này.
* Expire phải an toàn khi chạy lặp và cạnh tranh với accept/cancel; chỉ một kết quả terminal được commit.  
* Invitation expired là immutable; Program trở lại Draft, không tạo membership.  
* Tutor chỉnh Draft theo version được phép rồi gửi Invitation mới; không kéo dài expiry hoặc tái dùng Invitation cũ.  
* Event expiry/notification sau commit có event\_key unique; worker retry không gửi trùng.

# 3\. Tiêu chí chấp nhận

* Scheduler xử lý Invitation quá hạn; Invitation chưa tới hạn giữ pending.  
* Tutor owner có thể sửa/gửi lại sau biên 72 giờ dù scheduler trễ, khi request hợp lệ và không có xung đột khác; Invitation cũ được ghi expired trước Invitation mới trong cùng transaction.
* Race accept/expire/cancel chỉ tạo một trạng thái kết thúc hợp lệ và membership chỉ có khi accept thắng.  
* Chạy scheduler lặp không phát duplicate event/notification.  
* Customer không accept được sau expiry; Tutor có thể tiếp tục bằng Invitation mới.

# 4\. Chênh lệch/câu hỏi mở

* Baseline chưa có worker/scheduler; cần chọn cách dispatch, retry, queue failure và giám sát theo NFR.  
* Biên 72 giờ UTC và so sánh tại expires_at đã chốt tại BR-18. Cơ chế scheduler, retry queue và giám sát vẫn là việc vận hành trước rollout.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
