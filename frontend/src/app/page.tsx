import { CatalogLinks } from "@/app/become-a-tutor/catalog-links";
import { AppShell } from "@/components/layout/app-shell";
import { FeaturedServices } from "@/components/marketing/featured-services";
import { FeaturedListingProvider } from "@/components/marketing/featured-listing-provider";
import { DeferredFeaturedTutors } from "@/components/marketing/deferred-featured-tutors";
import { ChatTextIcon } from "@phosphor-icons/react/dist/ssr/ChatText";
import { ClockIcon } from "@phosphor-icons/react/dist/ssr/Clock";
import { GraduationCapIcon } from "@phosphor-icons/react/dist/ssr/GraduationCap";
import { HeadsetIcon } from "@phosphor-icons/react/dist/ssr/Headset";
import { MagnifyingGlassIcon } from "@phosphor-icons/react/dist/ssr/MagnifyingGlass";
import { PathIcon } from "@phosphor-icons/react/dist/ssr/Path";
import { SealCheckIcon } from "@phosphor-icons/react/dist/ssr/SealCheck";
import Image from "next/image";
import Link from "next/link";

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

function SectionIntro({ eyebrow, title, text, id }: { eyebrow: string; title: string; text?: string; id?: string }) {
  return <div className="landing-section-intro">
    <p className="landing-eyebrow">{eyebrow}</p>
    <h2 id={id}>{title}</h2>
    {text ? <p>{text}</p> : null}
  </div>;
}

function StepIcon({ name }: { name: string }) {
  if (name === "search") return <MagnifyingGlassIcon weight="regular" aria-hidden="true" />;
  if (name === "chat") return <ChatTextIcon weight="regular" aria-hidden="true" />;
  return <GraduationCapIcon weight="regular" aria-hidden="true" />;
}

function WhyIcon({ name }: { name: string }) {
  if (name === "verified") return <SealCheckIcon weight="regular" aria-hidden="true" />;
  if (name === "plan") return <PathIcon weight="regular" aria-hidden="true" />;
  if (name === "flexible") return <ClockIcon weight="regular" aria-hidden="true" />;
  return <HeadsetIcon weight="regular" aria-hidden="true" />;
}

export default function Home() {
  return <AppShell mode="public" title="TutorLink">
    <FeaturedListingProvider><div className="marketing-page">
      <section className="landing-hero">
        <div className="landing-inner landing-hero-container">
          <div className="landing-hero-grid">
            <div className="landing-hero-copy">
              <p className="landing-eyebrow">HỌC TẬP · RÈN LUYỆN · PHÁT TRIỂN</p>
              <h1>Kết nối đúng người,<br /><span>phát triển đúng mục tiêu.</span></h1>
              <p className="landing-lead">Khám phá dịch vụ, tìm hiểu hồ sơ người dạy và chọn người đồng hành phù hợp với điều bạn muốn đạt được.</p>
              <div className="landing-actions">
                <Link className="button button-primary" href="/marketplace" prefetch={true}>Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
                <Link className="button button-secondary" href="/become-a-tutor">Tôi muốn giảng dạy</Link>
              </div>
            </div>
            <div className="landing-hero-art">
              <div className="landing-art-halo" aria-hidden="true" />
              <span className="landing-hero-sparks" aria-hidden="true"><i /><i /><i /></span>
              <Image className="landing-hero-person" src="/figma/auth/student-woman.png" alt="Học viên đang học trực tuyến bên máy tính" width={707} height={734} sizes="(max-width: 700px) 100vw, (max-width: 1100px) 45vw, 500px" priority />
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
        </div>
      </section>

      <DeferredFeaturedTutors />

      <section className="landing-final-cta landing-inner" aria-label="Bắt đầu với TutorLink">
        <div>
          <p className="landing-eyebrow">SẴN SÀNG BẮT ĐẦU?</p>
          <h2>Mục tiêu của bạn xứng đáng có một người đồng hành phù hợp.</h2>
          <p>Khám phá dịch vụ và chọn bước tiếp theo theo cách của bạn.</p>
        </div>
        <div className="landing-cta-actions">
          <Link className="button landing-cta-light" href="/marketplace" prefetch={true}>Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
          <Link className="button landing-cta-quiet" href="/auth/register">Tạo tài khoản</Link>
        </div>
      </section>
    </div></FeaturedListingProvider>
  </AppShell>;
}
