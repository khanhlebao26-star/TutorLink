# Database Design — TutorLink

Tài liệu mô tả 43 bảng của **thiết kế đích** sau các business rule đã chốt. Cột/bảng ghi “chưa có migration” cần được triển khai trong issue database; sơ đồ draw.io cùng tên phải phản ánh thiết kế đích này. Không suy rằng mọi bảng/cột bên dưới đã tồn tại trong runtime. Riêng `users.terms_accepted_at` bắt buộc cho đăng ký mới; trước khi áp ràng buộc NOT NULL cho toàn bảng phải có phương án lấy lại đồng ý hoặc backfill có bằng chứng đối với tài khoản cũ.

# 01\. Tài khoản và hồ sơ

### **Bảng users**

id — bigint; NOT NULL; PK.

full\_name — varchar(255); NOT NULL.

email — varchar(255); NOT NULL; UK.

email\_verified\_at — timestamp; NULL.

password\_hash — varchar(255); NOT NULL.

remember\_token — varchar(100); NULL.

created\_at — timestamp; NULL.

updated\_at — timestamp; NULL.

role — varchar(20); NOT NULL; mặc định: customer; enum: UserRole.

status — varchar(20); NOT NULL; mặc định: active; enum: UserStatus; cần điều chỉnh.

date\_of\_birth — date; NULL.

terms\_version — varchar(32); NULL trong schema hiện tại; bắt buộc cho đăng ký mới theo quy tắc đích, cần migration/validation tương ứng.

terms\_accepted\_at — timestamptz; NOT NULL cho đăng ký mới theo quy tắc đích. Chưa có trong migration/runtime; phải đi cùng `terms_version` của bản điều khoản đã hiển thị và được người dùng đồng ý. Các tài khoản cũ cần chiến lược backfill hoặc yêu cầu đồng ý lại, không tự gán thời điểm chấp nhận giả.

suspended\_at — timestamptz; NULL.

deleted\_at — timestamptz; NULL.

permissions — json; NOT NULL; mặc định: \[\].

suspension\_reason — text; NULL; đề xuất thêm.

### 

### **Bảng customer\_profiles**

id — bigint; NOT NULL; PK.

user\_id — bigint; NOT NULL; UK FK; tham chiếu users.id.

avatar\_file\_id — bigint; NULL; FK; tham chiếu files.id.

city — varchar(120); NULL.

district — varchar(120); NULL.

bio — text; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### 

### **Bảng tutor\_profiles**

id — bigint; NOT NULL; PK.

user\_id — bigint; NOT NULL; UK FK; tham chiếu users.id.

headline — varchar(160); NOT NULL.

bio — text; NULL.

experience\_years — smallint; NOT NULL; mặc định: 0\.

status — varchar(20); NOT NULL; mặc định: draft; enum: TutorProfileStatus; cần điều chỉnh.

submitted\_at — timestamptz; NULL.

reviewed\_by — bigint; NULL; FK; tham chiếu users.id.

reviewed\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

review\_reason — text; NULL.

suspended\_at — timestamptz; NULL.

suspension\_reason — text; NULL.

avatar\_file\_id — bigint; NULL; FK; tham chiếu files.id.

public\_slug — varchar(180); NULL; UK.

status\_before\_suspension — varchar(20); NULL; migration H02 lưu trạng thái trước đình chỉ, backfill legacy suspended thành approved theo hành vi endpoint cũ.

qualification\_claims — jsonb; NOT NULL; mặc định: []; danh sách claim có cấu trúc `type` (`degree`/`certificate`), tên claim và thông tin hiển thị được allow-list; không chứa file hoặc nội dung giấy tờ.

assigned\_admin\_id — bigint; NULL; FK users.id; Admin hiện được giao xử lý.

assigned\_by — bigint; NULL; FK users.id; Admin đổi assignment gần nhất.

assigned\_at — timestamptz; NULL.

assignment\_version — integer; NOT NULL DEFAULT 0; tăng khi giao/chuyển/thu hồi.

Quy tắc D06 đã chốt: server tạo `public_slug` một lần khi tạo Tutor Profile từ tên và hậu tố ID duy nhất; đổi tên hiển thị không đổi slug. Không cần bảng alias/redirect slug trong MVP. Unique constraint ngăn trùng; chỉ hồ sơ đủ public gate mới được tra cứu qua slug.

BR-16 yêu cầu lưu bản sửa của Profile approved tách khỏi dữ liệu công khai. Duyệt revision phải kiểm version và thay dữ liệu công khai cùng transaction; request-changes/reject giữ bản approved cũ. `qualification_claims` là dữ liệu có cấu trúc để xác định degree/certificate bắt buộc; không suy từ headline/bio. Migration H02 bổ sung qualification claims và revision; DTO trả latest revision cho owner/Admin đúng scope. Việc áp dụng migration trên từng môi trường cần evidence riêng.

### Bảng tutor\_profile\_revisions — BR-16, migration BE-H02

id — bigint; NOT NULL; PK.

tutor\_profile\_id — bigint; NOT NULL; FK tutor\_profiles.id.

revision\_no — integer; NOT NULL; tăng trong phạm vi một Tutor Profile.

status — varchar(20); NOT NULL DEFAULT draft; enum: TutorProfileRevisionStatus.

avatar\_file\_id — bigint; NULL; FK files.id RESTRICT, giữ tệp avatar của revision khỏi bị xóa.

payload — jsonb; NOT NULL; snapshot allow-list của field Profile/specialization đang sửa, gồm `qualification_claims` có cấu trúc; không sao chép nội dung File.

version — integer; NOT NULL DEFAULT 1; kiểm `expected_version` khi sửa/duyệt.

submitted\_at — timestamptz; NULL.

reviewed\_by — bigint; NULL; FK users.id.

reviewed\_at — timestamptz; NULL.

review\_reason — text; NULL; bắt buộc khi request-changes/reject.

created\_at — timestamptz; NOT NULL.

updated\_at — timestamptz; NOT NULL.

UNIQUE(tutor\_profile\_id,revision\_no); partial UNIQUE(tutor\_profile\_id) WHERE status IN (draft,submitted,changes_requested). Chỉ một revision chưa đóng cho mỗi Profile approved. `approved`/`rejected` là terminal cho revision đó; approved copy snapshot đã duyệt sang Profile cùng transaction, giữ bản công khai cũ trong lúc chờ.

