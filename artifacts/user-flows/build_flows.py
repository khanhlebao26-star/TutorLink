"""Editable draw.io maps and previews generated from the same geometry."""
from pathlib import Path
from xml.etree import ElementTree as ET
from html import escape
import json
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).resolve().parent
W, H = 2400, 2150
X = [80 + 330 * i for i in range(7)]
COLORS = {
    'page': ('#FFFFFF', '#CAD4E2'), 'action': ('#F4EFFF', '#A98BCF'),
    'external': ('#EAF3FF', '#82ACE0'), 'success': ('#E8F7F0', '#71B69D'),
    'decision': ('#FFF5DE', '#D1AE5C'), 'proposal': ('#FFF0DC', '#D49A4C'),
    'muted': ('#F3F5F9', '#B8C2D2'),
}
NORMAL = 'C:/Windows/Fonts/arial.ttf'
BOLD = 'C:/Windows/Fonts/arialbd.ttf'

class Page:
    def __init__(self, ident, name, title, subtitle, color):
        self.ident, self.name, self.title, self.subtitle, self.color = ident, name, title, subtitle, color
        self.nodes, self.edges, self.bands = [], [], []
    def band(self, y, height, title, subtitle=''):
        self.bands.append((y, height, title, subtitle))
    def node(self, ident, col, y, title, sub='', kind='page', h=100, x=None, width=260):
        self.nodes.append(dict(id=ident, x=X[col] if x is None else x, y=y, w=width, h=h, title=title, sub=sub, kind=kind))
        return ident
    def edge(self, a, b, label='', start='right', end='left', points=None, dashed=False):
        self.edges.append(dict(a=a, b=b, label=label, start=start, end=end, points=points or [], dashed=dashed))
    def chain(self, ids):
        for a,b in zip(ids, ids[1:]): self.edge(a,b)
    def notes(self, entries):
        self.band(1870, 225, 'RÀ SOÁT ĐỂ GIẢM TRANG / GIẢM CÔNG THIẾT KẾ', 'Màu cam là đề xuất, chưa thay đổi chức năng trong dự án.')
        for i,(title, sub) in enumerate(entries):
            self.node(f'note-{i}',0,1940,title,sub,'proposal',h=132,x=80+i*755,width=725)
    def utilities(self, items, y=1705):
        self.band(y-55, 175, 'TIỆN ÍCH DÙNG CHUNG', 'Lối vào từ header, avatar hoặc menu tài khoản; không vẽ lặp vào từng bước chính.')
        for i,(title,route) in enumerate(items): self.node(f'util-{i}',0,y+30,title,route,'muted',h=88,x=80+i*455,width=425)

t = Page('ASlQo1yBcWQfyRQkByOP','01 · Tutor','TUTOR / COACH — TỪ HỒ SƠ ĐẾN GIẢNG DẠY', 'Luồng hiện tại trong code · Frontend :3000 · Điểm giao với học viên / admin được tô xanh.', '#8156C7')
t.band(175,390,'01  TÀI KHOẢN & ĐIỀU KIỆN GIẢNG DẠY','Giấy tờ tùy cấu hình demo; production yêu cầu giấy tờ hợp lệ. Admin duyệt hồ sơ, không duyệt từng dịch vụ qua một trang riêng.')
for ident,col,title,sub,kind in [
 ('login',0,'Đăng ký / Đăng nhập','/auth/register · /auth/login\nVai trò Tutor, đủ 18 tuổi','page'),
 ('email',1,'Xác minh email','/auth/verify-email','page'),
 ('profile',2,'Hoàn thiện hồ sơ Tutor','/profile · 5 bước\nCơ bản → Chuyên môn → Kinh nghiệm\n→ Tài liệu → Xem lại','page'),
 ('submit',3,'Gửi hồ sơ xét duyệt','Hồ sơ chuyển sang submitted','action'),
 ('review',4,'Admin phê duyệt?','Giao với sơ đồ Admin','decision'),
 ('approved',5,'Hồ sơ được duyệt','approved · không bị đình chỉ','success'),
 ('eligible',6,'Đủ điều kiện hoạt động','Tài khoản active + email xác minh\n+ hồ sơ approved\n+ giấy tờ nếu cấu hình yêu cầu','success')]: t.node(ident,col,270,title,sub,kind)
