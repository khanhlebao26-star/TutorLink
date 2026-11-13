import Image from "next/image";
import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { FeaturedServices } from "@/components/featured-services";
import { FeaturedTutors } from "@/components/featured-tutors";
import { StudentTestimonials } from "@/components/student-testimonials";
import { CatalogLinks } from "./become-a-tutor/catalog-links";

const learningSteps = [
  { number: "1", icon: "search", title: "Tìm dịch vụ", text: "Khám phá các dịch vụ phù hợp với mục tiêu của bạn." },
  { number: "2", icon: "chat", title: "Nhắn tin trao đổi", text: "Trao đổi trực tiếp để hiểu rõ nội dung và lộ trình." },
  { number: "3", icon: "program", title: "Tham gia Program", text: "Bắt đầu hành trình phát triển cùng người dạy phù hợp." },
];

const platformFeatures = [
  { icon: "verified", title: "Hồ sơ đã xác thực", text: "Thông tin chuyên môn và danh tính người dạy được kiểm duyệt." },
  { icon: "plan", title: "Lộ trình rõ ràng", text: "Mục tiêu, nội dung và tiến độ được thống nhất ngay từ đầu." },
  { icon: "flexible", title: "Kết nối linh hoạt", text: "Học trực tuyến hoặc trực tiếp theo thời gian phù hợp với bạn." },
  { icon: "support", title: "Luôn được hỗ trợ", text: "Đội ngũ TutorLink sẵn sàng đồng hành trong suốt chương trình." },
];

const platformStats = [
  { value: "10.000+", label: "Người học" },
  { value: "1.200+", label: "Tutor & Coach" },
  { value: "4.9/5", label: "Điểm hài lòng" },
  { value: "96%", label: "Đạt mục tiêu" },
];

function SectionIntro({ eyebrow, title, text, id }: { eyebrow: string; title: string; text?: string; id?: string }) {
  return <div className="landing-section-intro">
    <p className="landing-eyebrow">{eyebrow}</p>
    <h2 id={id}>{title}</h2>
    {text ? <p>{text}</p> : null}
  </div>;
}

function StepIcon({ name }: { name: string }) {
  if (name === "search") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.2" /><path d="m15 15 4.5 4.5" /></svg>;
  if (name === "chat") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M4 11h16M4 16h10" /><path d="m5 20 3-3h12V4H4v13" /></svg>;
  return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 12c-1.7-1.1-4.7-5.9-2.2-7.5 2.1-1.3 3.2 1.2 2.2 3.4 1-2.2 3.2-4.7 5.1-3.2 2.1 1.7-1 5.9-3.1 7.3 2.3-1.3 7.1-1.9 7.4.8.3 2.6-4.4 3.1-7.3 1.8 2.2 1.9 3.7 6.3 1.1 7.2-2.5.9-4-3.4-3.2-6.2-.9 2.8-3.7 6.4-5.8 4.5-2-1.9.7-5.8 3.2-7.1-2.8.8-7.3.3-7-2.4.2-2.6 4.8-2.6 7.6-.6Z" /></svg>;
}

function WhyIcon({ name }: { name: string }) {
  if (name === "verified") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 12 4 4 8-9" /></svg>;
  if (name === "plan") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9" /></svg>;
  if (name === "flexible") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" /></svg>;
  return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 8.8c0 4.3-8.2 10-8.2 10s-8.2-5.7-8.2-10a4.1 4.1 0 0 1 8.2-1 4.1 4.1 0 0 1 8.2 1Z" /></svg>;
}