# 02 · Danh mục và marketplace

### **Bảng categories**

id — bigint; NOT NULL; PK.

name — varchar(120); NOT NULL.

slug — varchar(140); NOT NULL; UK.

status — varchar(20); NOT NULL; mặc định: active; enum: CatalogStatus.

sort\_order — integer; NOT NULL; mặc định: 0\.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### 

### **Bảng subcategories**

id — bigint; NOT NULL; PK.

category\_id — bigint; NOT NULL; FK; tham chiếu categories.id.

name — varchar(120); NOT NULL.

slug — varchar(140); NOT NULL.

status — varchar(20); NOT NULL; mặc định: active; enum: CatalogStatus.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

# 02 · Danh mục và marketplace

## specializations  ·  tutor\_specializations

### Bảng specializations

id — bigint; NOT NULL; PK.

subcategory\_id — bigint; NOT NULL; FK; tham chiếu subcategories.id.

name — varchar(120); NOT NULL.

slug — varchar(140); NOT NULL.

status — varchar(20); NOT NULL; mặc định: active; enum: CatalogStatus.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng tutor\_specializations

tutor\_profile\_id — bigint; NOT NULL; PK FK; tham chiếu tutor\_profiles.id.

specialization\_id — bigint; NOT NULL; PK FK; tham chiếu specializations.id.

created\_at — timestamptz; NOT NULL.

Khóa ghép / unique: specializations: UNIQUE(subcategory\_id,slug); tutor\_specializations: PRIMARY(tutor\_profile\_id,specialization\_id).

# 02 · Danh mục và marketplace (tiếp)

## service\_listings  ·  availability\_rules

### Bảng service\_listings

id — bigint; NOT NULL; PK.

tutor\_profile\_id — bigint; NOT NULL; FK; tham chiếu tutor\_profiles.id.

specialization\_id — bigint; NOT NULL; FK; tham chiếu specializations.id.

slug — varchar(180); NOT NULL; UK.

title — varchar(180); NOT NULL.

description — text; NOT NULL.

delivery\_mode — varchar(20); NOT NULL; enum: DeliveryMode.

city — varchar(120); NULL.

district — varchar(120); NULL.

price\_from\_minor — bigint; NOT NULL.

currency — char(3); NOT NULL; mặc định: VND.

price\_unit — varchar(30); NOT NULL; enum: PriceUnit.

status — varchar(20); NOT NULL; mặc định: draft; enum: ListingStatus; cần điều chỉnh.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng listing\_moderation\_holds — migration BE-D02

Bản ghi hold riêng giữ nguồn `inactive` để Admin restore không ghi đè quyết định của Tutor. Tối đa một hold còn hiệu lực trên mỗi Listing; lịch sử hide/restore và audit không bị xóa khi gỡ hold.

id — bigint; NOT NULL; PK.

listing\_id — bigint; NOT NULL; FK tới service\_listings.id.

hidden\_by\_admin\_id — bigint; NOT NULL; FK tới users.id.

hidden\_at — timestamptz; NOT NULL.

hide\_reason — text; NOT NULL.

hide\_reason\_code — varchar(40); NOT NULL; ListingModerationReasonCode cho hide.

reactivate\_on\_restore — boolean; NOT NULL; true chỉ khi Admin hide Listing đang active; chuyển false nếu Tutor chủ động pause trong thời gian hold.

restored\_by\_admin\_id — bigint; NULL; FK tới users.id.

restored\_at — timestamptz; NULL.

restore\_reason — text; NULL khi hold còn hiệu lực, bắt buộc khi restore.

restore\_reason\_code — varchar(40); NULL khi hold còn hiệu lực, bắt buộc khi restore.

Migration có partial UNIQUE cho hold còn hiệu lực theo listing\_id. Không cần bảng assignment/scope Listing: Admin active có `admin.listings.moderate` được xử lý toàn bộ Listing trong MVP, audit ghi scope `all_listings`. Hide/restore, đổi ListingStatus, audit và notification outbox được ghi nguyên tử theo Business Rules/M03-F10; runtime vẫn chưa có hai route Admin này.

### Bảng availability\_rules

id — bigint; NOT NULL; PK.

tutor\_profile\_id — bigint; NOT NULL; FK; tham chiếu tutor\_profiles.id.

weekday — smallint; NOT NULL.

start\_time — time; NOT NULL.

end\_time — time; NOT NULL.

timezone — varchar(64); NOT NULL; mặc định: Asia/Ho\_Chi\_Minh.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

# 02 · Danh mục và marketplace (tiếp)

## favorites

### Bảng favorites

user\_id — bigint; NOT NULL; PK FK; tham chiếu users.id.

tutor\_profile\_id — bigint; NOT NULL; PK FK; tham chiếu tutor\_profiles.id.

created\_at — timestamptz; NOT NULL.

Khóa ghép / unique: favorites: PRIMARY(user\_id,tutor\_profile\_id).

# 03 · Tệp và xác minh

## files  ·  verification\_documents

### Bảng files

id — bigint; NOT NULL; PK.

owner\_id — bigint; NOT NULL; FK; tham chiếu users.id.

disk — varchar(32); NOT NULL; mặc định: s3.

object\_key — varchar(1024); NOT NULL; UK.

original\_name — varchar(255); NULL.

mime\_type — varchar(150); NOT NULL.

size\_bytes — bigint; NOT NULL.

visibility — varchar(20); NOT NULL; mặc định: private; enum: FileVisibility; cần điều chỉnh.

checksum\_sha256 — char(64); NULL.

scan\_status — varchar(20); NOT NULL; mặc định: pending; enum: FileScanStatus.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

purpose — varchar(50); NOT NULL; enum: FilePurpose; cần điều chỉnh.

### Bảng verification\_documents

id — bigint; NOT NULL; PK.

tutor\_profile\_id — bigint; NOT NULL; FK; tham chiếu tutor\_profiles.id.

file\_id — bigint; NOT NULL; FK; tham chiếu files.id.

document\_type — varchar(50); NOT NULL; enum: VerificationDocumentType.