t.chain(['login','email','profile','submit','review','approved','eligible'])
t.node('changes',4,425,'Yêu cầu chỉnh sửa','changes_requested\nĐọc lý do → sửa hồ sơ → gửi lại','muted',h=82)
t.node('rejected',5,425,'Từ chối / đình chỉ hồ sơ','Theo trạng thái và quyền backend\nKhông mặc định cho sửa / gửi lại','muted',h=82)
t.edge('review','changes','Chưa duyệt','bottom','top')
t.edge('review','rejected','Từ chối / đình chỉ','bottom','top',[(1530,400),(1860,400)])
t.edge('changes','profile','Sửa & gửi lại','left','bottom',[(990,466),(990,400),(870,400)],dashed=True)

t.band(600,390,'02  ĐĂNG DỊCH VỤ & CÔNG KHAI TRÊN MARKETPLACE','Một mục menu “Quản lý giảng dạy”, gồm hai tab Dịch vụ / Chương trình học. Đây là hai nghiệp vụ khác nhau.')
for ident,col,title,sub,kind in [
 ('manage',0,'Quản lý giảng dạy','Một mục menu dùng chung','action'),
 ('listings',1,'Tab Dịch vụ','/me/listings\nLọc nháp / active / paused / archived','page'),
 ('new-listing',2,'Tạo / sửa dịch vụ','/me/listings/new\n/me/listings/{id}/edit','page'),
 ('listing-draft',3,'Lưu bản nháp dịch vụ','Chưa xuất hiện với học viên','muted'),
 ('publish',4,'Công khai dịch vụ','Theo allowed_actions\nKiểm tra điều kiện tutor / chuyên môn','action'),
 ('market',5,'Học viên thấy dịch vụ','/marketplace\n/marketplace/{slug}','external'),
 ('availability',6,'Lịch khả dụng tham khảo','/me/availability\nHiển thị trên hồ sơ công khai','page')]: t.node(ident,col,685,title,sub,kind)
t.chain(['manage','listings','new-listing','listing-draft','publish','market'])
t.node('own-detail',3,840,'Chi tiết dịch vụ của tôi','/me/listings/{id}\nXem, sửa, tạm dừng, lưu trữ','page',h=90)
t.edge('listing-draft','own-detail','','bottom','top')
t.edge('own-detail','new-listing','Chỉnh sửa','left','bottom',[(1000,885),(1000,815),(870,815)],dashed=True)
t.edge('manage','availability','Nhánh quản lý thời gian','bottom','bottom',[(210,965),(2190,965)],dashed=True)

t.band(1020,390,'03  TRAO ĐỔI → TẠO CHƯƠNG TRÌNH → GỬI LỜI MỜI','Dịch vụ là nội dung chào bán; chương trình là thỏa thuận riêng với một học viên. Hiện lưu bản nháp xong sẽ về trang chương trình.')
for ident,col,title,sub,kind in [
 ('messages',0,'Trao đổi với học viên','/messages → /messages/{id}\nChọn người đã có cuộc trò chuyện','page'),
 ('new-program',1,'Tạo chương trình riêng','/programs/new?customer_id=…\nChọn học viên, mục tiêu, lịch, học phí','page'),
 ('program-draft',2,'Lưu & xem bản nháp','/programs/{id}\nChưa kích hoạt workspace học tập','muted'),
 ('send-invite',3,'Chỉnh sửa & gửi lời mời','/programs/{id}/edit\nGửi từ bản đã lưu, chưa bị sửa tiếp','action'),
 ('invite-detail',4,'Theo dõi lời mời','/invitations/{id}\nTutor có thể hủy khi đang chờ','page'),
 ('accepted',5,'Học viên chấp nhận?','Chấp nhận → kích hoạt chương trình','decision'),
 ('workspace',6,'Workspace chương trình','/programs/{id}\nTab Chương trình học: /programs','success')]: t.node(ident,col,1105,title,sub,kind)
