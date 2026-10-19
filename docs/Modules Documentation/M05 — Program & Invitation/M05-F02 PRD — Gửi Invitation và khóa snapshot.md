# M05-F02 · Gửi Invitation và khóa snapshot

## PRD chức năng · M05 Program & Invitation · Draft

# 1\. Mục tiêu

Tutor gửi offer bất biến tới đúng Customer target thông qua Invitation có hạn 72 giờ.

# 2\. Quy tắc

* Chỉ Tutor owner đủ điều kiện mới được gửi Invitation cho Program Draft.  
* Khi gửi, hệ thống khóa snapshot/version của mục tiêu, delivery\_mode, mức phí, currency, fee\_unit, source context và phạm vi đã thống nhất.  
* Mỗi Program chỉ có tối đa một Invitation pending còn hiệu lực.  
* Invitation lưu Customer đích và expires\_at \= thời điểm gửi \+ 72 giờ. Source Listing thay đổi/archived không làm đổi snapshot.  
* Send retry trên cùng Program không tạo Invitation trùng; side effect/notification chỉ phát sau commit bằng event\_key.

# 3\. Hành vi

Máy chủ xác thực owner, eligibility, trạng thái Draft, target và uniqueness trong transaction. Lỗi gửi không làm mất Draft; client có thể retry trên cùng Program sau khi xử lý lỗi.

# 4\. Tiêu chí chấp nhận

* Gửi thành công tạo đúng một Invitation pending và snapshot có thể đọc lại.  
* Invitation thứ hai không thể được tạo khi đã có pending còn hạn.  
* Thay giá/Listing/Profile sau send không đổi snapshot.  
* Request sai owner/state/eligibility hoặc đồng thời bị conflict an toàn; không tạo member/Program Active.

# 5\. Chênh lệch/câu hỏi mở

* Cần chốt resend/idempotency key và uniqueness wire/schema; schema hiện có index legacy khác target uniqueness theo Program.  
* Invitation active cũ từng mang nghĩa Accepted; target Enum dùng accepted. Cần migration/ADR, không trộn literal.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
