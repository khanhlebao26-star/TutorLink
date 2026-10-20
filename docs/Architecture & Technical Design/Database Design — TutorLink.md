# Database Design — TutorLink

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

terms\_version — varchar(32); NULL.

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

status\_before\_suspension — varchar(20); NULL; đề xuất thêm.

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

status — varchar(20); NOT NULL; mặc định: pending; enum: VerificationDocumentStatus; cần điều chỉnh.

reviewed\_by — bigint; NULL; FK; tham chiếu users.id.

reviewed\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

expires\_at — timestamptz; NULL; đề xuất thêm.

Khóa ghép / unique: verification\_documents: UNIQUE(tutor\_profile\_id,file\_id).

files chỉ lưu metadata, nội dung nằm ở object storage. purpose bắt buộc với upload mới; visibility chỉ private/public. Chỉ file scan\_status=clean và đúng owner/purpose mới được gắn vào tài nguyên. expires\_at được đề xuất để xác định giấy tờ hết hiệu lực. Avatar tham chiếu trực tiếp bằng avatar\_file\_id; profile\_document không tự bổ sung FileResourceType mới.

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

# 04 · Hội thoại và tin nhắn

### Bảng messages

id — bigint; NOT NULL; PK.

conversation\_id — bigint; NOT NULL; FK; tham chiếu conversations.id.

sender\_id — bigint; NOT NULL; FK; tham chiếu users.id.

message\_type — varchar(20); NOT NULL; mặc định: text; enum: MessageType.

body — text; NULL.

client\_message\_id — uuid; NOT NULL.

metadata — jsonb; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Khóa ghép / unique: messages: UNIQUE(sender\_id,client\_message\_id).

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

timezone — varchar(64); NOT NULL; mặc định: Asia/Ho\_Chi\_Minh.

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

Khóa ghép / unique: programs: UNIQUE(id,version); program\_invitations: UNIQUE(invited\_by,idempotency\_key).

programs.customer\_id là Customer đích cố định; owner\_tutor\_profile\_id là Tutor duy nhất. Invitation giữ snapshot bất biến và expires\_at \= thời điểm gửi \+ 72 giờ. Thay index pending cũ bằng UNIQUE(program\_id) WHERE status=pending; hết hạn phải chuyển expired trước khi gửi mới. Accept kiểm version/snapshot, tạo hai membership và chuyển Program active trong một transaction. Duy nhất một Customer member trong suốt Program; không tự thu hồi membership khi completed.

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

timezone — varchar(64); NOT NULL.

delivery\_mode — varchar(20); NOT NULL; enum: DeliveryMode.

location\_text — text; NULL.

meeting\_url — text; NULL.

status — varchar(20); NOT NULL; mặc định: scheduled; enum: SessionStatus; cần điều chỉnh.

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

# 07 · Nhiệm vụ và bài nộp

## tasks  ·  task\_submissions

### Bảng tasks

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

title — varchar(180); NOT NULL.

description — text; NULL.

due\_at — timestamptz; NULL.

status — varchar(20); NOT NULL; mặc định: todo; enum: TaskStatus.

assigned\_to — bigint; NULL; FK; tham chiếu users.id.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

current\_submission\_id — bigint; NULL; FK; tham chiếu task\_submissions.id; đề xuất thêm.

### Bảng task\_submissions

id — bigint; NOT NULL; PK.

task\_id — bigint; NOT NULL; FK; tham chiếu tasks.id.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id.

content — text; NULL.

status — varchar(20); NOT NULL; mặc định: submitted; enum: SubmissionStatus; cần điều chỉnh.

submitted\_at — timestamptz; NOT NULL.

feedback — text; NULL.

reviewed\_by — bigint; NULL; FK; tham chiếu users.id.

reviewed\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

submission\_version — integer; NOT NULL; đề xuất thêm.

idempotency\_key — uuid; NOT NULL; F05; khóa chống gửi trùng cùng version.

request\_hash — char(64); NOT NULL; F05; SHA-256 request đã chuẩn hóa; không lưu bản sao nội dung riêng tư.

Khóa ghép / unique: task\_submissions: UNIQUE(task\_id,customer\_id,submission\_version); UNIQUE(task\_id,customer\_id,idempotency\_key).