t.chain(['messages','new-program','program-draft','send-invite','invite-detail','accepted','workspace'])
t.node('invite-failure',5,1260,'Từ chối / hết hạn / đã hủy','Không kích hoạt; trao đổi lại\nvà gửi lời mời mới khi phù hợp','muted',h=90)
t.edge('accepted','invite-failure','Không','bottom','top')
t.edge('manage','new-program','Tab Chương trình học /programs','bottom','top',[(210,1005),(540,1005)],dashed=True)

t.band(1440,190,'04  WORKSPACE — CÁC TAB DÙNG CHUNG MỘT LAYOUT','Tutor quản lý nội dung; quyền thay đổi dựa trên trạng thái chương trình. Có tạm dừng / tiếp tục / hoàn thành / hủy theo quyền.')
for i,(ident,title,sub) in enumerate([
 ('overview','Tổng quan','/programs/{id}'),('sessions','Buổi học','/programs/{id}/sessions\nTạo / hoàn thành / hủy'),
 ('tasks','Bài tập & phản hồi','/programs/{id}/tasks\nGiao bài, chấm / yêu cầu sửa'),('progress','Tiến độ','/programs/{id}/progress\nGhi nhận kết quả'),
 ('announcements','Thông báo chương trình','/programs/{id}/announcements\nSoạn / đăng'),('fees','Học phí','/programs/{id}/fees\nGhi nhận, đánh dấu đã trả')]):
 t.node(ident,0,1510,title,sub,'page',h=88,x=80+i*380,width=340)
t.edge('workspace','fees','Các tab workspace','bottom','top',[(2190,1420),(2150,1420)],dashed=True)
t.utilities([('Tin nhắn','/messages'),('Thông báo tài khoản','/notifications · icon chuông'),('Báo cáo của tôi','/reports'),('Cài đặt / đăng xuất','/settings · avatar'),('Hỗ trợ','/support')])
t.notes([
 ('GIỮ: Dịch vụ ≠ Chương trình','Đã gộp dưới Quản lý giảng dạy. Không nên xóa chương trình: đó là nơi quản lý học viên và việc học thực tế.'),
 ('CÂN NHẮC GỘP: chi tiết dịch vụ của tôi','/me/listings/{id} có thể thành drawer / preview trong danh sách; giữ trang chỉnh sửa. Hiện vẫn là một route riêng.'),
 ('GIỮ: khả dụng ≠ buổi học','/me/availability là lịch tham khảo. /programs/{id}/sessions là lịch buổi học cụ thể, có trạng thái thực hiện.')])

s = Page('tutorlink-student-flow','02 · Học viên','HỌC VIÊN — TỪ TÌM DỊCH VỤ ĐẾN HOÀN THÀNH', 'Luồng hiện tại trong code · Có thể xem marketplace trước khi đăng nhập · Không có checkout / đặt lịch thanh toán tự động.', '#2C8DAD')
s.band(175,390,'01  KHÁM PHÁ & KẾT NỐI','Danh mục trên header lọc marketplace; hồ sơ tutor tập trung tổng quan, dịch vụ, đánh giá và lịch khả dụng.')
for ident,col,title,sub,kind in [
 ('home',0,'Trang chủ','/ · danh mục / dịch vụ nổi bật','page'),('market',1,'Tìm & lọc dịch vụ','/marketplace\nDanh mục, chuyên môn, hình thức, giá','page'),
 ('detail',2,'Chi tiết dịch vụ','/marketplace/{slug}\nNội dung, lịch tham khảo, review','page'),('tutor',3,'Hồ sơ tutor công khai','/tutors/{slug}\n?tab=overview / services / reviews / schedule','page'),
 ('message-modal',4,'Popup liên hệ tutor','Viết tin nhắn → gửi\nHai bên phải khác vai trò','action'),('message',5,'Trao đổi trực tiếp','/messages/{id}\nThống nhất nhu cầu, lịch và mức phí','page'),('tutor-invite',6,'Tutor tạo & gửi chương trình','Giao với sơ đồ Tutor','external')]: s.node(ident,col,270,title,sub,kind)