status — varchar(20); NOT NULL; mặc định: pending; enum: VerificationDocumentStatus. Migration BE-D02 chuyển legacy `active` sang `approved` và giới hạn trạng thái theo enum đích.

reviewed\_by — bigint; NULL; FK; tham chiếu users.id.

reviewed\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

expires\_at — timestamptz; NULL; được thêm trong migration BE-D02; NULL nghĩa là không có ngày hết hạn.

review\_reason — text; NULL; migration H02 lưu khi quyết định giấy tờ.

Khóa ghép / unique: verification\_documents: UNIQUE(tutor\_profile\_id,file\_id).

files chỉ lưu metadata, nội dung nằm ở object storage. purpose bắt buộc với upload mới; visibility chỉ private/public. Chỉ file scan\_status=clean và đúng owner/purpose mới được gắn vào tài nguyên. `expires_at` dùng để xác định giấy tờ hết hiệu lực. Avatar tham chiếu trực tiếp bằng avatar\_file\_id; profile\_document không tự bổ sung FileResourceType mới.

BR-16: thay file tạo hàng VerificationDocument mới để giữ quyết định cũ. Có thể có nhiều hàng cùng `document_type`; gate chọn giấy tờ hiện hành approved, chưa expired, đúng owner/File hợp lệ. Identity yêu cầu ít nhất một `identity_card` hoặc `passport`; `other` không thay thế. Degree/certificate chỉ là yêu cầu nếu có qualification claim cấu trúc tương ứng. Document bắt buộc expired làm mất eligibility public/tạo dịch vụ mới nhưng không tự đổi TutorProfileStatus/UserStatus; khi bản thay thế approved, kiểm lại public gate. Migration BE-D02 bổ sung `expires_at` và vocabulary trạng thái đích; job expiry chưa được triển khai.

# 03 · Tệp và xác minh (tiếp)

## file\_links

### Bảng file\_links

id — bigint; NOT NULL; PK.

file\_id — bigint; NOT NULL; FK; tham chiếu files.id.

resource\_type — varchar(40); NOT NULL; enum: FileResourceType.

resource\_id — bigint; NOT NULL.

purpose — varchar(50); NOT NULL; mặc định: attachment; enum: FileLinkPurpose; cần điều chỉnh.

created\_at — timestamptz; NOT NULL.

Khóa ghép / unique: file\_links: UNIQUE(file\_id,resource\_type,resource\_id).

# 04 · Hội thoại và tin nhắn

## conversations  ·  conversation\_members

### Bảng conversations

id — bigint; NOT NULL; PK.

type — varchar(20); NOT NULL; mặc định: direct; cần điều chỉnh.

context\_listing\_id — bigint; NULL; FK; tham chiếu service\_listings.id.

direct\_key — varchar(191); NOT NULL; UK; cần điều chỉnh.

status — varchar(20); NOT NULL; mặc định: active; enum: ConversationStatus; cần điều chỉnh.

activated\_at — timestamptz; NULL.

ended\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng conversation\_members

conversation\_id — bigint; NOT NULL; PK FK; tham chiếu conversations.id.

user\_id — bigint; NOT NULL; PK FK; tham chiếu users.id.

last\_read\_message\_id — bigint; NULL; FK; tham chiếu messages.id.

last\_read\_at — timestamptz; NULL.

joined\_at — timestamptz; NOT NULL.

Khóa ghép / unique: conversation\_members: PRIMARY(conversation\_id,user\_id).

Một Conversation cho đúng cặp Customer–Tutor: type cố định direct; direct\_key chuẩn hóa theo cặp và UNIQUE NOT NULL. conversation\_members có đúng hai người. last\_read\_message\_id phải thuộc cùng Conversation. System message do backend tạo; sender\_id dùng một tài khoản hệ thống được quản lý, không nhận từ client. Đóng chat theo điều kiện Program completed và không còn Program active/paused hoặc lời mời pending còn hiệu lực; dịch vụ mới mở lại cùng chat.

BR-17: `context_listing_id` chỉ được ghi khi tạo Conversation đầu tiên, có thể NULL và không bị ghi đè khi find-or-create/tái mở từ Listing khác. Request context mới vẫn phải qua gate Listing nhưng không thay metadata/lịch sử cũ. Đình chỉ một participant không xóa `conversation_members`, message hoặc read cursor và không tự đổi status; participant còn active đọc lịch sử theo membership, participant bị đình chỉ bị từ chối truy cập kể cả khi còn session cũ, khôi phục thì đọc lại theo membership. Migration hiện có nhưng chưa có route/service thực thi chính sách này.

# 04 · Hội thoại và tin nhắn

### Bảng messages

id — bigint; NOT NULL; PK.

conversation\_id — bigint; NOT NULL; FK; tham chiếu conversations.id.

sender\_id — bigint; NOT NULL; FK; tham chiếu users.id.

message\_type — varchar(20); NOT NULL; mặc định: text; enum: MessageType.

body — text; NULL.

client\_message\_id — uuid; NOT NULL.

request\_hash — char(64); NULL cho message lịch sử; bắt buộc với message mới; SHA-256 payload message canonical đã validation để phân biệt retry cùng key với payload khác theo BR-21. Không tự tạo hash giả cho bản ghi cũ khi migration.

metadata — jsonb; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Khóa ghép / unique: messages: UNIQUE(sender\_id,client\_message\_id).

BR-21 schema implications: inbox order and unread count derive from `(created_at,id)` and member `last_read_message_id`; report read receipts only at Conversation level. File `scan_status=clean` requires scanner approval; chat FileLink and Message commit atomically. Orphan cleanup uses pending 24h / complete-unlinked 7d; linked file retention follows owning resource.

# 05 · Program và lời mời

## programs  ·  program\_invitations

### Bảng programs

id — bigint; NOT NULL; PK.

owner\_tutor\_profile\_id — bigint; NOT NULL; FK; tham chiếu tutor\_profiles.id.

source\_listing\_id — bigint; NULL; FK; tham chiếu service\_listings.id.

version — integer; NOT NULL; mặc định: 1\.

title — varchar(180); NOT NULL.

goal — text; NULL.

level — varchar(80); NULL.