export default function Home() {
  return <AppShell mode="public" title="TutorLink">
    <div className="marketing-page">
      <section className="landing-hero">
        <div className="landing-inner landing-hero-container">
          <div className="landing-hero-grid">
            <div className="landing-hero-copy">
              <p className="landing-eyebrow">HỌC TẬP · RÈN LUYỆN · PHÁT TRIỂN</p>
              <h1>Kết nối đúng người,<br /><span>phát triển đúng mục tiêu.</span></h1>
              <p className="landing-lead">Khám phá dịch vụ, tìm hiểu hồ sơ người dạy và chọn người đồng hành phù hợp với điều bạn muốn đạt được.</p>
              <div className="landing-actions">
                <Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
                <Link className="button button-secondary" href="/become-a-tutor">Tôi muốn giảng dạy</Link>
              </div>
              <div className="landing-student-rating" aria-label="Đánh giá 4,9 trên 5 từ hơn 2.000 học viên">
                <div className="landing-student-avatars" aria-hidden="true"><span>M</span><span>T</span><span>L</span></div>
                <span><strong><i aria-hidden="true">★</i> 4.9/5</strong><small>từ hơn 2.000 học viên</small></span>
              </div>
            </div>
            <div className="landing-hero-art">
              <div className="landing-art-halo" aria-hidden="true" />
              <span className="landing-hero-sparks" aria-hidden="true"><i /><i /><i /></span>
              <Image className="landing-hero-person" src="/figma/auth/student-woman.png" alt="Học viên đang học trực tuyến bên máy tính" width={707} height={734} priority />
              <div className="landing-art-rating"><strong>4.9 <span aria-hidden="true">★</span></strong><small>Đánh giá trung bình</small></div>
              <div className="landing-art-trust"><strong>10K+</strong><small>Người học tin tưởng</small></div>
            </div>
          </div>
          <CatalogLinks />
        </div>
      </section>

      <section className="landing-steps-wrap">
        <div className="landing-section landing-inner">
          <SectionIntro eyebrow="3 BƯỚC KẾT NỐI" title="Bắt đầu thật đơn giản" text="Chỉ vài phút để tìm đúng người đồng hành cho mục tiêu của bạn." />
          <ol className="landing-steps">
            {learningSteps.map((step, index) => <li className={`landing-step landing-step-${step.icon}`} key={step.number}>
              <span className="landing-step-icon"><StepIcon name={step.icon} /></span>
              <div className="landing-step-copy">
                <div className="landing-step-heading"><span className="landing-step-number">{step.number}</span><h3>{step.title}</h3></div>
                <p>{step.text}</p>
              </div>
              {index < learningSteps.length - 1 ? <span className="landing-step-arrow" aria-hidden="true">→</span> : null}
            </li>)}
          </ol>
        </div>
      </section>

      <FeaturedServices />

      <section className="landing-why-section" id="why-tutorlink" aria-labelledby="landing-features-title">
        <div className="landing-inner">
          <div className="landing-why-grid">
            <div className="landing-why-copy">
              <p className="landing-eyebrow">VÌ SAO CHỌN TUTORLINK?</p>
              <h2 id="landing-features-title">Đồng hành đúng cách,<br /><span>tiến bộ mỗi ngày</span></h2>
              <p>Chúng tôi giúp bạn tìm đúng người hướng dẫn, trao đổi rõ ràng và theo dõi hành trình phát triển trong một không gian duy nhất.</p>
              <Link className="landing-why-link" href="/about">Tìm hiểu về TutorLink <span aria-hidden="true">→</span></Link>
            </div>
            <div className="landing-why-cards">
              {platformFeatures.map((feature) => <article className={`landing-why-card landing-why-card-${feature.icon}`} key={feature.icon}>
                <span className="landing-why-icon"><WhyIcon name={feature.icon} /></span>
                <h3>{feature.title}</h3>
                <p>{feature.text}</p>
              </article>)}
            </div>
          </div>
          <div className="landing-stats-bar" aria-label="Thống kê TutorLink">
            {platformStats.map((stat) => <div className="landing-stat" key={stat.label}><strong>{stat.value}</strong><span>{stat.label}</span></div>)}
          </div>
        </div>
      </section>

      <FeaturedTutors />

      <StudentTestimonials />

      <section className="landing-final-cta landing-inner" aria-label="Bắt đầu với TutorLink">
        <div>
          <p className="landing-eyebrow">SẴN SÀNG BẮT ĐẦU?</p>
          <h2>Mục tiêu của bạn xứng đáng có một người đồng hành phù hợp.</h2>
          <p>Khám phá dịch vụ và chọn bước tiếp theo theo cách của bạn.</p>
        </div>
        <div className="landing-cta-actions">
          <Link className="button landing-cta-light" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
          <Link className="button landing-cta-quiet" href="/auth/register">Tạo tài khoản</Link>
        </div>
      </section>
    </div>
  </AppShell>;
}