s.chain(['home','market','detail','tutor','message-modal','message','tutor-invite'])
s.node('favorite',3,425,'Lưu tutor yêu thích','/favorites → xem lại hồ sơ / dịch vụ','page',h=85)
s.edge('tutor','favorite','Tùy chọn','bottom','top')
s.node('login',4,425,'Nếu chưa đăng nhập','/auth/login hoặc /auth/register\nVai trò học viên, từ 15 tuổi','page',h=85)
s.edge('message-modal','login','Cần đăng nhập','bottom','top')

s.band(600,390,'02  NHẬN & PHẢN HỒI LỜI MỜI','Chương trình chỉ hoạt động sau khi đúng học viên được mời chấp nhận và lời mời vẫn còn hiệu lực.')
for ident,col,title,sub,kind in [
 ('notify',0,'Nhận thông báo lời mời','/notifications hoặc mở Lời mời học','page'),('inbox',1,'Danh sách / xem trước lời mời','/invitations\nChờ / chấp nhận / từ chối / hết hạn / hủy','page'),
 ('inv-detail',2,'Chi tiết lời mời','/invitations/{id}\nĐọc mục tiêu, lịch, học phí','page'),('accept',3,'Chấp nhận lời mời?','Còn hiệu lực, đúng người được mời','decision'),
 ('programs',4,'Program của tôi','/programs\nDanh sách chương trình đã tham gia','page'),('workspace',5,'Mở workspace học tập','/programs/{id}','success'),('states',6,'Theo dõi trạng thái','active / paused / completed / cancelled','muted')]: s.node(ident,col,685,title,sub,kind)
s.chain(['notify','inbox','inv-detail','accept','programs','workspace','states'])
s.node('not-accept',3,840,'Từ chối / hết hạn / bị hủy','Chưa tham gia chương trình\nTrao đổi lại với tutor nếu cần','muted',h=85)
s.edge('accept','not-accept','Không','bottom','top')

s.band(1020,390,'03  HỌC TẬP TRONG WORKSPACE','Đây là sáu tab nội dung, không phải sáu khu vực tài khoản riêng. Menu và header được giữ nguyên khi đổi tab.')
for i,(ident,title,sub) in enumerate([
 ('overview','Tổng quan','/programs/{id}\nMục tiêu, buổi sắp tới, tiến độ'),('sessions','Buổi học','/programs/{id}/sessions\nXem lịch / nơi học / trạng thái'),
 ('tasks','Bài tập','/programs/{id}/tasks\nMở bài tập → nộp bài / nộp lại'),('progress','Tiến độ','/programs/{id}/progress\nXem kết quả do tutor cập nhật'),
 ('announce','Thông báo chương trình','/programs/{id}/announcements\nChỉ xem nội dung đã đăng'),('fees','Học phí','/programs/{id}/fees\nXem khoản phí tutor ghi nhận')]):
 s.node(ident,0,1105,title,sub,'page',h=104,x=80+i*380,width=340)
s.node('task-detail',2,1260,'Bài nộp & phản hồi','/programs/{id}/tasks/{taskId}\nNộp → tutor phản hồi → sửa nếu cần','page',h=90,x=840,width=340)
s.edge('tasks','task-detail','','bottom','top')
s.edge('workspace','announce','Các tab workspace','bottom','top',[(1860,1010),(1770,1010)],dashed=True)

s.band(1440,190,'04  SAU KHÓA HỌC','Đánh giá gắn với chương trình đã hoàn thành và điều kiện phía backend, không viết trực tiếp từ trang review công khai.')
s.node('complete',0,1510,'Chương trình hoàn thành','Tutor hoàn thành theo điều kiện hệ thống','success',h=88)
s.node('review',1,1510,'Gửi đánh giá chương trình','Trong /programs/{id}\nĐiểm 1–5 và nhận xét','action',h=88)
s.node('public-review',2,1510,'Hiển thị review công khai','/tutors/{slug}?tab=reviews\nPhụ thuộc trạng thái kiểm duyệt','external',h=88)
s.node('report',4,1510,'Nếu có vấn đề: gửi báo cáo','/reports?program_id={id}\nTheo dõi trạng thái và kết quả','page',h=88,width=590)
s.chain(['complete','review','public-review'])
s.utilities([('Yêu thích','/favorites'),('Thông báo','/notifications'),('Tin nhắn','/messages'),('Cài đặt / đăng xuất','/settings · avatar'),('Hỗ trợ','/support')])
s.notes([
 ('CÂN NHẮC GỘP: xem trước / chi tiết lời mời','/invitations đã xem được thông tin và phản hồi. Có thể dùng một panel chi tiết; giữ deep link /invitations/{id}.'),
 ('KHÔNG THIẾT KẾ LẠI: review route cũ','/tutors/{slug}/reviews hiện redirect về ?tab=reviews. Đây không còn là một màn hình độc lập.'),
 ('GIỮ: thông báo tài khoản ≠ thông báo lớp','/notifications là sự kiện toàn tài khoản. /programs/{id}/announcements là nội dung tutor đăng cho lớp. Học phí hiện không phải cổng thanh toán.')])

