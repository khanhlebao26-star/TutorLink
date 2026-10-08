# ADR001 Monolith và REST baseline

Status: thiết kế v3.1 được ghi lại; baseline code đã đối chiếu, implementation feature còn theo gate. Context: đội ba người, bốn tuần, Laravel/Next/PostgreSQL đã có.

Decision: Laravel monolith cấu trúc hiện tại, REST JSON + polling trước; database queue khi slice cần. Không bắt buộc app/Modules/microservices/Reverb/Redis/search engine riêng.

Consequences: module là ranh giới nghiệp vụ/docs, không source folder bắt buộc; backend giữ state/authorization. Optional realtime không được làm gate lệ thuộc; frontend resync REST. Revisit khi đo tải hoặc approved requirement đòi hỏi, không vì AI thích kiến trúc mới.
