# ADR002 Direct chat và message retry

Status: target v3.1, constraints nền đã có, REST implementation chưa có. Context: nhiều Listing cùng Tutor không nên sinh nhiều conversation.

Decision: server derive direct_key chỉ từ pair Customer–Tutor; context_listing_id nullable metadata. Unique(sender_id,client_message_id), cursor(created_at,id), read monotonic trong cùng conversation.

Consequences: không trust client direct_key; chống concurrent creation bằng DB unique + transaction/retry. Same key payload conflict phải chốt wire trước W2. REST/event dedupe server ID; polling đủ G2, Reverb optional. Legacy notes có “IDs and context” không được thêm Listing vào key.