a = Page('tutorlink-admin-flow','03 · Admin','ADMIN — KIỂM DUYỆT & ĐIỀU HÀNH HỆ THỐNG', 'Hiện có hai giao diện: frontend :3000/admin và backend Filament :8000/admin · Quyền được kiểm tra theo từng thao tác.', '#208C78')
a.band(175,390,'01  DUYỆT HỒ SƠ TUTOR — FRONTEND','Admin không đăng ký từ form học viên / tutor; tài khoản và permission cần được cấp trước. Quyết định phải kèm lý do.')
for ident,col,title,sub,kind in [
 ('login',0,'Đăng nhập tài khoản admin','/auth/login\nTài khoản được cấp sẵn','page'),('permission',1,'Có permission phù hợp?','Vai trò admin chưa đủ\nQuyền xem / nhận / duyệt hồ sơ','decision'),
 ('queue',2,'Hàng đợi chưa phân công','FE :3000/admin\nLọc trạng thái hồ sơ','page'),('claim',3,'Nhận / phân công hồ sơ','Chuyển sang “Hồ sơ của tôi”','action'),
 ('profile',4,'Xem chi tiết hồ sơ','Trong /admin\nCá nhân, chuyên môn, phiên bản','page'),('docs',5,'Kiểm tra tài liệu','Duyệt / từ chối từng tài liệu\nChế độ demo có thể bỏ yêu cầu','action'),('decision',6,'Quyết định hồ sơ','Kiểm tra quyền + lý do + trạng thái','decision')]: a.node(ident,col,270,title,sub,kind)
a.chain(['login','permission','queue','claim','profile','docs','decision'])
a.node('approve',4,425,'Phê duyệt','Tutor đủ điều kiện công khai / giảng dạy','success',h=85)
a.node('change',5,425,'Yêu cầu chỉnh sửa','Tutor nhận lý do → sửa → gửi lại','external',h=85)
a.node('reject',6,425,'Từ chối / đình chỉ / khôi phục','Theo allowed_actions\nPhân biệt hồ sơ và tài khoản','muted',h=85)
a.edge('decision','reject','','bottom','top')
a.edge('decision','change','','bottom','top',[(2190,400),(1860,400)])
a.edge('decision','approve','','bottom','top',[(2190,395),(1530,395)])

a.band(600,390,'02  XỬ LÝ BÁO CÁO — BACKEND FILAMENT','Frontend hiện chưa có màn hình quản trị báo cáo tương ứng. Backend đã có tài nguyên Reports và trang Operations.')
for ident,col,title,sub,kind in [
 ('be-admin',0,'Vào admin backend','BE :8000/admin\nĐăng nhập + panel enabled + quyền','page'),('reports',1,'Danh sách báo cáo','Filament · Reports','page'),('assign',2,'Nhận / phân công xử lý','Chỉ xem phạm vi có quyền','action'),
 ('evidence',3,'Xem nội dung & bằng chứng','Operations · report / report-assignment','page'),('resolve',4,'Giải quyết hay đóng?','Nhập resolution_note / lý do','decision'),('report-result',5,'Cập nhật kết quả xử lý','resolve / dismiss\nGhi dấu vết quyết định','success'),('user-result',6,'Người gửi xem kết quả','FE :3000/reports\nGiao với tutor / học viên','external')]: a.node(ident,col,685,title,sub,kind)
a.chain(['be-admin','reports','assign','evidence','resolve','report-result','user-result'])

