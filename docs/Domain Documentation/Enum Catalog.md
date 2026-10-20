**TutorLink Project Enum Catalog**

## **1 Tài khoản và hồ sơ**

**01 UserRole**

* customer \= Khách hàng;   
* tutor \= Gia sư;   
* admin \= Quản trị viên (Admin không đăng ký công khai)

**02 RegisterRole**

* customer \= Khách hàng;  
* tutor \= Gia sư

**03 UserStatus**

* active \= Tài khoản đang hoạt động;   
* suspended: Tài khoản bị đình chỉ, không được đăng nhập hoặc thực hiện thao tác cần tài khoản hoạt động  
* deactivated: Tài khoản đã bị vô hiệu hóa theo một quy trình riêng, nếu hệ thống có hỗ trợ  

**04 TutorProfileStatus**

* draft \= Nháp;   
* submitted \= Đã gửi, chờ xét duyệt ;   
* changes\_requested \= Yêu cầu chỉnh sửa ;   
* approved \= Được duyệt;   
* rejected \= Từ chối  
* suspended= Đình chỉ hồ sơ  

**05 EmailVerificationState**

* unverified \= Chưa xác minh;   
* verified \= Đã xác minh

## **2 Xác minh và marketplace**

**06 VerificationDocumentType**

* identity\_card \= Căn cước;   
* passport \= Hộ chiếu;   
* degree \= Bằng cấp;   
* certificate \= Chứng chỉ;   
* other \= Khác

**07 VerificationDocumentStatus**

* pending \= Chưa duyệt;   
* approved \= Chấp nhận;   
* rejected \= Từ chối;   
* expired \= Hết hiệu lực

**08 CatalogStatus**

* active \= Hiển thị;   
* inactive \= Tạm ẩn

**09 DeliveryMode**

* online \= Trực tuyến;   
* offline \= Trực tiếp;   
* hybrid \= Kết hợp

**10 PriceUnit**

* session \= Theo buổi;   
* hour \= Theo giờ;   
* course \= Theo khóa

**11 Currency**

* VND \= Việt Nam đồng

**12 Weekday**

* 0 \= Monday;   
* 1 \= Tuesday;   
* 2 \= Wednesday;   
* 3 \= Thursday;   
* 4 \= Friday;  
* 5 \= Saturday;   
* 6 \= Sunday;

## 3 Listing

**13 ListingStatus**

* draft \= Nháp;   
* active \= Đang đăng;   
* inactive \= Tạm dừng;   
* archived \= Lưu trữ

**14 ListingAction**

* edit \= Chỉnh sửa;   
* publish \= Đăng/đăng lại;   
* pause \= Tạm dừng;   
* archive \= Lưu trữ

**15 ListingStatusFilter**

* draft \= Nháp;   
* active \= Đang đăng;   
* inactive \= Tạm dừng;   
* archived \= Lưu trữ

## **4 Hội thoại và tin nhắn**

**16 ConversationStatus**

* active \= Hoạt động;   
* cancelled \= Đã đóng (sau khi completed service đó); 

**17 MessageType**

* text \= Văn bản;  
* file \= Tệp;   
* system \= Sự kiện server (tin do backend tạo)

**18 MessageSendViewState**

* sending \= Đang gửi;  
* sent \= Server đã lưu;   
* failed \= Thất bại

**19 MessageReadViewState**

* unread \= Chưa đọc;   
* read \= Đã đọc

## **5 Program và Invitation**

**20 ProgramStatus**

* draft \= Chưa kích hoạt;   
* active \= Đang thực hiện;   
* paused \= Tạm dừng;   
* completed \= Hoàn thành;   
* cancelled \= Đã hủy

**21 InvitationStatus**

* pending \= Chờ phản hồi;   
* accepted \= Chấp nhận;   
* rejected \= Từ chối;   
* cancelled \= Thu hồi;   
* expired \= Hết hạn

