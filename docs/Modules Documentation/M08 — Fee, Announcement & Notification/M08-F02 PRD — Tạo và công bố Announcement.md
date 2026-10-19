# M08-F02 · Tạo và công bố Announcement

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Tutor owner gửi thông tin nội bộ cho các thành viên của Program thông qua Announcement có trạng thái Draft/Published.

# 2\. Quy tắc

* Announcement thuộc một Program; member đọc, Tutor owner tạo/sửa theo Program state.  
* AnnouncementViewState suy ra từ published\_at: draft → published. Hành động target là edit/publish; không tự thêm unpublish.  
* Program Active cho phép tạo; Paused/Completed/Cancelled không tạo Announcement mới. Terminal readonly.  
* File đính kèm phải complete, đúng owner, purpose announcement\_attachment và resource scope theo M10.  
* Chỉ publish mới phát notification; phát sau transaction commit với event\_key để retry không trùng.

# 3\. Tiêu chí chấp nhận

* Draft chưa công bố chỉ được owner chỉnh; không hiện trong inbox member.  
* Publish thành công làm Announcement hiển thị cho đúng members và phát event duy nhất.  
* Member khác Program/outsider không đọc được nội dung hoặc file private.  
* Paused/terminal chặn create/publish theo capability; lỗi không tạo notification giả.

# 4\. Chênh lệch/câu hỏi mở

* Chốt field/title/body, độ dài, sửa nội dung sau publish và có lịch sử version hay không.  
* Migration có announcements; API/UI và notification event writer chưa có trong baseline.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