a.band(1020,390,'03  ĐÁNH GIÁ, KIỂM DUYỆT & VẬN HÀNH','Các chức năng này đã có phía backend; không nhầm với menu /reports dành cho người gửi báo cáo.')
for ident,col,title,sub,kind in [
 ('reviews',0,'Danh sách đánh giá','Filament · Reviews','page'),('review-detail',1,'Xem ngữ cảnh đánh giá','Operations · review','page'),('moderate',2,'Ẩn / khôi phục đánh giá','Nhập lý do; kiểm tra quyền / phiên bản','action'),('public',3,'Cập nhật hiển thị công khai','Tab review hồ sơ tutor','external'),
 ('ops',4,'Điều hành / audit / outbox','Backend · Operations\nTheo quyền và cấu hình','page'),('monitor',5,'Giám sát hệ thống','FE :3000/monitoring','page'),('tools',6,'Công cụ theo dõi','BE /telescope · /pulse\nDashboard máy chủ nếu cấu hình','external')]: a.node(ident,col,1105,title,sub,kind)
a.chain(['reviews','review-detail','moderate','public'])
a.chain(['ops','monitor','tools'])

a.band(1440,190,'04  CÁC ĐIỂM VÀO KIỂM DUYỆT ĐANG TRÙNG NHAU','Các nhánh bên dưới là hiện trạng đã xác minh trong code. Chọn một giao diện chính trước khi tiếp tục đầu tư thiết kế.')
a.node('fe',0,1510,'Giao diện duyệt frontend','FE :3000/admin\nHàng đợi / nhận / quyết định hồ sơ','page',h=88,width=570)
a.node('be',2,1510,'Giao diện duyệt Filament','BE :8000/admin\nTutor Profiles + Operations','page',h=88,width=570)
a.node('assigned',4,1510,'Trang nghiệp vụ server cũ','BE /admin-business/assigned-profiles\nDanh sách / chi tiết / quyết định','page',h=88,width=570)
a.utilities([('Cài đặt admin','FE /settings'),('Quản lý permission','Cấu hình / cấp quyền backend'),('Đình chỉ / khôi phục','Trong chi tiết hồ sơ / account action'),('Audit & outbox','Backend · Operations / API'),('Đăng xuất','Avatar · FE hoặc panel backend')])
a.notes([
 ('ƯU TIÊN: chọn một admin UI chính','FE /admin và Filament trùng nghiệp vụ duyệt hồ sơ. Đề xuất chọn Filament cho giai đoạn demo; FE chỉ cần lối vào hoặc redirect nếu bạn duyệt phương án.'),
 ('CÂN NHẮC NGỪNG: admin-business','Đã có thêm một giao diện assigned-profiles. Có thể ngừng làm mới UI này sau khi xác nhận không còn quy trình phụ thuộc.'),
 ('KHÔNG XÓA QUYỀN / LUỒNG KIỂM DUYỆT','Gộp màn hình không đồng nghĩa bỏ kiểm tra permission, phân công, xét tài liệu, audit hay lý do quyết định. Đây là nghiệp vụ cần giữ.')])

PAGES = [t,s,a]
for page in PAGES:
    for e in page.edges:
        if (e['a'],e['b']) in [('review','approved'),('accepted','workspace'),('accept','programs'),('permission','queue')]: e['label']='Có'

def position(n, side):
    return {'right':(n['x']+n['w'],n['y']+n['h']/2), 'left':(n['x'],n['y']+n['h']/2), 'top':(n['x']+n['w']/2,n['y']), 'bottom':(n['x']+n['w']/2,n['y']+n['h'])}[side]

def routes(page):
    nodes={n['id']:n for n in page.nodes}
    for e in page.edges:
        p,q=position(nodes[e['a']],e['start']),position(nodes[e['b']],e['end'])
        pts=[p]+e['points']+[q]
        if not e['points'] and p[0]!=q[0] and p[1]!=q[1]: pts=[p,(p[0],q[1]),q]
        yield e,pts

def html_label(n):
    if n['kind']=='decision': return f'<b>{escape(n["title"])}</b>'
    return f'<b>{escape(n["title"])}</b><br><font style="font-size:12px" color="#64748B">'+escape(n['sub']).replace('\n','<br>')+'</font>'