**22 ProgramMemberRole**

* customer \= Khách hàng;   
* tutor \= Gia sư; 

**23 ProgramMemberStatus**

* active \= Quan hệ hợp lệ;  
* cancelled \= Thu hồi;   
* expired \= Hết hiệu lực cũ;

**24 ProgramAction**

* edit \= Sửa Draft;   
* send\_invitation \= Gửi lời mời;   
* pause \= Tạm dừng;   
* resume \= Tiếp tục;   
* complete \= Hoàn thành;   
* cancel \= Hủy

**25 InvitationAction**

* accept \= Chấp nhận;   
* reject \= Từ chối;   
* cancel \= Thu hồi

## **6 Session và nhiệm vụ**

**26 SessionStatus**

* scheduled \= Lên lịch;   
* completed \= Hoàn tất;   
* cancelled \= Đã hủy

**27 TaskStatus**

* todo \= Chưa bắt đầu;   
* in\_progress \= Đang làm;   
* submitted \= Đã nộp;   
* completed \= Hoàn thành;   
* cancelled \= Đã hủy;

**28 SubmissionStatus**

* submitted \= Chờ duyệt;   
* accepted \= Đã chấp nhận;   
* revision\_requested \= Yêu cầu sửa

**29 TaskAction**

* edit \= Sửa nhiệm vụ;   
* start \= Bắt đầu;   
* submit \= Nộp/cập nhật;   
* review \= Chấp nhận bài;   
* request\_revision \= Yêu cầu sửa;   
* cancel \= Hủy

## **7 Phí và nội dung workspace**

**30 FeeRecordStatus**

* declared \= đã khai báo;   
* voided \= đã vô hiệu hóa

**31 TutorPaymentMark**

* marked\_paid \= Tutor đánh dấu đã thu;   
* unmarked \= chưa đánh dấu   
* marked\_unpaid \= Tutor đánh dấu chưa thu; 

**32 FeeAction**

* edit \= Sửa phí;   
* mark\_paid \= Đã thu;   
* mark\_unpaid \= Chưa thu;   
* void \= Vô hiệu bản ghi sai

**33 AnnouncementViewState**

* draft \= Chưa công bố;   
* published \= Công bố

## **8 File và liên kết tài nguyên**

**34 FilePurpose**

* avatar \= Ảnh đại diện;  
* verification\_document \= Tài liệu xác minh;   
* task\_attachment \= Tệp giao bài;   
* profile\_document \= Tài liệu hồ sơ riêng;   
* task\_submission \= Tệp bài nộp;   
* message\_attachment \= Tệp chat;   
* announcement\_attachment \= Tệp thông báo

**35 FileVisibility**

* private \= Riêng tư theo policy;   
* public \= Công khai có điều kiện

**36 FileScanStatus**

* pending \= Chờ kiểm tra hoàn tất;   
* clean \= Đạt kiểm tra hiện tại;   
* rejected \= Từ chối

**37 FileResourceType**

* message \= Tin nhắn;   
* task \= Nhiệm vụ;   
* task\_submission \= Bài nộp;   
* announcement \= Thông báo;   
* verification\_document \= Tài liệu xác minh

**38 FileLinkPurpose**

* attachment \= Tệp nội dung;   
* evidence \= Tài liệu chứng minh

## **9 Review và Report**

**39 ReviewVisibility**

* visible \= Công khai;   
* hidden \= Bị ẩn

**40 ReportStatus**

* open \= Chờ xử lý;   
* resolved \= Đã xử lý vi phạm;   
* dismissed \= Bác bỏ

**41 ReportResourceType**

* tutor\_profile \= Hồ sơ Tutor;   
* listing \= Listing;   
* message \= Tin nhắn;   
* program \= Program;   
* review \= Đánh giá

**42 ReportReasonCode**

