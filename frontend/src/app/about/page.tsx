import "@/app/about/about.css";
import { AppShell } from "@/components/layout/app-shell";
import Link from "next/link";

const principles = [
  {
    number: "01",
    title: "Lựa chọn phù hợp bắt đầu từ thông tin rõ ràng",
    text: "Tìm hiểu hồ sơ, chuyên môn, kinh nghiệm và dịch vụ để chọn người hướng dẫn phù hợp với mục tiêu, lịch trình và cách học của bạn.",
  },
  {
    number: "02",
    title: "Trao đổi trước khi bắt đầu",
    text: "Học viên và gia sư có thể nhắn tin để thống nhất mục tiêu, nội dung, hình thức học và lịch học trước khi cùng xây dựng lộ trình.",
  },
  {
    number: "03",
    title: "Theo dõi hành trình tiến bộ",
    text: "Các chương trình học giúp hai bên sắp xếp buổi học, theo dõi hoạt động và cùng nhìn lại những bước tiến trên hành trình đã chọn.",
  },
];

export default function AboutPage() {
  return <AppShell mode="public" title="About TutorLink">
    <div className="about-page">
      <section className="about-hero">
        <div className="about-hero-orbit about-hero-orbit-one" aria-hidden="true" />
        <div className="about-hero-orbit about-hero-orbit-two" aria-hidden="true" />
        <div className="about-container about-hero-content">
          <p className="about-eyebrow">VỀ TUTORLINK</p>
          <h1>Kết nối người học với người đồng hành phù hợp.</h1>
          <p className="about-hero-lead">TutorLink là nền tảng giúp bạn khám phá gia sư, huấn luyện viên và các dịch vụ học tập, rèn luyện — để biến mục tiêu của mình thành một hành trình có người cùng đồng hành.</p>
          <div className="about-actions">
            <Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
            <Link className="about-secondary-link" href="/become-a-tutor">Trở thành gia sư <span aria-hidden="true">↗</span></Link>
          </div>
          <div className="about-hero-note"><span aria-hidden="true">✦</span><span>Học hỏi điều mới. Rèn luyện kỹ năng. Tiến gần hơn đến mục tiêu của bạn.</span></div>
        </div>
        <div className="about-hero-art" aria-hidden="true">
          <div className="about-art-card about-art-card-main"><span className="about-art-icon">↗</span><div><i /><i /><i /></div><strong>Mục tiêu của bạn</strong><small>Mỗi bước tiến đều có ý nghĩa</small></div>
          <div className="about-art-card about-art-card-tutor"><span className="about-art-avatar">TL</span><span><strong>Gia sư phù hợp</strong><small>Cùng bạn xây dựng lộ trình</small></span><b>✓</b></div>
          <div className="about-art-spark about-art-spark-one">✦</div><div className="about-art-spark about-art-spark-two">✧</div>
        </div>
      </section>

      <section className="about-intro about-container">
        <div><p className="about-eyebrow">MỘT NƠI, NHIỀU HÀNH TRÌNH</p><h2>Mỗi người học một mục tiêu riêng.</h2></div>
        <p className="about-intro-copy">Có người muốn nắm vững một môn học. Có người muốn rèn sức khỏe, chơi tốt một môn thể thao hoặc phát triển kỹ năng mới. TutorLink đưa người học và người hướng dẫn đến gần nhau hơn, để việc bắt đầu trở nên rõ ràng và thuận tiện.</p>
      </section>

      <section className="about-journey-section">
        <div className="about-container">
          <div className="about-section-heading"><p className="about-eyebrow">CÁCH TUTORLINK HOẠT ĐỘNG</p><h2>Từ ý tưởng đến hành động, từng bước một.</h2><p>Không cần biết hết mọi thứ ngay từ đầu. Hãy bắt đầu bằng điều bạn muốn đạt được.</p></div>
          <div className="about-journey-grid">{principles.map((item) => <article className="about-journey-card" key={item.number}><span className="about-step-number">{item.number}</span><div className="about-step-line" aria-hidden="true" /><h3>{item.title}</h3><p>{item.text}</p></article>)}</div>
        </div>
      </section>

      <section className="about-community about-container">
        <div className="about-community-copy"><p className="about-eyebrow">DÀNH CHO CẢ HAI PHÍA</p><h2>Một cộng đồng cùng học và cùng dạy.</h2><p>Người học có thể so sánh các lựa chọn, trao đổi với gia sư và theo dõi chương trình của mình. Gia sư có thể giới thiệu chuyên môn, tạo dịch vụ, quản lý lịch khả dụng và đồng hành cùng học viên qua từng buổi học.</p></div>
        <div className="about-community-cards"><article><span aria-hidden="true">⌕</span><h3>Dành cho người học</h3><p>Tìm người hướng dẫn, khám phá dịch vụ và chọn cách học phù hợp.</p><Link href="/marketplace">Tìm gia sư <b aria-hidden="true">→</b></Link></article><article><span aria-hidden="true">✎</span><h3>Dành cho gia sư</h3><p>Chia sẻ kinh nghiệm, giới thiệu dịch vụ và hỗ trợ học viên tiến bộ.</p><Link href="/become-a-tutor">Tìm hiểu cách tham gia <b aria-hidden="true">→</b></Link></article></div>
      </section>

      <section className="about-values">
        <div className="about-container about-values-inner"><div><p className="about-eyebrow">ĐIỀU CHÚNG TÔI HƯỚNG ĐẾN</p><h2>Trải nghiệm học tập minh bạch, có định hướng và lấy con người làm trung tâm.</h2></div><p>TutorLink khuyến khích hai bên trao đổi kỳ vọng từ sớm, thống nhất mục tiêu và chủ động xây dựng mối quan hệ học tập tôn trọng lẫn nhau. Mỗi hành trình bắt đầu từ một cuộc kết nối phù hợp.</p></div>
      </section>

      <section className="about-final about-container"><div><p className="about-eyebrow">BƯỚC TIẾP THEO LÀ CỦA BẠN</p><h2>Sẵn sàng bắt đầu hành trình mới?</h2><p>Khám phá các dịch vụ trên TutorLink hoặc tham gia cộng đồng với vai trò gia sư.</p></div><div className="about-actions"><Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link><Link className="button button-secondary" href="/become-a-tutor">Trở thành gia sư</Link></div></section>
    </div>
  </AppShell>;
}