Mỗi lần nộp/cập nhật chủ động tạo một task\_submissions mới: content, tệp và feedback của version cũ được bảo toàn; submission\_version tăng từ 1\. tasks.current\_submission\_id chỉ tới version mới nhất của chính Task và Customer được giao. Insert, cập nhật pointer, Task state và file links thực hiện trong cùng transaction. PUT bắt buộc Idempotency-Key UUID; lưu key và SHA-256 request\_hash đã validation/canonicalize trên đúng version, giữ theo lịch sử submission (không hết hạn khi version còn lưu). UNIQUE(task\_id,customer\_id,idempotency\_key) giới hạn key theo người nộp và Task. Sau khi kiểm tra quyền/assignee, cùng key \+ hash trả đúng version đã commit mà không tăng version/audit/notification; cùng key nhưng hash khác trả 409\. Key mới chỉ tạo version nếu expected\_submission\_version khớp version hiện tại; stale trả 409\. Feedback chỉ ghi cho version mới nhất và kiểm expected\_submission\_version; reviewed\_by/reviewed\_at/feedback gắn vào đúng row version.

# 08 · Tiến độ và thông báo lớp

## progress\_records  ·  announcements

### Bảng progress\_records

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

customer\_id — bigint; NOT NULL; FK; tham chiếu users.id.

metric\_name — varchar(100); NOT NULL.

value — numeric(12,4); NOT NULL.

note — text; NULL.

recorded\_by — bigint; NOT NULL; FK; tham chiếu users.id.

recorded\_at — timestamptz; NOT NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

### Bảng announcements

id — bigint; NOT NULL; PK.

program\_id — bigint; NOT NULL; FK; tham chiếu programs.id.

author\_id — bigint; NOT NULL; FK; tham chiếu users.id.

title — varchar(180); NOT NULL.

body — text; NOT NULL.

published\_at — timestamptz; NULL.

created\_at — timestamptz; NULL.

updated\_at — timestamptz; NULL.

Progress lưu metric\_name/value/note; không thêm status. customer\_id phải là Customer của Program; recorded\_by là Tutor có quyền sửa. Announcement draft/published suy ra từ published\_at; bản nháp không hiển thị với Customer. Chỉ thành viên phù hợp của Program được đọc nội dung và tệp workspace.

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

voided\_at — timestamptz; NULL; đề xuất thêm.

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

Fee Record có hai enum độc lập: status=declared/voided và tutor\_payment\_mark=unmarked/marked\_paid/marked\_unpaid. Kỳ tháng lưu period\_start=ngày đầu tháng, period\_end=ngày cuối cùng cùng tháng; API suy ra YYYY-MM. UNIQUE(program\_id,period\_start) áp dụng cả voided, không tái khai báo cùng kỳ. Số tiền VND nguyên ≥0; void giữ dấu thanh toán và là trạng thái cuối. Mỗi sửa phí/dấu thanh toán/void ghi Audit với lý do và thời gian. Review chỉ tạo cho Program completed hợp lệ, rating 1..5; visibility quyết định công khai. Report dùng open/resolved/dismissed.

# 09 · Phí, đánh giá và xử lý vi phạm (tiếp)

## reports

### Bảng reports

id — bigint; NOT NULL; PK.

reporter\_id — bigint; NOT NULL; FK; tham chiếu users.id.

resource\_type — varchar(40); NOT NULL; enum: ReportResourceType.

resource\_id — bigint; NOT NULL.

reason — varchar(100); NOT NULL; enum: ReportReasonCode.

details — text; NULL.

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

### available\_at — timestamptz; NOT NULL; thời điểm worker được thử lại.

### processed\_at — timestamptz; NULL.

### failed\_at — timestamptz; NULL.

### last\_error\_code — varchar(80); NULL; mã an toàn, không lưu raw exception.

### created\_at — timestamptz; NOT NULL.

### Khóa ghép / unique: notification\_outbox: UNIQUE(user\_id,type,event\_key); index partial(available\_at,id) WHERE processed\_at IS NULL AND failed\_at IS NULL.

### Ghi một outbox row mỗi recipient cùng transaction mutation/audit. Worker trong Docker lấy row đã đến hạn bằng FOR UPDATE SKIP LOCKED; insert notifications và đặt processed\_at trong cùng transaction. Khi lỗi, rollback lần tạo notification rồi ghi attempt\_count, last\_error\_code và available\_at với backoff tăng dần. Ngưỡng tối đa 10 lần hoặc 60 phút hiện là đề xuất cần nhóm chốt; sau ngưỡng, ghi failed\_at để vận hành kiểm tra/replay.

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

## Tham chiếu đa hình