* spam \= Spam;   
* harassment \= Quấy rối;   
* inappropriate\_content \= Nội dung không phù hợp;   
* fraud\_suspected \= Nghi lừa đảo;   
* misleading\_information \= Thông tin sai;   
* privacy\_violation \= Xâm phạm riêng tư;   
* other \= Khác

## 10 Notification và sự kiện

**43 NotificationType**

* tutor\_profile\_submitted \= Gửi hồ sơ;   
* tutor\_profile\_approved \= Duyệt hồ sơ;   
* tutor\_profile\_changes\_requested \= Yêu cầu sửa hồ sơ;  
* tutor\_profile\_rejected \= Từ chối hồ sơ;  
* tutor\_profile\_suspended \= Đình chỉ hồ sơ;  
* tutor\_profile\_restored \= Restore hồ sơ;  
     
* invitation\_received \= Nhận lời mời;   
* invitation\_accepted \= Accept;   
* invitation\_rejected \= Reject;   
* invitation\_cancelled \= Thu hồi;   
* invitation\_expired \= Hết hạn;   
    
* task\_assigned \= Giao nhiệm vụ;   
* task\_submitted \= Nộp/cập nhật bài;   
* task\_feedback\_received \= Feedback chấp nhận;   
* task\_revision\_requested \= Yêu cầu sửa bài;   
    
* announcement\_published \= Công bố thông báo;   
* program\_paused \= Pause Program;   
* program\_resumed \= Resume;   
* program\_completed \= Hoàn thành Program;   
* program\_cancelled \= Hủy Program;   
    
* report\_resolved \= Report đã xử lý;   
* report\_dismissed \= Report bị bác

**44 NotificationReadState**

* unread \= Chưa đọc;   
* read \= Đã đọc

**45 NotificationChannel**

* in\_app \= Trong ứng dụng

**46 AuthEmailType**

* verify\_email \= Xác minh email;   
* reset\_password \= Đặt lại mật khẩu

## **11 Permission và capability**

**47 AdminPermission**

* admin.tutor\_profiles.view \= Xem hồ sơ;   
* admin.tutor\_profiles.decide \= Duyệt hồ sơ;   
* admin.verification\_documents.download \= Tải giấy tờ;   
* admin.tutor\_profiles.suspend \= Suspend/restore hồ sơ;   
* admin.accounts.suspend \= Suspend/restore account;   
* admin.reports.view \= Xem report/evidence theo scope;   
* admin.reports.decide \= Quyết định report;   
* admin.reviews.moderate \= Ẩn/hiện review;   
* admin.audit\_logs.view \= Đọc audit theo scope  
* admin.tutor\_profiles.assign \= Giao/chuyển/thu hồi người phụ trách hồ sơ Tutor;   
* admin.reports.assign \= Giao/chuyển/thu hồi người phụ trách Report;

**48 AdminTutorProfileAction**

* approve \= Duyệt hồ sơ;   
* request-changes \= Yêu cầu sửa hồ sơ;   
* reject \= Từ chối hồ sơ;  
* profile-suspend \= Đình chỉ hồ sơ;  
* profile-restore \= Khôi phục hồ sơ;  
* account-suspend \= Đình chỉ tài khoản;   
* account-restore \= Khôi phục tài khoản

**49 SessionAction**

* edit \= Sửa lịch;   
* complete \= Hoàn tất;   
* cancel \= Hủy

**50 AnnouncementAction**

* edit \= Sửa nội dung;   
* publish \= Công bố

**51 ProgressAction**

* edit \= Sửa tiến độ

F03 — Bổ sung AdminPermission  
admin.tutor\_profiles.assign \= Giao/chuyển/thu hồi người phụ trách hồ sơ Tutor; chỉ cho phép xem metadata tối thiểu của hàng đợi phân công.  
admin.reports.assign \= Giao/chuyển/thu hồi người phụ trách Report; không cho phép xem evidence hoặc quyết định Report nếu thiếu quyền riêng.
