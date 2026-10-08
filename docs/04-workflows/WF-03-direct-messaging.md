# WF03 Direct chat và retry

Actor: Customer A, Tutor B, outsider C. Tiền điều kiện: Listing thật, pair/eligibility/file policy đã chốt.

1. A mở Listing L1 của B, create Conversation; server tạo direct_key.
2. A mở Listing L2 của B; kết quả vẫn cùng Conversation, không thêm pair.
3. A gửi message UUID client_message_id; retry cùng payload/key sau lỗi mạng; chỉ một message persist.
4. B polling cursor nhận message; read đánh dấu message cùng thread; reconnect resync REST.
5. Gửi attachment complete; chỉ participants đủ policy tải được.

Nhánh lỗi: concurrent create, key reused khác payload, cursor timestamp bằng nhau, read message thread khác, read lùi, outsider list/send/download, suspended account, 429. Nếu có realtime, REST/event dedupe theo server ID.

Evidence AC-G2-01/02/06 gồm số record sau retry/race và browser polling, không chỉ UI bubble.
