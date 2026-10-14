import Image from "next/image";
import Link from "next/link";
import { AppShell } from "@/components/app-shell";

const tutorSteps = [
  { title: "Tạo tài khoản", text: "Chọn vai trò Tutor khi đăng ký trên TutorLink.", href: "/auth/register", action: "Đăng ký" },
  { title: "Hoàn thiện hồ sơ", text: "Giới thiệu kinh nghiệm và chuyên môn của bạn.", href: "/profile", action: "Mở hồ sơ" },
  { title: "Tạo dịch vụ", text: "Mô tả dịch vụ, hình thức dạy và mức phí.", href: "/me/listings/new", action: "Quản lý dịch vụ" },
  { title: "Cập nhật lịch", text: "Chia sẻ những khung giờ bạn có thể nhận học viên.", href: "/me/availability", action: "Quản lý lịch" },
];

const tutorTools = [
  { icon: "01", title: "Hồ sơ người dạy", text: "Giới thiệu bản thân, kinh nghiệm và lĩnh vực chuyên môn." },
  { icon: "02", title: "Dịch vụ của bạn", text: "Tạo và quản lý các dịch vụ bạn muốn chia sẻ." },
  { icon: "03", title: "Lịch khả dụng", text: "Cập nhật khung giờ để người học tham khảo." },
  { icon: "04", title: "Tự chủ cách dạy", text: "Thiết lập hình thức và khu vực phù hợp với dịch vụ." },
];

