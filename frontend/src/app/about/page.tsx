import "@/app/about/about.css";
import { AppShell } from "@/components/layout/app-shell";
import Image from "next/image";
import Link from "next/link";
import { MagnifyingGlassIcon } from "@phosphor-icons/react/dist/ssr/MagnifyingGlass";
import { SealCheckIcon } from "@phosphor-icons/react/dist/ssr/SealCheck";
import { CalendarDotsIcon } from "@phosphor-icons/react/dist/ssr/CalendarDots";
import { ChartLineUpIcon } from "@phosphor-icons/react/dist/ssr/ChartLineUp";
import { ChalkboardTeacherIcon } from "@phosphor-icons/react/dist/ssr/ChalkboardTeacher";
import { ChatCircleDotsIcon } from "@phosphor-icons/react/dist/ssr/ChatCircleDots";
import { GraduationCapIcon } from "@phosphor-icons/react/dist/ssr/GraduationCap";
import { PathIcon } from "@phosphor-icons/react/dist/ssr/Path";
import { TargetIcon } from "@phosphor-icons/react/dist/ssr/Target";

export const dynamic = "force-static";
export const revalidate = 300;

export default function AboutPage() {
  return <AppShell mode="public" title="About TutorLink">
    <div className="about-page">
      <section className="about-hero">
        <div className="about-hero-orbit about-hero-orbit-one" aria-hidden="true" />
        <div className="about-hero-orbit about-hero-orbit-two" aria-hidden="true" />
        <div className="about-container about-hero-content">
          <p className="about-eyebrow">VỀ TUTORLINK</p>
          <h1>Kết nối người học với người dạy phù hợp.</h1>
          <p className="about-hero-lead">TutorLink giúp bạn khám phá gia sư, huấn luyện viên và dịch vụ phù hợp — rồi cùng xây dựng lộ trình để tiến bộ.</p>
          <div className="about-actions">
            <Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
            <Link className="about-secondary-link" href="/become-a-tutor">Trở thành gia sư <span aria-hidden="true">↗</span></Link>
          </div>
          <div className="about-hero-note"><SealCheckIcon aria-hidden="true" weight="regular" /><span>Hồ sơ được kiểm duyệt · Trao đổi trực tiếp · Theo dõi lộ trình</span></div>
        </div>
        <div className="about-hero-art" aria-hidden="true">
          <Image className="about-hero-illustration" src="/about/about-hero-match.png" alt="" width={1750} height={1024} priority sizes="(max-width: 620px) 100vw, (max-width: 1100px) 45vw, 620px" />
        </div>
      </section>

      <section className="about-intro about-container">
        <div className="about-intro-heading"><span className="about-intro-icon"><TargetIcon aria-hidden="true" weight="regular" /></span><div><p className="about-eyebrow">MỘT NỀN TẢNG, NHIỀU MỤC TIÊU</p><h2>Mỗi hành trình bắt đầu từ mục tiêu riêng.</h2></div></div>
        <p className="about-intro-copy">Từ học một môn mới đến rèn luyện thể chất, mỗi người bắt đầu với điều mình muốn làm tốt hơn. TutorLink giúp bạn tìm người hướng dẫn và dịch vụ phù hợp để bắt đầu rõ ràng hơn.</p>
      </section>

      <section className="about-journey-section">
        <div className="about-container">
          <div className="about-section-heading"><p className="about-eyebrow">CÁCH TUTORLINK HOẠT ĐỘNG</p><h2>Từ ý tưởng đến hành động, từng bước một.</h2><p>Không cần biết hết mọi thứ ngay từ đầu. Hãy bắt đầu bằng điều bạn muốn đạt được.</p></div>
          <div className="about-journey-grid">
            <article className="about-journey-card"><div className="about-step-visual"><span className="about-step-icon"><MagnifyingGlassIcon aria-hidden="true" weight="regular" /></span></div><div className="about-step-line" aria-hidden="true" /><h3>Khám phá người dạy</h3><p>Tìm kiếm và lựa chọn gia sư phù hợp với nhu cầu, mục tiêu và lịch học của bạn.</p></article>
            <article className="about-journey-card"><div className="about-step-visual"><span className="about-step-icon is-blue"><ChatCircleDotsIcon aria-hidden="true" weight="regular" /></span></div><div className="about-step-line" aria-hidden="true" /><h3>Trao đổi trước khi bắt đầu</h3><p>Thống nhất mục tiêu, nội dung, hình thức và lịch học trước khi cùng bắt đầu.</p></article>
            <article className="about-journey-card"><div className="about-step-visual"><span className="about-step-icon is-purple"><ChartLineUpIcon aria-hidden="true" weight="regular" /></span></div><div className="about-step-line" aria-hidden="true" /><h3>Theo dõi tiến độ</h3><p>Cùng nhìn lại quá trình học, đánh giá tiến bộ và điều chỉnh khi cần.</p></article>
          </div>
        </div>
      </section>

      <section className="about-community about-container">
        <div className="about-community-heading"><p className="about-eyebrow">DÀNH CHO CẢ HAI PHÍA</p><h2>Học và dạy hiệu quả trên cùng một nền tảng.</h2><p>Những công cụ cần thiết để kết nối, tổ chức việc học và cùng tiến bộ.</p></div>
        <div className="about-community-cards">
          <article className="about-audience-card"><div className="about-audience-illustration is-student"><GraduationCapIcon aria-hidden="true" weight="regular" /></div><h3>Dành cho học viên</h3><ul className="about-benefit-list"><li><MagnifyingGlassIcon aria-hidden="true" weight="regular" /><span><strong>Tìm gia sư phù hợp</strong><small>Dễ dàng tìm và lựa chọn người dạy đúng nhu cầu.</small></span></li><li><CalendarDotsIcon aria-hidden="true" weight="regular" /><span><strong>Học linh hoạt</strong><small>Chủ động sắp xếp lịch học theo thời gian của bạn.</small></span></li><li><ChartLineUpIcon aria-hidden="true" weight="regular" /><span><strong>Theo dõi tiến bộ</strong><small>Nắm bắt quá trình học và kết quả một cách rõ ràng.</small></span></li></ul><Link href="/marketplace">Khám phá dịch vụ <b aria-hidden="true">→</b></Link></article>
          <article className="about-audience-card"><div className="about-audience-illustration is-tutor"><ChalkboardTeacherIcon aria-hidden="true" weight="regular" /></div><h3>Dành cho gia sư</h3><ul className="about-benefit-list"><li><SealCheckIcon aria-hidden="true" weight="regular" /><span><strong>Tiếp cận học viên phù hợp</strong><small>Kết nối với người đang tìm đúng chuyên môn của bạn.</small></span></li><li><PathIcon aria-hidden="true" weight="regular" /><span><strong>Chủ động giảng dạy</strong><small>Thống nhất nội dung, phương pháp và lịch học linh hoạt.</small></span></li><li><ChartLineUpIcon aria-hidden="true" weight="regular" /><span><strong>Đồng hành và phát triển</strong><small>Theo dõi hành trình dạy và nâng cao trải nghiệm giảng dạy.</small></span></li></ul><Link href="/become-a-tutor">Trở thành gia sư <b aria-hidden="true">→</b></Link></article>
        </div>
      </section>

      <section className="about-values">
        <div className="about-container about-values-inner"><div className="about-values-heading"><p className="about-eyebrow">MINH BẠCH TRONG TỪNG KẾT NỐI</p><h2>Rõ ràng từ lựa chọn đầu tiên đến từng bước tiến.</h2><p>TutorLink tập trung vào trải nghiệm học và dạy có định hướng, thuận tiện và đáng tin cậy.</p></div><div className="about-values-cards"><article><span><SealCheckIcon aria-hidden="true" weight="regular" /></span><h3>Hồ sơ được kiểm duyệt</h3><p>Thông tin người dạy và người học được xác minh, rõ ràng và đáng tin cậy.</p></article><article><span><ChatCircleDotsIcon aria-hidden="true" weight="regular" /></span><h3>Trao đổi trực tiếp</h3><p>Kết nối và trao đổi dễ dàng ngay trên nền tảng trước khi bắt đầu.</p></article><article><span><PathIcon aria-hidden="true" weight="regular" /></span><h3>Theo dõi trong Program</h3><p>Theo dõi tiến độ học tập và các nội dung đã thống nhất trong Program.</p></article></div></div>
      </section>

      <section className="about-final about-container"><div><p className="about-eyebrow">BƯỚC TIẾP THEO LÀ CỦA BẠN</p><h2>Sẵn sàng bắt đầu hành trình mới?</h2><p>Khám phá các dịch vụ trên TutorLink hoặc tham gia cộng đồng với vai trò gia sư.</p></div><div className="about-actions"><Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link><Link className="button button-secondary" href="/become-a-tutor">Trở thành gia sư</Link></div></section>
    </div>
  </AppShell>;
}