delivery\_mode — varchar(20); NOT NULL; enum: DeliveryMode.

agreed\_fee\_minor — bigint; NOT NULL.

currency — char(3); NOT NULL; mặc định: VND.

fee\_unit — varchar(30); NOT NULL; enum: PriceUnit.

location\_text — text; NULL.

meeting\_url — text; NULL.

schedule\_note — text; NULL.

timezone — varchar(64); NOT NULL; IANA timezone authoritative của Program; mặc định: Asia/Ho\_Chi\_Minh.

planned\_session\_count — integer; NULL.

status — varchar(20); NOT NULL; mặc định: draft; enum: ProgramStatus; cần điều chỉnh.

offer\_snapshot — jsonb; NULL.

snapshot\_locked\_at — timestamptz; NULL.

activated\_at — timestamptz; NULL.

ends\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id; đề xuất thêm.

### Bảng program\_invitations

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

program\_version — integer; NOT NULL.

invitee\_user\_id — bigint; NOT NULL; FK; tham chiếu users.id.

invited\_by — bigint; NOT NULL; FK; tham chiếu users.id.

status — varchar(20); NOT NULL; mặc định: pending; enum: InvitationStatus; cần điều chỉnh.

offer\_snapshot — jsonb; NOT NULL.

idempotency\_key — uuid; NOT NULL.

expires\_at — timestamptz; NOT NULL.

responded\_at — timestamptz; NULL.

accepted\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Khóa ghép / unique: programs dùng PK(id), `version` là version offer hiện tại; không dùng FK Invitation về version row lịch sử vì snapshots bất biến nằm trên từng Invitation. program\_invitations: UNIQUE(invited\_by,idempotency\_key); thêm partial unique `UNIQUE(program\_id) WHERE status=pending`.

programs.customer\_id là Customer đích cố định; owner\_tutor\_profile\_id là Tutor duy nhất. `Program.version` là offer version bắt đầu từ 1, tăng sau mỗi Draft edit. PATCH/send dùng `expected_version`; Invitation giữ `program_version` bất biến cùng snapshot và expires\_at = `sent_at` + 72 giờ UTC. `sent_at` là thời điểm server tạo Invitation, lưu cùng timestamp `created_at`; các Invitation cũ giữ program_version/snapshot của mình khi Program Draft về sau tăng version. Accept hợp lệ chỉ khi server time < expires\_at; worker trễ không kéo dài hạn. Hết hạn phải chuyển Invitation sang expired trước khi gửi mới. Draft bị khóa khi pending; Draft cancel phải cancel Invitation cùng transaction. Accept kiểm Program/Invitation/version/snapshot/expiry, tạo hai membership và chuyển Program active trong một transaction. Nếu source Listing đang hold thì accept bị từ chối 409, Invitation không đổi; có thể accept sau khi hold gỡ và trước expiry nếu Customer/Tutor còn đủ điều kiện; không recheck Listing active vì snapshot đã khóa. Hủy Active/Paused bị chặn khi còn Session scheduled hoặc Task todo/in\_progress/submitted; không tự cascade-cancel. Duy nhất một Customer member trong suốt Program; không tự thu hồi membership khi completed. Lock/isolation protocol cần kiểm thử cạnh tranh PostgreSQL trước triển khai.

# 05 · Program và lời mời (tiếp)

## program\_members

### Bảng program\_members

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

user\_id — bigint; NOT NULL; FK; tham chiếu users.id.

member\_role — varchar(20); NOT NULL; enum: ProgramMemberRole; cần điều chỉnh.

status — varchar(20); NOT NULL; mặc định: active; enum: ProgramMemberStatus.

joined\_at — timestamptz; NOT NULL.

left\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Khóa ghép / unique: program\_members: UNIQUE(program\_id,user\_id).

# 06 · Lịch học

## program\_sessions  ·  lesson\_sessions

### Bảng program\_sessions

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

program\_version — integer; NOT NULL.

sequence\_number — integer; NOT NULL.

title — varchar(180); NOT NULL.

planned\_starts\_at — timestamptz; NOT NULL.

planned\_ends\_at — timestamptz; NOT NULL.

timezone — varchar(64); NOT NULL; snapshot `Program.timezone` tại lúc lập Session; mọi instant lưu UTC.

delivery\_mode — varchar(20); NOT NULL; enum: DeliveryMode.

location\_text — text; NULL.

meeting\_url — text; NULL.

status — varchar(20); NOT NULL; mặc định: scheduled; enum: SessionStatus; cần điều chỉnh.

version — integer; NOT NULL DEFAULT 1; BR-19, optimistic concurrency cho Session.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng lesson\_sessions

id — bigint; NOT NULL; PK.

program\_session\_id — bigint; NOT NULL; UK FK; tham chiếu program\_sessions.id.

status — varchar(20); NOT NULL; mặc định: scheduled; enum: SessionStatus; cần điều chỉnh.

started\_at — timestamptz; NULL.

ended\_at — timestamptz; NULL.

tutor\_joined\_at — timestamptz; NULL.

customer\_joined\_at — timestamptz; NULL.

completion\_note — text; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Khóa ghép / unique: program\_sessions: UNIQUE(program\_id,program\_version,sequence\_number).

program\_sessions lưu kế hoạch; lesson\_sessions lưu kết quả thực hiện, quan hệ 1 → 0..1. Hai cột status dùng SessionStatus scheduled/completed/cancelled và được cập nhật nhất quán trong một transaction. Không dùng planned/in\_progress/no\_show làm enum đích. tutor\_joined\_at/customer\_joined\_at là cột legacy được giữ lại, không suy ra chức năng điểm danh. program\_version là số phiên bản lịch sử, không đặt FK ghép tới version hiện tại của programs.

Theo BR-19, mọi mutation Session kiểm `expected_version` dưới cùng transaction với state gate; PATCH lịch chỉ khi Program Active và Session scheduled. Complete/cancel trong Active/Paused tăng version, cùng với tạo lesson\_sessions nguyên tử khi complete. Replay request hợp lệ trả kết quả đã lưu mà không tăng version/audit lần hai. `Program.timezone` authoritative; program\_sessions.timezone snapshot giá trị đó, instant lưu UTC. `meeting_url` chỉ nhận HTTPS, tối đa 2.048 ký tự, không credentials.

