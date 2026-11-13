import { AppShell } from "@/components/layout/app-shell";
import { TutorCommunityStories } from "@/components/marketing/tutor-community-stories";
import Image from "next/image";
import Link from "next/link";

const tutorRegistration = "/auth/register?role=tutor";

const tutorSteps = [
  { icon: "person", title: "1. Đăng ký", text: "Tạo tài khoản Tutor / Coach trên TutorLink." },
  { icon: "profile", title: "2. Hoàn thiện hồ sơ", text: "Cập nhật thông tin cá nhân, chuyên môn và giới thiệu về bạn." },
  { icon: "shield", title: "3. Gửi xét duyệt", text: "Chúng tôi xem xét hồ sơ để đảm bảo chất lượng dịch vụ." },
  { icon: "plus", title: "4. Tạo dịch vụ", text: "Đăng các dịch vụ bạn cung cấp với thông tin rõ ràng." },
  { icon: "message", title: "5. Nhắn tin với khách hàng", text: "Trao đổi trực tiếp với người quan tâm." },
  { icon: "people", title: "6. Tạo Program & gửi lời mời", text: "Xây dựng chương trình phù hợp và mời người học tham gia." },
];

const tutorTools = [
  { icon: "person", tone: "pink", title: "Hồ sơ công khai", text: "Giới thiệu về bản thân, kinh nghiệm và thế mạnh của bạn đến với những người đang tìm kiếm.", action: "Tạo hồ sơ", href: "/profile" },
  { icon: "profile", tone: "blue", title: "Đăng dịch vụ", text: "Tạo và quản lý các dịch vụ bạn cung cấp một cách dễ dàng, rõ ràng và linh hoạt.", action: "Xem cách hoạt động", href: "/me/listings/new" },
  { icon: "message", tone: "purple", title: "Nhắn tin với khách hàng", text: "Trao đổi trực tiếp để hiểu rõ nhu cầu, thống nhất nội dung và phương pháp phù hợp.", action: "Khám phá tin nhắn", href: "/messages" },
  { icon: "people", tone: "pink", title: "Không gian Program", text: "Tạo chương trình và mời người học tham gia để đồng hành trong suốt quá trình học tập.", action: "Tìm hiểu Program", href: "/programs" },
];

const careerBenefits = [
  { title: "Chủ động lịch dạy và mức phí", text: "Tự quyết định thời gian, hình thức và giá trị dịch vụ." },
  { title: "Tập trung vào chất lượng chuyên môn", text: "Công cụ quản lý gọn gàng giúp bạn dành nhiều thời gian hơn cho người học." },
  { title: "Xây dựng cộng đồng riêng", text: "Kết nối lâu dài, tạo Program và theo dõi tiến bộ rõ ràng." },
];

