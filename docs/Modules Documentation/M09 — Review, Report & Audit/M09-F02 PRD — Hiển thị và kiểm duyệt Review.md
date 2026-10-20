# M09-F02 · Hiển thị và kiểm duyệt Review

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Hiển thị đánh giá hợp lệ cho công chúng và cho Admin có quyền moderation ẩn/hiện review có vi phạm.

# 2\. Quy tắc

* Public DTO chỉ chứa Review hợp lệ visible; rating summary chỉ tổng hợp Review visible đủ điều kiện.  
* Không seed rating giả trong API mode và không tính Review từ Program chưa completed.  
* Admin chỉ hide/show khi có permission admin.reviews.moderate; lưu audit actor, old/new visibility, reason/time.  
* ReviewVisibility: visible ↔ hidden; hide không xóa Review hoặc thay Program history.  
* Public payload không tiết lộ reporter, private files, moderation notes hay verification documents.

# 3\. Tiêu chí chấp nhận

* Hidden Review không xuất hiện trong public list hoặc aggregate; visible hợp lệ xuất hiện.  
* Admin thiếu permission không thể moderate; Admin được cấp permission có thể đổi visibility và audit.  
* Race/stale moderation trả conflict, không mất audit hoặc tạo trạng thái sai.  
* Tutor không sửa/ẩn Review của mình bằng quyền owner.

# 4\. Chênh lệch/câu hỏi mở

* Chốt public sorting/pagination và body visibility khi Review bị hidden.  
* Enum permission có trong Domain, nhưng Review API/UI và public aggregate chưa có baseline.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