def drawio():
    mx=ET.Element('mxfile',host='app.diagrams.net',agent='TutorLink user-flow audit',version='24.7.17',pages='3')
    ports={'right':(1,.5),'left':(0,.5),'top':(.5,0),'bottom':(.5,1)}
    for p in PAGES:
        diag=ET.SubElement(mx,'diagram',id=p.ident,name=p.name)
        model=ET.SubElement(diag,'mxGraphModel',dx='1600',dy='1000',grid='1',gridSize='10',guides='1',tooltips='1',connect='1',arrows='1',fold='1',page='1',pageScale='1',pageWidth=str(W),pageHeight=str(H),math='0',shadow='0')
        root=ET.SubElement(model,'root'); ET.SubElement(root,'mxCell',id='0'); ET.SubElement(root,'mxCell',id='1',parent='0')
        def cell(ident,value,x,y,w,h,style):
            c=ET.SubElement(root,'mxCell',id=ident,value=value,style=style,vertex='1',parent='1')
            ET.SubElement(c,'mxGeometry',x=str(x),y=str(y),width=str(w),height=str(h),**{'as':'geometry'})
        cell('title',p.title,50,30,2300,47,f'text;html=1;align=left;verticalAlign=middle;fontFamily=Arial;fontSize=30;fontStyle=1;fontColor=#10264B;')
        cell('subtitle',p.subtitle,50,86,2300,35,'text;html=1;align=left;fontFamily=Arial;fontSize=15;fontColor=#667892;')
        cell('legend','Trắng: trang hiện có    |    Tím: thao tác    |    Xanh lá: kết quả    |    Vàng: điều kiện    |    Xanh dương: giao vai trò / hệ thống    |    Cam: đề xuất gộp',50,125,2300,28,'text;html=1;align=left;fontFamily=Arial;fontSize=13;fontColor=#667892;')
        for i,(y,h,title,sub) in enumerate(p.bands):
            cell(f'band-{i}','',40,y,2320,h,'rounded=1;arcSize=5;fillColor=#F7F9FC;strokeColor=#E4EAF1;')
            cell(f'band-title-{i}',title,65,y+14,2260,29,f'text;html=1;align=left;fontFamily=Arial;fontSize=17;fontStyle=1;fontColor={p.color};')
            cell(f'band-sub-{i}',sub,65,y+44,2260,26,'text;html=1;align=left;fontFamily=Arial;fontSize=13;fontColor=#718096;')
        for n in p.nodes:
            fill,stroke=COLORS[n['kind']]
            style=f'rounded=1;whiteSpace=wrap;html=1;align=center;verticalAlign=middle;fontFamily=Arial;fontSize=16;fontColor=#152A4D;fillColor={fill};strokeColor={stroke};strokeWidth=1.4;spacing=13;arcSize=12;'
            if n['kind']=='decision': style+='shape=rhombus;perimeter=rhombusPerimeter;'
            if n['kind']=='proposal': style+='dashed=1;'
            cell(n['id'],html_label(n),n['x'],n['y'],n['w'],n['h'],style)
        for i,(e,pts) in enumerate(routes(p)):
            ex,ey=ports[e['start']];ix,iy=ports[e['end']]
            style=f'edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;endArrow=block;endFill=1;strokeColor=#8394AD;strokeWidth=1.6;fontFamily=Arial;fontSize=12;fontColor=#5C708C;labelBackgroundColor=#FFFFFF;exitX={ex};exitY={ey};entryX={ix};entryY={iy};'
            if e['dashed']: style+='dashed=1;'
            c=ET.SubElement(root,'mxCell',id=f'edge-{i}',value=e['label'],style=style,edge='1',parent='1',source=e['a'],target=e['b'])
            geom=ET.SubElement(c,'mxGeometry',relative='1',**{'as':'geometry'})
            if e['points']:
                arr=ET.SubElement(geom,'Array',**{'as':'points'})
                for x,y in e['points']: ET.SubElement(arr,'mxPoint',x=str(x),y=str(y))
    ET.indent(mx)
    ET.ElementTree(mx).write(OUT/'tutorlink-user-flows.drawio',encoding='utf-8',xml_declaration=True)