# 07 · Nhiệm vụ và bài nộp

## tasks  ·  task\_submissions

### Bảng tasks

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

title — varchar(180); NOT NULL.

description — text; NULL.

due\_at — timestamptz; NULL.

status — varchar(20); NOT NULL; mặc định: todo; enum: TaskStatus.

version — integer; NOT NULL DEFAULT 1; BR-19, tăng theo mỗi Task mutation thành công.

assigned\_to — bigint; NULL; FK; tham chiếu users.id.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

current\_submission\_id — bigint; NULL; FK; tham chiếu task\_submissions.id; đề xuất thêm.

### Bảng task\_submissions

id — bigint; NOT NULL; PK.

task\_id — bigint; NOT NULL; FK; tham chiếu tasks.id.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id.

content — text; NULL; tối đa 10.000 ký tự; phải có nội dung không trắng hoặc file đính kèm.

status — varchar(20); NOT NULL; mặc định: submitted; enum: SubmissionStatus; cần điều chỉnh.

submitted\_at — timestamptz; NOT NULL.

feedback — text; NULL.

reviewed\_by — bigint; NULL; FK; tham chiếu users.id.

reviewed\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

submission\_version — integer; NOT NULL; đề xuất thêm.

idempotency\_key — uuid; NOT NULL; F05; khóa chống gửi trùng cùng version.

request\_hash — char(64); NOT NULL; SHA-256 JSON canonical RFC 8785 của `expected_submission_version`, `content` và file IDs đã validation; không lưu bản sao nội dung riêng tư.

Khóa ghép / unique: task\_submissions: UNIQUE(task\_id,customer\_id,submission\_version); UNIQUE(task\_id,customer\_id,idempotency\_key).

Mỗi lần nộp/cập nhật chủ động tạo một task\_submissions mới: content, tệp và feedback của version cũ được bảo toàn; submission\_version tăng từ 1\. tasks.current\_submission\_id chỉ tới version mới nhất của chính Task và Customer được giao. Insert, cập nhật pointer, Task state và file links thực hiện trong cùng transaction. PUT bắt buộc Idempotency-Key UUID; lưu key và SHA-256 request\_hash theo BR-20, giữ theo lịch sử submission (không hết hạn khi version còn lưu). UNIQUE(task\_id,customer\_id,idempotency\_key) giới hạn key theo người nộp và Task. Mỗi Submission tối đa 5 file, mỗi file tối đa 5 MiB; MIME PDF/JPG/PNG/DOC/DOCX. Sau khi kiểm tra quyền/assignee, cùng key \+ hash trả đúng version đã commit mà không tăng version/audit/notification; cùng key nhưng hash khác trả 409\. Key mới chỉ tạo version nếu expected\_submission\_version khớp version hiện tại; stale trả 409\. Feedback chỉ ghi cho version mới nhất và kiểm expected\_submission\_version; reviewed\_by/reviewed\_at/feedback gắn vào đúng row version.

`tasks.version` theo BR-19 tăng mỗi mutation Task thành công, gồm edit/start/cancel và status change cùng Submission/feedback. PATCH/start/cancel kiểm `expected_version`; Submission/feedback giữ guard `expected_submission_version` riêng. Các thay đổi liên quan phải đồng bộ trong transaction; replay không tăng version/audit/notification.

# 08 · Tiến độ và thông báo lớp

## progress\_records  ·  announcements

### Bảng progress\_records

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id.

metric\_name — varchar(100); NOT NULL; MVP chỉ nhận `progress_percent`.

value — smallint; NOT NULL; check 0–100 inclusive; Tutor tự đánh giá.

note — text; NULL.

recorded\_by — bigint; NOT NULL; FK; tham chiếu users.id.

recorded\_at — timestamptz; NOT NULL; server-assigned instant.

version — integer; NOT NULL DEFAULT 1; optimistic concurrency cho PATCH.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng announcements

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

author\_id — bigint; NOT NULL; FK; tham chiếu users.id.

title — varchar(180); NOT NULL.

body — text; NOT NULL.

published\_at — timestamptz; NULL.

version — integer; NOT NULL DEFAULT 1; BR-19, optimistic concurrency cho Draft/publish.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Progress lưu metric\_name/value/note; không thêm status. Chỉ cho `metric_name=progress_percent`, value nguyên 0–100. customer\_id phải là Customer của Program; recorded\_by là Tutor owner. Mọi PATCH kiểm `expected_version`, tăng version và ghi audit before/after; không hard-delete. `recorded_at` do server đặt; đọc/hiển thị theo Program timezone. Announcement draft/published suy ra từ published\_at; bản nháp không hiển thị với Customer. Title/body giới hạn 180/10.000 ký tự; tối đa 5 file, mỗi file tối đa 5 MiB, MIME PDF/JPG/PNG/DOC/DOCX. Chỉ thành viên phù hợp của Program được đọc nội dung và tệp workspace.

Announcement có `version` theo BR-19; PATCH Draft và publish kiểm `expected_version`. Khi published, nội dung/tệp không được sửa; đính chính bằng record mới.

# 09 · Phí, đánh giá và xử lý vi phạm

## fee\_records  ·  reviews

### Bảng fee\_records

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

period\_start — date; NOT NULL.

period\_end — date; NOT NULL.

amount\_minor — bigint; NOT NULL.

currency — char(3); NOT NULL; mặc định: VND.

status — varchar(20); NOT NULL; mặc định: declared; enum: FeeRecordStatus; cần điều chỉnh.

disclaimer — text; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

tutor\_payment\_mark — varchar(20); NOT NULL; mặc định: unmarked; enum: TutorPaymentMark; đề xuất thêm.

version — integer; NOT NULL DEFAULT 1; BR-19, optimistic concurrency cho edit/mark/void.

voided\_at — timestamptz; NULL; đề xuất thêm.

Fee `version` theo BR-19 tăng mỗi lần sửa/mark/void thành công; các mutation kiểm `expected_version`. Chỉ record declared chưa marked_paid được sửa amount; kỳ tháng và record voided giữ nguyên.

voided\_by — bigint; NULL; FK; tham chiếu users.id; đề xuất thêm.

