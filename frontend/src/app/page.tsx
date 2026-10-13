import Image from "next/image";
import Link from "next/link";
import { AppShell } from "@/components/app-shell";
import { CatalogLinks } from "./become-a-tutor/catalog-links";

const learningSteps = [
  { number: "01", title: "Tìm lĩnh vực phù hợp", text: "Bắt đầu từ danh mục dịch vụ đang hoạt động trên TutorLink." },
  { number: "02", title: "Khám phá lựa chọn", text: "Dùng bộ lọc để tìm theo hình thức, khu vực hoặc mức phí." },
  { number: "03", title: "Xem thông tin chi tiết", text: "Đọc mô tả dịch vụ và hồ sơ người dạy trước khi chọn." },
];

const platformFeatures = [
  { icon: "01", title: "Danh mục đang hoạt động", text: "Chọn từ những lĩnh vực hiện có trên hệ thống." },
  { icon: "02", title: "Thông tin dịch vụ rõ ràng", text: "Xem mô tả, hình thức dạy, khu vực và mức phí." },
  { icon: "03", title: "Tìm theo nhu cầu", text: "Lọc dịch vụ theo cách học, địa điểm và khoảng phí." },
  { icon: "04", title: "Hồ sơ và lịch khả dụng", text: "Tìm hiểu người dạy và xem các khung giờ đã chia sẻ." },
];

function SectionIntro({ eyebrow, title, text, id }: { eyebrow: string; title: string; text?: string; id?: string }) {
  return <div className="landing-section-intro">
    <p className="landing-eyebrow">{eyebrow}</p>
    <h2 id={id}>{title}</h2>
    {text ? <p>{text}</p> : null}
  </div>;
}

