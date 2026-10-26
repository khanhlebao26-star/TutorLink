# M08-F02 · Tạo và công bố Announcement

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Tutor owner gửi thông tin nội bộ cho các thành viên của Program thông qua Announcement có trạng thái Draft/Published.

# 2\. Quy tắc

* Announcement thuộc một Program; member đọc, Tutor owner tạo/sửa theo Program state.  
* AnnouncementViewState suy ra từ published\_at: draft → published. Hành động target là edit/publish; không tự thêm unpublish.  
* Program Active hoặc Paused cho phép Tutor owner tạo và sửa Announcement Draft; Completed/Cancelled không cho tạo hoặc publish mới.
* Announcement đã published là bất biến, gồm nội dung và tệp đính kèm. Đính chính phải tạo Announcement mới để lịch sử và notification thể hiện rõ thay đổi; không unpublish.
* Announcement có integer `version` bắt đầu từ 1. PATCH Draft và POST publish yêu cầu `expected_version`; mismatch trả 409, không ghi một phần. Mỗi mutation thành công tăng version một lần; replay hợp lệ không phát notification thứ hai.
* Draft chỉ Tutor owner xem được. Published được các member hiện tại của Program đọc; quyền truy cập nội dung và file luôn kiểm tra membership hiện tại, không dựa vào việc biết URL/deep link.
* Tại thời điểm publish, hệ thống chốt các member hiện tại khác với Tutor owner làm recipient notification. Member được thêm vào Program sau đó đọc được Announcement đã published nhưng không nhận thông báo hồi tố.
* File đính kèm phải complete, đã scan clean, đúng owner, purpose announcement\_attachment và resource scope theo M10. Việc liên kết file vào Announcement phải hoàn tất nguyên tử; không cho publish nếu còn file pending/quarantine/failed.
* Allow-list field của Draft gồm `title`, `body` và tối đa 5 file đính kèm. `title` bắt buộc không trắng, 1–180 ký tự; `body` bắt buộc không trắng, 1–10.000 ký tự. Mỗi file tối đa 5 MiB và phải qua allow-list purpose/MIME của M10. `published_at`, author, Program và version do server quản lý.
* Danh sách Announcement sắp mới nhất trước theo `(published_at,id)` giảm dần. MVP không có read state riêng cho Announcement; trạng thái đã đọc chỉ áp dụng cho Notification Inbox.
* Chỉ publish mới phát notification; trong cùng transaction ghi trạng thái Published, audit và một outbox event cho mỗi recipient đã chốt. Sau commit, event tạo một in-app notification cho từng recipient, nội dung trung tính kèm Announcement ID/deep link; không sao chép body hay tên/metadata file. Lỗi hoặc rollback không để lại notification.
* Retry cùng yêu cầu publish trả kết quả đã lưu; không tạo thêm version, audit hay notification. Request cạnh tranh dùng `expected_version` cũ trả 409. Đính chính dùng Announcement mới; Tutor nêu rõ đây là đính chính trong tiêu đề hoặc nội dung, không cần quan hệ dữ liệu riêng trong MVP.

# 3\. Tiêu chí chấp nhận

* Draft chưa công bố chỉ được owner chỉnh; không hiện trong inbox member.  
* Publish thành công làm Announcement hiển thị cho member Program và phát đúng một event cho mỗi member khác với Tutor owner tại thời điểm publish. Member gia nhập sau đó đọc được bài nhưng không nhận thông báo hồi tố.
* Member khác Program/outsider không đọc được nội dung hoặc file private.  
* Active/Paused cho Tutor owner tạo Draft và publish; Completed/Cancelled chặn create/publish theo capability. Lỗi không thay đổi Announcement hoặc tạo notification giả.
* Field lạ, title/body trắng hoặc vượt giới hạn, hơn 5 file, file quá 5 MiB hoặc sai purpose/MIME bị từ chối.
* Chỉ file complete và scan clean được liên kết/publish. Nếu file chưa sẵn sàng hoặc transaction publish thất bại, Announcement không chuyển Published và không có notification.
* PATCH chỉ sửa Draft khi Program Active/Paused; đã published thì conflict. Stale PATCH/publish trả 409 và không tạo notification. Retry publish không nhân đôi version, audit hoặc notification.
* Danh sách trả Published mới nhất trước theo `(published_at,id)` giảm dần; không có trạng thái đã đọc riêng cho Announcement.

# 4\. Chênh lệch/câu hỏi mở

* Allow-list và giới hạn field được chốt tại BR-20; published content immutability/version policy đã được chốt tại BR-19.
* Migration có announcements; API/UI và notification event writer chưa có trong baseline. Contract cần triển khai recipient snapshot lúc publish, authorization membership hiện tại khi đọc/tải file, stable ordering và outbox nguyên tử.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