export default function BecomeATutorPage() {
  return <AppShell mode="public" title="Dành cho người dạy">
    <div className="marketing-page tutor-marketing-page">
      <section className="landing-hero tutor-hero">
        <div className="landing-inner landing-hero-grid">
          <div className="landing-hero-copy">
            <p className="landing-eyebrow">DÀNH CHO NGƯỜI DẠY</p>
            <h1>Chia sẻ kiến thức,<br /><span>lan tỏa giá trị.</span></h1>
            <p className="landing-lead">Đưa chuyên môn của bạn đến gần hơn với những người đang tìm kiếm sự hướng dẫn phù hợp.</p>
            <div className="landing-actions">
              <Link className="button button-primary" href="/auth/register">Tạo tài khoản Tutor <span aria-hidden="true">→</span></Link>
              <Link className="button button-secondary" href="/profile">Mở hồ sơ của tôi</Link>
            </div>
            <p className="landing-trust-line"><span aria-hidden="true">✦</span> Hồ sơ, dịch vụ và lịch khả dụng trong một tài khoản</p>
          </div>
          <div className="landing-hero-art tutor-hero-art">
            <div className="landing-art-halo" aria-hidden="true" />
            <Image className="landing-hero-person" src="/figma/auth/student-man.png" alt="Người dạy đang làm việc bên máy tính" width={784} height={858} priority />
            <div className="landing-art-note"><span className="landing-note-icon" aria-hidden="true">✓</span><span><strong>Chủ động xây dựng hồ sơ</strong><small>Chuyên môn · dịch vụ · lịch dạy</small></span></div>
          </div>
        </div>
      </section>

      <section className="landing-section landing-inner tutor-journey" aria-labelledby="tutor-journey-title">
        <div className="landing-section-intro landing-section-intro-left">
          <p className="landing-eyebrow">HÀNH TRÌNH CỦA NGƯỜI DẠY</p>
          <h2 id="tutor-journey-title">Bắt đầu và quản lý thật dễ dàng</h2>
          <p>Từng bước dẫn bạn từ tài khoản đầu tiên đến những dịch vụ và lịch dạy của riêng mình.</p>
        </div>
        <ol className="tutor-journey-grid">
          {tutorSteps.map((step, index) => <li className="tutor-journey-step" key={step.title}>
            <span className="landing-step-number">0{index + 1}</span>
            <h3>{step.title}</h3>
            <p>{step.text}</p>
            <Link href={step.href}>{step.action} <span aria-hidden="true">→</span></Link>
          </li>)}
        </ol>
      </section>

      <section className="landing-section tutor-tools-section">
        <div className="landing-inner">
          <div className="landing-section-intro landing-section-intro-left">
            <p className="landing-eyebrow">CÔNG CỤ CỦA BẠN</p>
            <h2>Bạn có thể làm gì trên TutorLink?</h2>
            <p>Các công cụ hỗ trợ bạn giới thiệu chuyên môn và quản lý hoạt động giảng dạy.</p>
          </div>
          <div className="landing-feature-grid tutor-tool-grid">
            {tutorTools.map((tool) => <article className="landing-feature" key={tool.icon}>
              <span className="landing-feature-icon" aria-hidden="true">{tool.icon}</span>
              <h3>{tool.title}</h3>
              <p>{tool.text}</p>
            </article>)}
          </div>
        </div>
      </section>

      <section className="landing-tutor-value">
        <div className="landing-inner landing-tutor-grid">
          <div className="landing-tutor-art">
            <div className="landing-tutor-halo" aria-hidden="true" />
            <Image src="/figma/auth/student-woman.png" alt="Người dạy chia sẻ kiến thức qua máy tính" width={707} height={734} />
          </div>
          <div className="landing-tutor-copy">
            <p className="landing-eyebrow">PHÁT TRIỂN CÙNG CHUYÊN MÔN</p>
            <h2>Biến chuyên môn thành <span>giá trị bền vững.</span></h2>
            <p>TutorLink giúp bạn giới thiệu dịch vụ theo cách rõ ràng và giữ các thông tin dạy học được sắp xếp trong một nơi.</p>
            <ul className="landing-check-list">
              <li><span aria-hidden="true">✓</span> Chọn hình thức và khu vực cung cấp dịch vụ</li>
              <li><span aria-hidden="true">✓</span> Cập nhật mô tả và mức phí của từng dịch vụ</li>
              <li><span aria-hidden="true">✓</span> Tự quản lý khung giờ khả dụng</li>
            </ul>
            <Link className="landing-text-link" href="/profile">Bắt đầu hoàn thiện hồ sơ <span aria-hidden="true">→</span></Link>
          </div>
        </div>
      </section>

      <section className="landing-section landing-inner landing-faq tutor-faq" id="faq" aria-labelledby="tutor-faq-title">
        <div className="landing-faq-intro">
          <p className="landing-eyebrow">CÂU HỎI THƯỜNG GẶP</p>
          <h2 id="tutor-faq-title">Bắt đầu từ đâu?</h2>
          <p>Tìm hiểu các bước cơ bản để giới thiệu dịch vụ của bạn.</p>
          <Link className="landing-text-link" href="/auth/register">Tạo tài khoản <span aria-hidden="true">→</span></Link>
        </div>
        <div className="landing-faq-list">
          <details className="landing-faq-item">
            <summary>Làm sao để đăng ký làm Tutor?</summary>
            <p>Mở trang đăng ký, chọn vai trò Tutor và tạo tài khoản. Sau đó bạn có thể hoàn thiện hồ sơ của mình.</p>
          </details>
          <details className="landing-faq-item">
            <summary>Tôi có thể giới thiệu những thông tin nào?</summary>
            <p>Hồ sơ giúp bạn giới thiệu kinh nghiệm và chuyên môn. Mỗi dịch vụ có phần mô tả, hình thức, khu vực và mức phí.</p>
          </details>
          <details className="landing-faq-item">
            <summary>Tôi quản lý dịch vụ và lịch ở đâu?</summary>
            <p>Dùng các mục Dịch vụ của tôi và Lịch khả dụng trong khu vực tài khoản TutorLink.</p>
          </details>
        </div>
      </section>

      <section className="landing-final-cta landing-inner" aria-label="Bắt đầu hành trình Tutor">
        <div>
          <p className="landing-eyebrow">BẮT ĐẦU NGAY HÔM NAY</p>
          <h2>Chuyên môn của bạn có thể mở ra hành trình mới.</h2>
          <p>Tạo hồ sơ và chia sẻ những dịch vụ bạn muốn cung cấp.</p>
        </div>
        <div className="landing-cta-actions">
          <Link className="button landing-cta-light" href="/auth/register">Tạo tài khoản Tutor <span aria-hidden="true">→</span></Link>
          <Link className="button landing-cta-quiet" href="/profile">Mở hồ sơ của tôi</Link>
        </div>
      </section>
    </div>
  </AppShell>;
}
