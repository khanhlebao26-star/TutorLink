# PRD Messaging

FR-05, BR-04/06/07/14/15. POST conversations find-or-create theo pair; list own conversations; GET/POST messages; PATCH read. Listing context nullable, không tách Conversation cho Listing A/B cùng Tutor.

Server kiểm Customer/Tutor eligibility cho kết nối mới, membership/state/account cho message. Read-only quyền khi Profile suspended ở quan hệ hiện có phải chốt rõ trước endpoint; không mở write vì UI còn hiển thị chat.

Message nhận UUID client_message_id, lưu trước response; retry giữ cùng record. Cursor created_at+id; read chỉ tham chiếu message cùng conversation, không lùi. Attachment complete, đúng owner/purpose/resource policy. Polling có cleanup, không chồng request, resync sau reconnect.

AC-G2-01/02: hai request create cạnh tranh chỉ một pair; Listing A/B cùng chat; retry/double-click một message; cursor không mất/trùng record cùng timestamp; người ngoài không list/read/send/download; read không cross-thread/lùi; rate limit 429; network retry không tạo message mới. Reverb/private channels optional sau core.