def wrapped(text,font,width):
    lines=[]
    for para in text.split('\n'):
        line=''
        for word in para.split():
            candidate=f'{line} {word}'.strip()
            if font.getlength(candidate)>width and line: lines.append(line);line=word
            else: line=candidate
        lines.append(line)
    return lines

def preview(page, index):
    im=Image.new('RGB',(W,H),'white'); d=ImageDraw.Draw(im)
    fonts={size:ImageFont.truetype(NORMAL,size) for size in [12,13,14,15]}
    bold={size:ImageFont.truetype(BOLD,size) for size in [16,17,30]}
    d.text((50,30),page.title,font=bold[30],fill='#10264B')
    d.text((50,87),page.subtitle,font=fonts[15],fill='#667892')
    d.text((50,129),'Trắng: trang  |  Tím: thao tác  |  Xanh lá: kết quả  |  Vàng: điều kiện  |  Xanh dương: giao hệ thống  |  Cam: đề xuất gộp',font=fonts[13],fill='#667892')
    for y,h,title,sub in page.bands:
        d.rounded_rectangle((40,y,2360,y+h),radius=18,fill='#F7F9FC',outline='#E4EAF1',width=1)
        d.text((65,y+15),title,font=bold[17],fill=page.color)
        d.text((65,y+45),sub,font=fonts[13],fill='#718096')
    for e,pts in routes(page):
        d.line(pts,fill='#8394AD',width=2)
        end, prev=pts[-1],pts[-2]
        vx,vy=end[0]-prev[0],end[1]-prev[1]; length=(vx*vx+vy*vy)**.5 or 1; ux,uy=vx/length,vy/length
        d.polygon([end,(end[0]-9*ux+4*uy,end[1]-9*uy-4*ux),(end[0]-9*ux-4*uy,end[1]-9*uy+4*ux)],fill='#8394AD')
        if e['label']:
            left,right=pts[0],pts[1];px,py=(left[0]+right[0])/2,(left[1]+right[1])/2
            tw=fonts[12].getlength(e['label']);d.rectangle((px-tw/2-4,py-19,px+tw/2+4,py-2),fill='white');d.text((px-tw/2,py-18),e['label'],font=fonts[12],fill='#5C708C')
    for n in page.nodes:
        x,y,w,h=n['x'],n['y'],n['w'],n['h'];fill,stroke=COLORS[n['kind']]
        if n['kind']=='decision': d.polygon([(x+w/2,y),(x+w,y+h/2),(x+w/2,y+h),(x,y+h/2)],fill=fill,outline=stroke,width=2)
        else: d.rounded_rectangle((x,y,x+w,y+h),radius=12,fill=fill,outline=stroke,width=2)
        width=w-32 if n['kind']!='decision' else w*.70
        titles=wrapped(n['title'],bold[16],width);subs=wrapped(n['sub'],fonts[12],width) if n['kind']!='decision' else []
        height=len(titles)*20+6+len(subs)*16
        if height>h-12: raise ValueError(f'Label exceeds node: {page.name} / {n["id"]} ({height} > {h})')
        ty=y+(h-height)/2
        for line in titles:
            d.text((x+(w-bold[16].getlength(line))/2,ty),line,font=bold[16],fill='#152A4D');ty+=20
        ty+=6
        for line in subs:
            d.text((x+(w-fonts[12].getlength(line))/2,ty),line,font=fonts[12],fill='#64748B');ty+=16
    im.save(OUT/f'{index:02d}-{page.name.split(" · ")[1].replace(" ","-")}.png')

if __name__=='__main__':
    OUT.mkdir(parents=True,exist_ok=True)
    drawio()
    for index,page in enumerate(PAGES,1): preview(page,index)
    (OUT/'flow-model.json').write_text(json.dumps([dict(name=p.name,nodes=p.nodes,edges=p.edges) for p in PAGES],ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'file':str(OUT/'tutorlink-user-flows.drawio'),'pages':[{ 'name':p.name,'nodes':len(p.nodes),'connections':len(p.edges)} for p in PAGES]},ensure_ascii=False))
