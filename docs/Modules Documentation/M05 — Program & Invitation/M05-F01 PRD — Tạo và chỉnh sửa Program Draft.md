# M05-F01 · Tạo và chỉnh sửa Program Draft

## PRD chức năng · M05 Program & Invitation · Draft

# 1\. Mục tiêu

Tutor đủ điều kiện tạo một Program Draft nhắm tới đúng một Customer và chỉnh sửa offer trước khi gửi Invitation.

# 2\. Phạm vi và dữ liệu

* Draft gồm target\_customer\_id (wire OpenAPI: customer\_id; DB: programs.customer\_id), mục tiêu/phạm vi dịch vụ, delivery\_mode, agreed\_fee\_minor, currency=VND, fee\_unit và source Listing tùy chọn.  
* Program là thỏa thuận riêng; Listing chỉ làm nguồn tham khảo. Archive/chỉnh Listing không sửa Program offer hoặc snapshot đã gửi.  
* Một Program có một Tutor owner và một Customer target; chỉ một Customer member được tạo khi Invitation được accept.

# 3\. Quy tắc

* Tạo Program yêu cầu Tutor account active, email verified, Profile approved, không suspended; Customer target phải là Customer hợp lệ.  
* Draft được sửa trước khi snapshot bị khóa. Dữ liệu tiền là số nguyên VND không âm; không dùng float.  
* Tạo/sửa Draft không kích hoạt Program, không tạo membership và không cho tạo workspace activity.

# 4\. Tiêu chí chấp nhận

* Tutor đủ điều kiện tạo Draft và nhận Program ID; API trả trạng thái draft.  
* Tutor khác, Customer hoặc outsider không thể xem/sửa Draft.  
* Sai role, amount, currency, delivery\_mode hoặc target bị từ chối bằng lỗi validation.  
* Thay Listing/Profile sau này không thay đổi các giá trị đã lưu trong Draft; snapshot sẽ khóa ở thời điểm gửi.

# 5\. Chênh lệch/câu hỏi mở

* OpenAPI draft dùng customer\_id làm wire name cho target\_customer\_id. Database Design đề xuất programs.customer\_id (bigint NOT NULL FK users.id), nên migration/mapping DB vẫn cần trước triển khai; chưa có migration.  
* Còn chốt versioning cho snapshot và luật sửa Draft sau khi Invitation bị reject/cancel/expired; wire name customer\_id đã được map trong OpenAPI guide.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