void\_reason — text; NULL; đề xuất thêm.

payment\_marked\_at — timestamptz; NULL; đề xuất thêm.

payment\_marked\_by — bigint; NULL; FK; tham chiếu users.id; đề xuất thêm.

payment\_mark\_reason — text; NULL; đề xuất thêm.

### Bảng reviews

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id.

tutor\_profile\_id — bigint; NOT NULL; FK; tham chiếu tutor\_profiles.id.

rating — smallint; NOT NULL.

comment — text; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

visibility — varchar(20); NOT NULL; mặc định: visible; enum: ReviewVisibility; đề xuất thêm.

moderated\_at — timestamptz; NULL; đề xuất thêm.

moderated\_by — bigint; NULL; FK; tham chiếu users.id; đề xuất thêm.

moderation\_reason — text; NULL; đề xuất thêm.

Khóa ghép / unique: fee\_records: UNIQUE(program\_id,period\_start); reviews: UNIQUE(program\_id,customer\_id).

Fee Record có hai enum độc lập: status=declared/voided và tutor\_payment\_mark=unmarked/marked\_paid/marked\_unpaid. Kỳ tháng lưu period\_start=ngày đầu tháng, period\_end=ngày cuối cùng cùng tháng theo lịch Program.timezone; API suy ra YYYY-MM. UNIQUE(program\_id,period\_start) áp dụng cả voided, không tái khai báo cùng kỳ. Số tiền VND nguyên ≥0; void giữ dấu thanh toán và là trạng thái cuối. Mỗi sửa phí/dấu thanh toán/void ghi Audit với lý do và thời gian. Legacy `confirmed` không được tự suy thành bằng chứng thanh toán nếu chưa xác minh ngữ nghĩa. BR-22: period\_start không trước tháng activated\_at hoặc sau tháng hiện tại trong Program.timezone; service kiểm tra vì cần timezone/current month. amount\_minor=0 giữ payment mark unmarked; service không cho mark paid/unpaid hoặc sửa về 0 khi mark đã được ghi. DB check tương ứng bảo đảm amount=0 ⇒ mark=unmarked. Mọi amount edit/void lưu reason trong audit before/after; Customer đọc allow-list reason/update time. Idempotency-Key Fee cần durable command replay receipt (actor\_id, key, method/path, request hash, response snapshot), unique theo actor/key và giữ tối thiểu theo retention của Fee Record. Tạo hoặc sửa/mark/void thành công ghi notification\_outbox `fee_record_changed` với event key theo record/version trong cùng transaction; retry không tạo side effect lần hai. Review chỉ tạo cho Program completed hợp lệ, rating 1..5; visibility quyết định công khai. Report dùng open/resolved/dismissed.

# 09 · Phí, đánh giá và xử lý vi phạm (tiếp)

## reports

### Bảng reports

id — bigint; NOT NULL; PK.

reporter\_id — bigint; NOT NULL; FK; tham chiếu users.id.

resource\_type — varchar(40); NOT NULL; enum: ReportResourceType.

resource\_id — bigint; NOT NULL.

reason — varchar(100); NOT NULL; enum: ReportReasonCode.

details — text; NULL. API Report dùng tên trường `description` và ánh xạ vào cột này; không đổi tên cột dữ liệu.

assigned\_admin\_id — bigint; NULL; FK users.id; Admin hiện được giao xử lý.

assigned\_by — bigint; NULL; FK users.id; Admin đổi assignment gần nhất.

assigned\_at — timestamptz; NULL.

assignment\_version — integer; NOT NULL DEFAULT 0; tăng khi giao/chuyển/thu hồi.

status — varchar(20); NOT NULL; mặc định: open; enum: ReportStatus; cần điều chỉnh.

handled\_by — bigint; NULL; FK; tham chiếu users.id.

handled\_at — timestamptz; NULL.

resolution\_note — text; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

# 10 · Thông báo và kiểm toán

## notifications  ·  notification\_outbox  ·  audit\_logs

### Bảng notifications

id — uuid; NOT NULL; PK.

user\_id — bigint; NOT NULL; FK; tham chiếu users.id.

type — varchar(100); NOT NULL; enum: NotificationType.

payload — jsonb; NOT NULL.

read\_at — timestamptz; NULL.

created\_at — timestamptz; NOT NULL.

event\_key — varchar(191); NOT NULL; đề xuất thêm.

### Bảng notification\_outbox

### id — uuid; NOT NULL; PK.

### user\_id — bigint; NOT NULL; FK; tham chiếu users.id.

### type — varchar(100); NOT NULL; enum: NotificationType.

### resource\_type — varchar(40); NOT NULL; loại tài nguyên được phép trong notification.

### resource\_id — bigint; NOT NULL.

### event\_key — varchar(191); NOT NULL; được tạo một lần trong transaction nghiệp vụ.

### payload — jsonb; NOT NULL; allow-list tối thiểu, không chứa nội dung chat/evidence.

### attempt\_count — integer; NOT NULL; mặc định: 0\.

### retry\_started\_at — timestamptz; NOT NULL; đặt bằng created\_at lúc insert; bắt đầu cửa sổ retry 60 phút, đặt lại khi operator cho phép replay.

### available\_at — timestamptz; NOT NULL; thời điểm worker được thử lại.

### processed\_at — timestamptz; NULL.

### failed\_at — timestamptz; NULL.

### replay\_requested\_at — timestamptz; NULL; operator cho phép thử lại row đã failed, giữ event\_key cũ.

### resolved\_at — timestamptz; NULL; thời điểm xử lý xong row đã failed, kể cả quyết định không gửi lại.

### last\_error\_code — varchar(80); NULL; mã an toàn, không lưu raw exception.

### created\_at — timestamptz; NOT NULL.

### Khóa ghép / unique: notification\_outbox: UNIQUE(user\_id,type,event\_key); index partial(available\_at,id) WHERE processed\_at IS NULL AND resolved\_at IS NULL AND (failed\_at IS NULL OR replay\_requested\_at > failed\_at). Operator ghi replay\_requested\_at, đặt lại attempt\_count/retry\_started\_at/available\_at trong transaction cùng audit; worker chỉ chọn lại row failed có mốc replay mới, không tạo row/event\_key mới.

