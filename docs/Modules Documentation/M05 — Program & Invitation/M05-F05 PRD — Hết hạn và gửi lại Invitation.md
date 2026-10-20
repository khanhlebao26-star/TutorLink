# M05-F05 · Hết hạn và gửi lại Invitation

## PRD chức năng · M05 Program & Invitation · Draft · Phụ thuộc scheduler/queue

# 1\. Mục tiêu

Hệ thống tự hết hạn Invitation pending sau 72 giờ và giữ đường tiếp tục offer mà không sửa bản Invitation cũ.

# 2\. Quy tắc

* Invitation hết hạn khi server time `>= expires_at` và trạng thái vẫn pending; transition pending → expired. `expires_at` là `sent_at + 72 giờ` theo UTC. Accept chỉ hợp lệ khi server time `< expires_at`, không phụ thuộc scheduler đã chạy hay chưa.
* Expire phải an toàn khi chạy lặp và cạnh tranh với accept/cancel; chỉ một kết quả terminal được commit.  
* Invitation expired là immutable; Program trở lại Draft, không tạo membership.  
* Tutor chỉnh Draft theo version được phép rồi gửi Invitation mới; không kéo dài expiry hoặc tái dùng Invitation cũ.  
* Event expiry/notification sau commit có event\_key unique; worker retry không gửi trùng.

# 3\. Tiêu chí chấp nhận

* Scheduler xử lý Invitation quá hạn; Invitation chưa tới hạn giữ pending.  
* Race accept/expire/cancel chỉ tạo một trạng thái kết thúc hợp lệ và membership chỉ có khi accept thắng.  
* Chạy scheduler lặp không phát duplicate event/notification.  
* Customer không accept được sau expiry; Tutor có thể tiếp tục bằng Invitation mới.

# 4\. Chênh lệch/câu hỏi mở

* Baseline chưa có worker/scheduler; cần chọn cách dispatch, retry, queue failure và giám sát theo NFR.  
* Biên 72 giờ UTC và so sánh tại expires_at đã chốt tại BR-18. Cơ chế scheduler, retry queue và giám sát vẫn là việc vận hành trước rollout.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
