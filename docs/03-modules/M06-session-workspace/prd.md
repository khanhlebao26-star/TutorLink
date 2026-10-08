# PRD Session and Workspace Overview

FR-08, BR-04/10. Program Workspace có sáu tab Overview/Session/Task/Progress/Announcement/Fee. Overview read model gồm next_session, task_counts, latest_progress/announcement và capability fee/review; aggregate không phát sinh N+1.

Session DTO một buổi với scheduled/completed/cancelled, ID mapping rõ trên hai bảng cũ. Tutor owner tạo/update/cancel theo state; member xem. UTC storage/API, timezone Asia/Ho_Chi_Minh khi hiển thị; ends>starts; meeting URL chỉ là link bên ngoài. Delete có nghĩa cancel, giữ history.

AC-G3-01: owner/member/outsider matrix; timezone round-trip; invalid schedule 422; Paused không tạo mới; terminal readonly; cancel không hard-delete; next_session/counts đúng, không tính planned row như delivered; thiếu Session không làm tab khác mất dữ liệu. Session attachment/attendance ngoài scope.