file\_links.resource\_type \+ resource\_id trỏ logic tới messages, tasks, task\_submissions, announcements hoặc verification\_documents. reports dùng tutor\_profile/listing/message/program/review. audit\_logs tham chiếu tài nguyên lịch sử. Những cặp này không thể đặt một FK SQL chung; service phải kiểm tra đúng type, row tồn tại và quyền truy cập, rồi xử lý gắn/xóa trong transaction. Không vẽ chúng thành FK thật.

## Các quan hệ và CHECK cần bổ sung

UNIQUE pending Invitation chỉ theo program\_id. Giữ UNIQUE(program\_id,user\_id) và đổi unique Customer membership thành UNIQUE(program\_id) WHERE member\_role=customer, để một Program không có nhiều Customer lịch sử. Kiểm Customer đích/Customer member trùng programs.customer\_id; Tutor member trùng chủ Program. Cột role/status phải có CHECK đúng enum, không còn observer, pending Program hoặc expired Conversation.

Giá và số lượng không âm; version ≥1; rating 1..5; weekday 0..6; end\_time\>start\_time; planned\_ends\_at\>planned\_starts\_at. Chỉ VND; amount\_minor/price\_from\_minor/agreed\_fee\_minor là số nguyên đồng. expires\_at giấy tờ NULL khi không có hạn. Fee voided phải có voided\_at/voided\_by/void\_reason; hồ sơ suspended phải có status\_before\_suspension/suspended\_at/suspension\_reason hợp lệ.

Chỉ FK program\_id từ Invitation/Session tới programs.id; program\_version và snapshot là dữ liệu lịch sử bất biến. Không dùng FK(program\_id,program\_version) tới row Program hiện tại vì version của Program có thể tăng. Kiểm version tại thời điểm gửi/accept bằng khóa transaction.

## Chỉ mục và xóa dữ liệu

Giữ các index marketplace(status,specialization\_id,delivery\_mode,city,price\_from\_minor); inbox(invitee\_user\_id,status,expires\_at); message(conversation\_id,created\_at,id); task(program\_id,due\_at,status); notification(user\_id,read\_at,created\_at); report(status,created\_at); audit(resource\_type,resource\_id,created\_at). Đề xuất index mọi FK chưa được index bởi PK/UK; thêm submission(task\_id,submission\_version DESC), fee(program\_id,period\_start).

Giữ lịch sử Program, Invitation, Submission, Fee, Review và Audit; không cascade hard delete vào các bảng này. Tài khoản ưu tiên status/soft delete. FK reviewer/actor có thể SET NULL khi chính sách xóa thật cho phép. File chỉ xóa object khi không còn liên kết và đã hết thời hạn lưu; một file\_links row không trao quyền đọc cho mọi người dùng. Audit lưu lại reason/time cho thay đổi dấu thanh toán, đình chỉ, khôi phục và quyết định Admin.

## Phạm vi triển khai

Không tạo bảng Order/Payment riêng: Program là thỏa thuận dịch vụ, fee\_records là phí tự khai báo và dấu đã/chưa thu do Tutor đặt; không có thanh toán qua cổng, hoàn tiền hoặc đối soát ngân hàng. Không thêm moderation riêng từng Listing, support ticket hoặc metrics Admin khi các chức năng đó còn điều kiện trong API Route Catalog. Không tự thêm enum vào catalog trong tài liệu này.

## Tài liệu nguồn

Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw

Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY

Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs

F03 — Cột phân công Admin (thiết kế đích)  
Thêm vào tutor\_profiles và reports: assigned\_admin\_id — bigint NULL FK users.id (Admin hiện phụ trách); assigned\_by — bigint NULL FK users.id (Admin đã giao/chuyển/thu hồi gần nhất); assigned\_at — timestamptz NULL (thời điểm thay đổi assignment gần nhất); assignment\_version — integer NOT NULL DEFAULT 0 (tăng 1 mỗi lần giao, chuyển hoặc thu hồi; dùng optimistic concurrency). NULL assignee biểu thị chưa phân công. reviewed\_by và handled\_by tiếp tục chỉ người ra quyết định, không thay thế assigned\_admin\_id. Dùng audit\_logs hiện có để ghi before/after assignee, actor\_id, reason, resource, version và created\_at trong cùng transaction; không thêm bảng assignment riêng. Bổ sung index hàng đợi theo (status, assigned\_admin\_id, submitted\_at) cho tutor\_profiles và (status, assigned\_admin\_id, created\_at) cho reports. Đây là thiết kế tài liệu, chưa phải migration đã chạy.
