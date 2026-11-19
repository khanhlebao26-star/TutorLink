import "@/app/support/support.css";
import { AppShell } from "@/components/layout/app-shell";
import Link from "next/link";
import type { ReactNode } from "react";

const commonQuestions = [
  {
    question: "Làm cách nào để cập nhật thông tin tài khoản?",
    answer: <>Mở menu avatar, chọn <Link href="/settings">Cài đặt</Link> để cập nhật tên hiển thị và xem thông tin tài khoản. Email đăng nhập hiện được dùng để nhận các thông báo liên quan.</>,
  },
  {
    question: "Tôi không thấy tin nhắn hoặc lời mời của mình ở đâu?",
    answer: <>Bạn có thể mở <Link href="/messages">Tin nhắn</Link> để xem cuộc trò chuyện, hoặc <Link href="/invitations">Lời mời học</Link> để kiểm tra lời mời tham gia chương trình. Hãy đăng nhập đúng tài khoản nếu các mục này chưa hiển thị.</>,
  },
  {
    question: "Thông báo được xem ở đâu?",
    answer: <>Mở mục <Link href="/notifications">Thông báo</Link> từ thanh điều hướng hoặc menu tài khoản để xem các cập nhật mới.</>,
  },
];

const learnerQuestions = [
  {
    question: "Làm thế nào để tìm gia sư hoặc dịch vụ phù hợp?",
    answer: <>Vào <Link href="/marketplace">Tìm dịch vụ</Link>, sau đó chọn lĩnh vực và dùng bộ lọc chuyên môn, hình thức học, khu vực hoặc mức phí để thu hẹp kết quả. Mở trang dịch vụ để xem thông tin trước khi liên hệ gia sư.</>,
  },
  {
    question: "Tôi có thể lưu hồ sơ gia sư để xem lại không?",
    answer: <>Có. Nhấn nút <strong>Yêu thích</strong> trên hồ sơ gia sư. Các hồ sơ đã lưu sẽ xuất hiện trong mục <Link href="/favorites">Yêu thích</Link> trên thanh đầu trang.</>,
  },
  {
    question: "Lịch hiển thị trên hồ sơ có phải lịch đã đặt chắc chắn không?",
    answer: "Đây là khung giờ tham khảo do gia sư chia sẻ. Hãy nhắn tin để thống nhất ngày, giờ và hình thức học cụ thể trước khi bắt đầu.",
  },
  {
    question: "Tôi có thể đánh giá gia sư sau khi học không?",
    answer: "Đánh giá nên phản ánh trải nghiệm học thực tế. Hệ thống chỉ cho phép học viên đủ điều kiện gửi đánh giá theo trạng thái chương trình của tài khoản.",
  },
];

const tutorQuestions = [
  {
    question: "Tôi bắt đầu tạo hồ sơ và dịch vụ ở đâu?",
    answer: <>Cập nhật thông tin chuyên môn tại <Link href="/profile">Hồ sơ Tutor</Link>, sau đó tạo dịch vụ trong mục <Link href="/me/listings">Dịch vụ của tôi</Link>. Thông tin rõ ràng giúp học viên hiểu nội dung, hình thức và mức phí bạn cung cấp.</>,
  },
  {
    question: "Làm thế nào để cập nhật thời gian có thể dạy?",
    answer: <>Mở <Link href="/me/availability">Lịch khả dụng</Link> để quản lý các khung giờ tham khảo. Hãy trao đổi trực tiếp với học viên để xác nhận lịch cho từng buổi.</>,
  },
  {
    question: "Tôi xem lời mời và trao đổi với học viên ở đâu?",
    answer: <>Bạn có thể theo dõi lời mời trong <Link href="/invitations">Lời mời học</Link> và tiếp tục trao đổi tại <Link href="/messages">Tin nhắn</Link>.</>,
  },
  {
    question: "Tôi xem báo cáo của mình ở đâu?",
    answer: <>Mở <Link href="/reports">Báo cáo của tôi</Link> để xem các báo cáo liên quan đến hoạt động tài khoản.</>,
  },
];