### Ghi một outbox row mỗi recipient cùng transaction mutation/audit. Worker trong Docker lấy row đã đến hạn bằng FOR UPDATE SKIP LOCKED; insert notifications và đặt processed_at trong cùng transaction. Khi lỗi, rollback lần tạo notification rồi ghi attempt_count, last_error_code và available_at với backoff tăng dần. Dừng retry sau 10 lần hoặc 60 phút từ retry_started_at, tùy mốc nào đến trước; ghi failed_at, cảnh báo failed hoặc pending quá 5 phút. Replay vận hành giữ event_key cũ, cần quyền/reason/audit; nếu replay lại thất bại thì ghi failed_at mới và yêu cầu một replay_requested_at mới. Khi đã failed rồi xử lý xong, ghi resolved_at; giữ row tới resolved_at +90 ngày. Row chỉ processed, chưa từng failed giữ 30 ngày từ processed_at. Notification giữ 180 ngày; Audit giữ 24 tháng. Purge có kiểm soát và audit.

### Bảng audit\_logs

id — bigint; NOT NULL; PK.

actor\_id — bigint; NULL; FK; tham chiếu users.id.

action — varchar(100); NOT NULL.

resource\_type — varchar(40); NOT NULL.

resource\_id — bigint; NOT NULL.

before — jsonb; NULL.

after — jsonb; NULL.

reason — text; NULL.

request\_id — uuid; NULL.

created\_at — timestamptz; NOT NULL.

Khóa ghép / unique: notifications: UNIQUE(user\_id,type,event\_key).

Notification chỉ dùng in\_app; read\_at NULL là unread. UNIQUE(user\_id,type,event\_key) trên notifications và notification\_outbox chống trùng theo recipient/type/event. Audit append-only, bỏ secret và dữ liệu nhạy cảm không cần thiết; audit bắt buộc ghi cùng transaction nghiệp vụ. Notification chỉ chứa resource/deep link được phép; worker không cấp quyền đọc tài nguyên.

# 11 · Xác thực kỹ thuật

## password\_reset\_tokens  ·  sessions

### Bảng password\_reset\_tokens

email — varchar(255); NOT NULL; PK.

token — varchar(255); NOT NULL.

created\_at — timestamp; NULL.

### Bảng sessions

id — varchar(255); NOT NULL; PK.

user\_id — bigint; NULL.

ip\_address — varchar(45); NULL.

user\_agent — text; NULL.

payload — text; NOT NULL.

last\_activity — integer; NOT NULL.

sessions là phiên HTTP Laravel, khác lesson\_sessions là buổi học. sessions.user\_id chỉ được index trong migration hiện tại, không vẽ như FK thật. personal\_access\_tokens là bảng Sanctum hiện có; SPA cookie dùng sessions, không bắt buộc phát personal token. tokenable\_type/tokenable\_id là liên kết đa hình. password\_reset\_tokens.token lưu dạng hash, không lưu token thô; email liên kết logic với users.email.

# 11 · Xác thực kỹ thuật (tiếp)

## personal\_access\_tokens

### Bảng personal\_access\_tokens

id — bigint; NOT NULL; PK.

tokenable\_type — varchar(255); NOT NULL; LOG.

tokenable\_id — bigint; NOT NULL; LOG.

name — text; NOT NULL.

token — varchar(64); NOT NULL; UK.

abilities — text; NULL.

last\_used\_at — timestamp; NULL.

expires\_at — timestamp; NULL.

created\_at — timestamp; NULL.

updated\_at — timestamp; NULL.

# 12 · Cache và hàng đợi

## cache  ·  cache\_locks

### Bảng cache

key — varchar(255); NOT NULL; PK.

value — text; NOT NULL.

expiration — bigint; NOT NULL.

### Bảng cache\_locks

key — varchar(255); NOT NULL; PK.

owner — varchar(255); NOT NULL.

expiration — bigint; NOT NULL.

Các bảng Laravel vận hành: cache/cache\_locks, queue jobs, job\_batches, failed\_jobs và migrations. Không phải thực thể nghiệp vụ; chỉ được sử dụng nếu cấu hình driver tương ứng. Các mốc thời gian integer của queue/cache là Unix epoch; migrations.batch là đợt migration. Không đưa payload, exception hoặc token của các bảng này vào API công khai.

# 12 · Cache và hàng đợi (tiếp)

## jobs  ·  job\_batches

### Bảng jobs

id — bigint; NOT NULL; PK.

queue — varchar(255); NOT NULL.

payload — text; NOT NULL.

attempts — smallint; NOT NULL.

reserved\_at — integer; NULL.

available\_at — integer; NOT NULL.

created\_at — integer; NOT NULL.

### Bảng job\_batches

id — varchar(255); NOT NULL; PK.

name — varchar(255); NOT NULL.

total\_jobs — integer; NOT NULL.

pending\_jobs — integer; NOT NULL.

failed\_jobs — integer; NOT NULL.

failed\_job\_ids — text; NOT NULL.

options — text; NULL.

cancelled\_at — integer; NULL.

created\_at — integer; NOT NULL.

finished\_at — integer; NULL.

# 12 · Cache và hàng đợi (tiếp)

## failed\_jobs  ·  migrations

### Bảng failed\_jobs

id — bigint; NOT NULL; PK.

uuid — varchar(255); NOT NULL; UK.

connection — varchar(255); NOT NULL.

queue — varchar(255); NOT NULL.

payload — text; NOT NULL.

exception — text; NOT NULL.

failed\_at — timestamp; NOT NULL.

### Bảng migrations

id — integer; NOT NULL; PK.

migration — varchar(255); NOT NULL.

batch — integer; NOT NULL.

# 13 · Quy tắc liên kết và toàn vẹn

## Idempotency receipts dùng chung — schema BR-23, runtime Accept Invitation

### Bảng idempotency\_receipts — migration BE-D02

id — uuid; NOT NULL; PK.

actor\_id — bigint; NOT NULL; FK users.id.

idempotency\_key — uuid; NOT NULL.

method — varchar(10); NOT NULL; HTTP method chuẩn hóa.

canonical\_path — varchar(512); NOT NULL; path chuẩn hóa, không gồm query biến thiên.