export default function Home() {
  return <AppShell mode="public" title="TutorLink">
    <div className="marketing-page">
      <section className="landing-hero">
        <div className="landing-inner landing-hero-grid">
          <div className="landing-hero-copy">
            <p className="landing-eyebrow">HỌC TẬP · RÈN LUYỆN · PHÁT TRIỂN</p>
            <h1>Kết nối đúng người,<br /><span>phát triển đúng mục tiêu.</span></h1>
            <p className="landing-lead">Khám phá dịch vụ, tìm hiểu hồ sơ người dạy và chọn người đồng hành phù hợp với điều bạn muốn đạt được.</p>
            <div className="landing-actions">
              <Link className="button button-primary" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
              <Link className="button button-secondary" href="/become-a-tutor">Tôi muốn giảng dạy</Link>
            </div>
            <p className="landing-trust-line"><span aria-hidden="true">✦</span> Tìm kiếm theo danh mục, hình thức và khu vực</p>
          </div>
          <div className="landing-hero-art">
            <div className="landing-art-halo" aria-hidden="true" />
            <Image className="landing-hero-person" src="/figma/auth/student-woman.png" alt="Học viên đang học trực tuyến bên máy tính" width={707} height={734} priority />
            <div className="landing-art-note"><span className="landing-note-icon" aria-hidden="true">⌕</span><span><strong>Tìm theo cách của bạn</strong><small>Lĩnh vực · hình thức · khu vực</small></span></div>
          </div>
        </div>
      </section>

      <section className="landing-section landing-categories landing-inner" aria-labelledby="landing-categories-title">
        <SectionIntro id="landing-categories-title" eyebrow="BẮT ĐẦU TỪ ĐIỀU BẠN QUAN TÂM" title="Tìm dịch vụ theo lĩnh vực" text="Danh mục được lấy trực tiếp từ những lĩnh vực đang hoạt động trên TutorLink." />
        <CatalogLinks />
      </section>

      <section className="landing-steps-wrap">
        <div className="landing-section landing-inner">
          <SectionIntro eyebrow="3 BƯỚC ĐỂ KHÁM PHÁ" title="Bắt đầu thật đơn giản" text="Từ nhu cầu của bạn đến thông tin dịch vụ phù hợp, mọi thứ đều dễ theo dõi." />
          <ol className="landing-steps">
            {learningSteps.map((step) => <li className="landing-step" key={step.number}>
              <span className="landing-step-number">{step.number}</span>
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </li>)}
          </ol>
        </div>
      </section>

      <section className="landing-section landing-inner" id="why-tutorlink" aria-labelledby="landing-features-title">
        <SectionIntro id="landing-features-title" eyebrow="VÌ SAO CHỌN TUTORLINK?" title="Mọi thông tin cần thiết, ở đúng nơi" text="Tìm hiểu dịch vụ và người dạy với những chi tiết giúp bạn đưa ra lựa chọn phù hợp." />
        <div className="landing-feature-grid">
          {platformFeatures.map((feature) => <article className="landing-feature" key={feature.icon}>
            <span className="landing-feature-icon" aria-hidden="true">{feature.icon}</span>
            <h3>{feature.title}</h3>
            <p>{feature.text}</p>
          </article>)}
        </div>
      </section>

      <section className="landing-tutor-callout">
        <div className="landing-inner landing-tutor-grid">
          <div className="landing-tutor-art">
            <div className="landing-tutor-halo" aria-hidden="true" />
            <Image src="/figma/auth/student-man.png" alt="Người dạy chia sẻ kiến thức qua máy tính" width={784} height={858} />
          </div>
          <div className="landing-tutor-copy">
            <p className="landing-eyebrow">DÀNH CHO NGƯỜI DẠY</p>
            <h2>Chia sẻ kiến thức,<br /><span>lan tỏa giá trị.</span></h2>
            <p>Tạo hồ sơ, đăng dịch vụ và quản lý lịch khả dụng của bạn trong cùng một tài khoản TutorLink.</p>
            <ul className="landing-check-list">
              <li><span aria-hidden="true">✓</span> Giới thiệu kinh nghiệm và chuyên môn</li>
              <li><span aria-hidden="true">✓</span> Tạo dịch vụ với hình thức và mức phí riêng</li>
              <li><span aria-hidden="true">✓</span> Cập nhật lịch khả dụng theo kế hoạch</li>
            </ul>
            <div className="landing-actions">
              <Link className="button button-primary" href="/auth/register">Tạo tài khoản Tutor</Link>
              <Link className="landing-text-link" href="/profile">Mở hồ sơ của tôi <span aria-hidden="true">→</span></Link>
            </div>
          </div>
        </div>
      </section>

      <section className="landing-section landing-inner landing-faq" id="faq" aria-labelledby="landing-faq-title">
        <div className="landing-faq-intro">
          <p className="landing-eyebrow">CÂU HỎI THƯỜNG GẶP</p>
          <h2 id="landing-faq-title">Bạn muốn biết thêm?</h2>
          <p>Những thông tin hữu ích để bạn bắt đầu với TutorLink.</p>
          <Link className="landing-text-link" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
        </div>
        <div className="landing-faq-list">
          <details className="landing-faq-item">
            <summary>Tôi có thể xem dịch vụ trước khi đăng ký không?</summary>
            <p>Có. Bạn có thể xem danh mục, tìm dịch vụ và đọc thông tin công khai mà không cần đăng nhập.</p>
          </details>
          <details className="landing-faq-item">
            <summary>Làm sao để tìm dịch vụ phù hợp?</summary>
            <p>Mở Marketplace để duyệt danh mục hoặc lọc theo hình thức, khu vực và mức phí.</p>
          </details>
          <details className="landing-faq-item">
            <summary>Tôi muốn trở thành người dạy thì bắt đầu ở đâu?</summary>
            <p>Tạo tài khoản và chọn vai trò Tutor trong biểu mẫu đăng ký, sau đó hoàn thiện hồ sơ của bạn.</p>
          </details>
        </div>
      </section>

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