function JourneyIcon({ name }: { name: string }) {
  if (name === "person") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="7" r="3.2" /><path d="M5.5 20c.4-4.1 2.5-6.2 6.5-6.2s6.1 2.1 6.5 6.2" /></svg>;
  if (name === "profile") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="3.5" width="14" height="17" rx="2" /><path d="M9 8h6M9 12h6M9 16h4" /></svg>;
  if (name === "shield") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 20 6v5.5c0 4.5-3.2 7.7-8 9.5-4.8-1.8-8-5-8-9.5V6l8-3Z" /><path d="m8.5 11.5 2.3 2.3 4.7-4.7" /></svg>;
  if (name === "plus") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v10M7 12h10" /></svg>;
  if (name === "message") return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 5h16v12H9l-5 3V5Z" /><path d="M8 10h.01M12 10h.01M16 10h.01" /></svg>;
  return <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3.5 20v-1.4c0-3 2-4.8 5.5-4.8s5.5 1.8 5.5 4.8V20M16 5.5a3 3 0 0 1 0 5.8M17 14c2.3.3 3.5 1.8 3.5 4.1V20" /></svg>;
}

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
              <Link className="button button-primary" href={tutorRegistration}>Tạo tài khoản Tutor <span aria-hidden="true">→</span></Link>
              <Link className="button button-secondary" href="/profile">Mở hồ sơ của tôi</Link>
            </div>
            <ul className="tutor-hero-benefits" aria-label="Lợi ích dành cho người dạy">
              <li><span aria-hidden="true">✓</span>Chủ động thời gian</li>
              <li><span aria-hidden="true">✓</span>Xây dựng thương hiệu</li>
              <li><span aria-hidden="true">✓</span>Mở rộng thu nhập</li>
            </ul>
          </div>
          <div className="landing-hero-art tutor-hero-art">
            <div className="landing-art-halo" aria-hidden="true" />
            <Image className="landing-hero-person" src="/figma/auth/student-man.png" alt="Người dạy đang làm việc bên máy tính" width={784} height={858} priority />
            <div className="landing-art-note"><span className="landing-note-icon" aria-hidden="true">✓</span><span><strong>Chủ động xây dựng hồ sơ</strong><small>Chuyên môn · dịch vụ · lịch dạy</small></span></div>
            <div className="landing-art-trust"><strong>+24</strong><small>học viên tháng này</small></div>
          </div>
        </div>
      </section>

      <section className="landing-section landing-inner tutor-journey" aria-labelledby="tutor-journey-title">
        <div className="tutor-journey-panel">
          <h2 id="tutor-journey-title">Hành trình trở thành Tutor / Coach trên TutorLink</h2>
          <ol className="tutor-journey-grid">
            {tutorSteps.map((step, index) => <li className="tutor-journey-step" key={step.title}>
              <span className="tutor-journey-icon"><JourneyIcon name={step.icon} /></span>
              {index < tutorSteps.length - 1 ? <span className="tutor-journey-arrow" aria-hidden="true">›</span> : null}
              <h3>{step.title}</h3>
              <p>{step.text}</p>
            </li>)}
          </ol>
        </div>
      </section>

      <section className="landing-section landing-inner tutor-tools-comprehensive" aria-labelledby="tutor-tools-title">
        <div className="landing-section-intro landing-section-intro-left tutor-tools-intro">
          <p className="landing-eyebrow">BỘ CÔNG CỤ TOÀN DIỆN</p>
          <h2 id="tutor-tools-title">Những gì bạn có thể làm trên TutorLink</h2>
          <p>Các công cụ cần thiết để xây dựng, kết nối và quản lý hoạt động dạy của bạn.</p>
        </div>
        <div className="tutor-tools-grid">
          {tutorTools.map((tool) => <article className={`tutor-tool-card tutor-tool-card-${tool.tone}`} key={tool.title}>
            <span className="tutor-tool-icon"><JourneyIcon name={tool.icon} /></span>
            <h3>{tool.title}</h3>
            <p>{tool.text}</p>
            <Link href={tool.href}>{tool.action} <span aria-hidden="true">→</span></Link>
          </article>)}
        </div>
      </section>

      <section className="tutor-career-section" aria-labelledby="tutor-career-title">
        <div className="landing-inner tutor-career-grid">
          <div className="tutor-career-art">
            <div className="tutor-career-halo" aria-hidden="true" />
            <div className="tutor-career-portrait tutor-career-portrait-woman">
              <Image src="/figma/auth/student-woman.png" alt="Tutor đang làm việc bên máy tính" fill sizes="(max-width: 700px) 70vw, 300px" />
            </div>
            <div className="tutor-career-portrait tutor-career-portrait-man">
              <Image src="/figma/auth/student-man.png" alt="Coach đang làm việc bên máy tính" fill sizes="(max-width: 700px) 70vw, 320px" />
            </div>
            <div className="tutor-career-rating"><strong>4.9 <span aria-hidden="true">★</span></strong><small>từ 126 đánh giá</small></div>
            <div className="tutor-career-income"><span aria-hidden="true">↗</span><small>Thu nhập tháng này<strong>12.850.000đ</strong></small></div>
          </div>
          <div className="tutor-career-copy">
            <p className="landing-eyebrow">PHÁT TRIỂN SỰ NGHIỆP</p>
            <h2 id="tutor-career-title">Biến chuyên môn thành <span>giá trị bền vững</span></h2>
            <p className="tutor-career-lead">TutorLink không chỉ giúp bạn tìm học viên. Chúng tôi tạo ra một không gian để bạn xây dựng uy tín, tổ chức chương trình và phát triển sự nghiệp theo cách riêng.</p>
            <ul className="tutor-career-benefits">
              {careerBenefits.map((benefit) => <li key={benefit.title}>
                <span className="tutor-career-check" aria-hidden="true">✓</span>
                <span><strong>{benefit.title}</strong><small>{benefit.text}</small></span>
              </li>)}
            </ul>
            <Link className="tutor-career-link" href={tutorRegistration}>Bắt đầu hành trình của bạn <span aria-hidden="true">→</span></Link>
          </div>
        </div>
      </section>

      <TutorCommunityStories />

      <section className="landing-section landing-inner landing-faq tutor-faq" id="faq" aria-labelledby="tutor-faq-title">
        <div className="landing-faq-intro">
          <p className="landing-eyebrow">CÂU HỎI THƯỜNG GẶP</p>
          <h2 id="tutor-faq-title">Bạn còn băn khoăn?</h2>
          <p>Tìm câu trả lời nhanh trước khi bắt đầu. Nếu cần thêm hỗ trợ, đội ngũ TutorLink luôn sẵn sàng đồng hành.</p>
          <Link className="landing-text-link tutor-faq-support" href="/messages">Liên hệ hỗ trợ <span aria-hidden="true">→</span></Link>
        </div>
        <div className="landing-faq-list">
          <details className="landing-faq-item">
            <summary>Ai có thể trở thành Tutor / Coach?</summary>
            <div className="tutor-faq-answer"><div><p>Bất kỳ ai có chuyên môn, kinh nghiệm thực tế và mong muốn đồng hành cùng người học đều có thể đăng ký. Hồ sơ sẽ được xét duyệt để đảm bảo chất lượng chung.</p></div></div>
          </details>
          <details className="landing-faq-item">
            <summary>TutorLink thu phí như thế nào?</summary>
            <div className="tutor-faq-answer"><div><p>Mức phí dịch vụ do người dạy thiết lập và được hiển thị trên trang dịch vụ. Nếu có khoản phí nền tảng áp dụng, thông tin sẽ được nêu rõ trước khi giao dịch.</p></div></div>
          </details>
          <details className="landing-faq-item">
            <summary>Tôi có thể tự đặt lịch và mức phí không?</summary>
            <div className="tutor-faq-answer"><div><p>Có. Bạn có thể tự tạo dịch vụ, thiết lập mức phí và cập nhật lịch khả dụng trong tài khoản TutorLink.</p></div></div>
          </details>
          <details className="landing-faq-item">
            <summary>Quá trình xét duyệt mất bao lâu?</summary>
            <div className="tutor-faq-answer"><div><p>Thời gian xét duyệt có thể thay đổi tùy hồ sơ. Bạn có thể theo dõi trạng thái trong tài khoản TutorLink; nếu cần bổ sung thông tin, đội ngũ sẽ hướng dẫn bạn.</p></div></div>
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
          <Link className="button landing-cta-light" href={tutorRegistration}>Tạo tài khoản Tutor <span aria-hidden="true">→</span></Link>
          <Link className="button landing-cta-quiet" href="/profile">Mở hồ sơ của tôi</Link>
        </div>
      </section>
    </div>
  </AppShell>;
}