request\_hash — char(64); NOT NULL; SHA-256 của payload đã canonicalize theo BR-23 hoặc BR-22.

response\_status — smallint; NOT NULL.

response\_snapshot — jsonb; NOT NULL; allow-list response đã trả, không chứa bí mật.

created\_at — timestamptz; NOT NULL.

expires\_at — timestamptz; NULL; command BR-23 có hạn 30 ngày, Fee Record theo BR-22 có thể NULL đến khi đủ điều kiện purge.

UNIQUE(actor\_id,idempotency\_key). Accept Invitation ghi receipt cùng mutation trong transaction; cùng actor/key + method/path/hash trả response snapshot cũ sau khi kiểm quyền hiện tại, payload khác trả 409. Các module khác chưa tích hợp bảng này. Fee Record cần tính hash/replay theo BR-22 và giữ receipt tới khi Fee Record cùng lịch sử liên quan được phép purge. Submission giữ key/hash trên task_submissions, Messaging giữ key/hash trên messages.

## Tham chiếu đa hình

file\_links.resource\_type \+ resource\_id trỏ logic tới messages, tasks, task\_submissions, announcements hoặc verification\_documents. reports dùng tutor\_profile/listing/message/program/review. audit\_logs tham chiếu tài nguyên lịch sử. Những cặp này không thể đặt một FK SQL chung; service phải kiểm tra đúng type, row tồn tại và quyền truy cập, rồi xử lý gắn/xóa trong transaction. Không vẽ chúng thành FK thật.

## Các quan hệ và CHECK cần bổ sung

UNIQUE pending Invitation chỉ theo program\_id. Giữ UNIQUE(program\_id,user\_id) và đổi unique Customer membership thành UNIQUE(program\_id) WHERE member\_role=customer, để một Program không có nhiều Customer lịch sử. Kiểm Customer đích/Customer member trùng programs.customer\_id; Tutor member trùng chủ Program. Cột role/status phải có CHECK đúng enum, không còn observer, pending Program hoặc expired Conversation.

Giá và số lượng không âm; version ≥1; rating 1..5; weekday 0..6; end\_time\>start\_time; planned\_ends\_at\>planned\_starts\_at. Review comment nếu có phải không trắng và tối đa 2.000 ký tự; Report details tối đa 2.000 ký tự, reason=`other` thì details bắt buộc không trắng. Chỉ VND; amount\_minor/price\_from\_minor/agreed\_fee\_minor là số nguyên đồng. expires\_at giấy tờ NULL khi không có hạn. Fee voided phải có voided\_at/voided\_by/void\_reason; hồ sơ suspended phải có status\_before\_suspension/suspended\_at/suspension\_reason hợp lệ.

Chỉ FK program\_id từ Invitation/Session tới programs.id; program\_version và snapshot là dữ liệu lịch sử bất biến. Không dùng FK(program\_id,program\_version) tới row Program hiện tại vì version của Program có thể tăng. Kiểm version tại thời điểm gửi/accept bằng khóa transaction.

## Chỉ mục và xóa dữ liệu

Giữ các index marketplace(status,specialization\_id,delivery\_mode,city,price\_from\_minor); inbox(invitee\_user\_id,status,expires\_at); message(conversation\_id,created\_at,id); task(program\_id,due\_at,status); notification(user\_id,read\_at,created\_at); report(status,created\_at); audit(resource\_type,resource\_id,created\_at). Đề xuất index mọi FK chưa được index bởi PK/UK; thêm submission(task\_id,submission\_version DESC), fee(program\_id,period\_start). Partial UNIQUE(reporter_id,resource_type,resource_id) trên reports WHERE status='open' bảo vệ một Report open mỗi reporter/resource; index review(tutor_profile_id,visibility,created_at DESC,id DESC), tutor_profile_revisions(tutor_profile_id,revision_no) UNIQUE và partial UNIQUE cho revision chưa đóng; idempotency_receipts(expires_at) đã được tạo để purge receipt có TTL.

Giữ lịch sử Program, Invitation, Submission, Fee, Review và Audit; không cascade hard delete vào các bảng này. Report/decision giữ 24 tháng từ handled_at; Report còn open không purge. Audit giữ 24 tháng từ created_at; Notification 180 ngày. Tài khoản ưu tiên status/soft delete. FK reviewer/actor có thể SET NULL khi chính sách xóa thật cho phép. File chỉ xóa object khi không còn liên kết và đã hết thời hạn lưu; một file\_links row không trao quyền đọc cho mọi người dùng. Audit lưu lại reason/time cho thay đổi dấu thanh toán, đình chỉ, khôi phục, truy cập evidence, replay outbox và quyết định Admin; payload audit không chứa nội dung riêng tư đầy đủ.

## Phạm vi triển khai

Không tạo bảng Order/Payment riêng: Program là thỏa thuận dịch vụ, fee\_records là phí tự khai báo và dấu đã/chưa thu do Tutor đặt; không có thanh toán qua cổng, hoàn tiền hoặc đối soát ngân hàng. Listing moderation hold đã có migration theo BR-15/M03-F10; hide/restore và support-ticket/appeal routes chưa triển khai. Admin users/metrics có policy tại BR-27; các route vẫn Dự kiến. Không tự thêm giá trị `hidden` vào ListingStatus.

## Tài liệu nguồn

Domain Model: [Domain Model](<../Domain Documentation/Domain Model.md>)

Enum Catalog: [Enum Catalog](<../Domain Documentation/Enum Catalog.md>)

Business Rules: [Business Rules](<../Domain Documentation/Business Rules.md>)

F03 — Cột phân công Admin (thiết kế đích)  
Các cột assignment đã được đưa vào bảng tutor\_profiles và reports ở trên. NULL assignee biểu thị chưa phân công; reviewed\_by và handled\_by tiếp tục chỉ người ra quyết định. Assignment đổi cùng audit, tăng assignment\_version; không thêm bảng lịch sử assignment riêng. Index hàng đợi theo (status, assigned\_admin\_id, submitted\_at) cho tutor\_profiles và (status, assigned\_admin\_id, created\_at) cho reports. Đây là thiết kế tài liệu, chưa phải migration đã chạy.