function QuestionList({ items }: { items: { question: string; answer: ReactNode }[] }) {
  return <div className="support-question-list">
    {items.map(({ question, answer }) => <details className="support-question" key={question}>
      <summary>{question}<span aria-hidden="true">+</span></summary>
      <div className="support-answer"><p>{answer}</p></div>
    </details>)}
  </div>;
}

export default function SupportPage() {
  return <AppShell mode="public" title="Hỗ trợ">
    <div className="support-page">
      <section className="support-hero">
        <div className="support-hero-orb support-hero-orb-left" aria-hidden="true" />
        <div className="support-hero-orb support-hero-orb-right" aria-hidden="true" />
        <div className="support-hero-content">
          <p className="support-eyebrow">TRUNG TÂM HỖ TRỢ TUTORLINK</p>
          <h1>Chúng tôi sẵn sàng <span>đồng hành</span></h1>
          <p>Tìm câu trả lời và hướng dẫn dành cho cả học viên lẫn gia sư.</p>
          <a className="support-hero-button" href="#cau-hoi">Xem câu hỏi thường gặp <span aria-hidden="true">↓</span></a>
        </div>
      </section>

      <div className="support-content">
        <nav className="support-shortcuts" aria-label="Chủ đề hỗ trợ">
          <a href="#chung"><span className="support-shortcut-icon" aria-hidden="true">?</span><span><strong>Tài khoản &amp; sử dụng</strong><small>Cài đặt, tin nhắn và thông báo</small></span><b aria-hidden="true">→</b></a>
          <a href="#hoc-vien"><span className="support-shortcut-icon is-blue" aria-hidden="true">⌕</span><span><strong>Dành cho học viên</strong><small>Tìm dịch vụ và học cùng gia sư</small></span><b aria-hidden="true">→</b></a>
          <a href="#gia-su"><span className="support-shortcut-icon is-purple" aria-hidden="true">✦</span><span><strong>Dành cho gia sư</strong><small>Hồ sơ, dịch vụ và lịch dạy</small></span><b aria-hidden="true">→</b></a>
        </nav>

        <section className="support-faq" id="cau-hoi" aria-labelledby="support-faq-title">
          <header className="support-section-heading">
            <p className="support-eyebrow">CÂU HỎI THƯỜNG GẶP</p>
            <h2 id="support-faq-title">Bạn cần trợ giúp điều gì?</h2>
            <p>Chọn chủ đề phù hợp để xem hướng dẫn nhanh.</p>
          </header>

          <section className="support-faq-group" id="chung">
            <div className="support-group-heading"><span>01</span><div><h3>Tài khoản &amp; sử dụng TutorLink</h3><p>Thông tin hữu ích cho mọi thành viên.</p></div></div>
            <QuestionList items={commonQuestions} />
          </section>

          <section className="support-faq-group" id="hoc-vien">
            <div className="support-group-heading"><span>02</span><div><h3>Học viên</h3><p>Tìm người đồng hành và quản lý hành trình học.</p></div></div>
            <QuestionList items={learnerQuestions} />
          </section>

          <section className="support-faq-group" id="gia-su">
            <div className="support-group-heading"><span>03</span><div><h3>Gia sư &amp; Coach</h3><p>Xây dựng hồ sơ và tổ chức hoạt động dạy.</p></div></div>
            <QuestionList items={tutorQuestions} />
          </section>
        </section>

        <section className="support-contact" aria-labelledby="support-contact-title">
          <div><p className="support-eyebrow">CẦN HỖ TRỢ THÊM?</p><h2 id="support-contact-title">Hãy bắt đầu một cuộc trò chuyện</h2><p>Đăng nhập để nhắn tin và cung cấp thêm thông tin về vấn đề bạn gặp phải.</p></div>
          <Link href="/messages">Đi đến tin nhắn <span aria-hidden="true">→</span></Link>
        </section>
      </div>
    </div>
  </AppShell>;
}
