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

export const dynamic = "force-static";
export const revalidate = 300;

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
  return <div className="landing-section-intro mx-auto mb-[34px] max-w-[700px] text-center">
    <p className="landing-eyebrow mb-[14px] text-[.72rem] font-extrabold leading-[1.5] tracking-[.1em] text-accent-dark">{eyebrow}</p>
    <h2 className="mb-3 text-[clamp(1.8rem,3.2vw,2.65rem)] leading-[1.14] tracking-[-.04em]" id={id}>{title}</h2>
    {text ? <p className="m-0 leading-[1.7] text-[#7581a0]">{text}</p> : null}
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
    <FeaturedListingProvider><div className="marketing-page overflow-hidden">
      <section className="landing-hero overflow-hidden bg-[radial-gradient(ellipse_at_0_0,#f0ecff_0_17%,transparent_17.2%),radial-gradient(ellipse_at_100%_12%,#ffeafa_0_19%,transparent_19.2%),linear-gradient(180deg,#fff_0%,#fff_72%,#fcfaff_100%)]">
        <div className="landing-inner landing-hero-container mx-auto max-w-[1250px] px-5 pb-[22px] sm:px-[clamp(20px,5vw,64px)]">
          <div className="landing-hero-grid grid min-h-0 grid-cols-1 gap-0 sm:min-h-[465px] sm:grid-cols-[minmax(0,1fr)_minmax(260px,.85fr)] sm:gap-6 md:grid-cols-[minmax(0,1fr)_minmax(300px,.95fr)] md:gap-[clamp(24px,5vw,68px)]">
            <div className="landing-hero-copy relative z-[2] py-11 sm:pb-8 sm:pt-[54px]">
              <p className="landing-eyebrow">HỌC TẬP · RÈN LUYỆN · PHÁT TRIỂN</p>
              <h1>Kết nối đúng người,<br /><span>phát triển đúng mục tiêu.</span></h1>
              <p className="landing-lead">Khám phá dịch vụ, tìm hiểu hồ sơ người dạy và chọn người đồng hành phù hợp với điều bạn muốn đạt được.</p>
              <div className="landing-actions mt-[25px] flex flex-wrap items-center gap-3 max-[420px]:flex-col max-[420px]:items-stretch">
                <Link className="button button-primary" href="/marketplace" prefetch={true}>Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
                <Link className="button button-secondary" href="/become-a-tutor">Tôi muốn giảng dạy</Link>
              </div>
            </div>
            <div className="landing-hero-art relative flex min-h-[275px] items-end justify-center sm:min-h-[390px] md:min-h-[425px]">
              <div className="landing-art-halo" aria-hidden="true" />
              <span className="landing-hero-sparks" aria-hidden="true"><i /><i /><i /></span>
              <Image className="landing-hero-person relative z-[1] h-auto w-full max-w-full object-contain sm:w-[min(100%,380px)] md:w-[min(100%,500px)]" src="/figma/auth/student-woman.png" alt="Học viên đang học trực tuyến bên máy tính" width={707} height={734} sizes="(max-width: 700px) 100vw, (max-width: 1100px) 45vw, 500px" priority />
            </div>
          </div>
          <CatalogLinks />
        </div>
      </section>

      <section className="landing-steps-wrap mx-auto my-0 w-[calc(100%-32px)] max-w-[1250px] rounded-[22px] bg-[linear-gradient(115deg,#fff3f9,#f7f1ff)] p-3.5 sm:p-[18px]">
        <div className="landing-section landing-inner mx-auto max-w-[1250px] px-5 py-1.5 sm:px-[clamp(20px,5vw,64px)] sm:pb-0 sm:pt-[9px]">
          <SectionIntro eyebrow="3 BƯỚC KẾT NỐI" title="Bắt đầu thật đơn giản" text="Chỉ vài phút để tìm đúng người đồng hành cho mục tiêu của bạn." />
          <ol className="landing-steps grid list-none grid-cols-1 gap-x-0 rounded-2xl border border-white bg-white/85 px-2 py-[5px] sm:grid-cols-3 sm:gap-x-7 sm:px-2 sm:py-3">
            {learningSteps.map((step, index) => <li className={`landing-step landing-step-${step.icon} relative grid min-w-0 grid-cols-[48px_minmax(0,1fr)] items-center gap-[13px] px-[14px] py-[17px] text-left sm:px-5 sm:py-[18px]`} key={step.number}>
              <span className="landing-step-icon inline-flex h-12 w-12 items-center justify-center rounded-full bg-[#ffebf5] text-accent"><StepIcon name={step.icon} /></span>
              <div className="landing-step-copy min-w-0">
                <div className="landing-step-heading flex items-center gap-[7px]"><span className="landing-step-number inline-flex h-5 w-5 flex-[0_0_20px] items-center justify-center rounded-md bg-accent text-[.68rem] font-extrabold text-white">{step.number}</span><h3 className="m-0 text-[.8rem] leading-[1.25]">{step.title}</h3></div>
                <p className="mb-0 mt-[5px] text-[.67rem] leading-[1.4] text-[#7783a1]">{step.text}</p>
              </div>
              {index < learningSteps.length - 1 ? <span className="landing-step-arrow absolute right-[-14px] top-1/2 z-[1] hidden -translate-y-1/2 translate-x-1/2 text-[1.35rem] font-light text-[#a0acc8] sm:block" aria-hidden="true">→</span> : null}
            </li>)}
          </ol>
        </div>
      </section>

      <FeaturedServices />

      <section className="landing-why-section relative bg-[linear-gradient(112deg,#fbf8ff_0%,#fff4fa_100%)] pt-[52px] sm:pt-[clamp(62px,7vw,88px)]" id="why-tutorlink" aria-labelledby="landing-features-title">
        <div className="landing-inner mx-auto max-w-[1250px] px-5 sm:px-[clamp(20px,5vw,64px)]">
          <div className="landing-why-grid grid grid-cols-1 items-center gap-7 md:gap-8 lg:grid-cols-[minmax(330px,.82fr)_minmax(0,1.18fr)] lg:gap-[clamp(42px,6vw,82px)]">
            <div className="landing-why-copy max-w-[440px]">
              <p className="landing-eyebrow">VÌ SAO CHỌN TUTORLINK?</p>
              <h2 id="landing-features-title">Đồng hành đúng cách,<br /><span>tiến bộ mỗi ngày</span></h2>
              <p>Chúng tôi giúp bạn tìm đúng người hướng dẫn, trao đổi rõ ràng và theo dõi hành trình phát triển trong một không gian duy nhất.</p>
              <Link className="landing-why-link" href="/about">Tìm hiểu về TutorLink <span aria-hidden="true">→</span></Link>
            </div>
            <div className="landing-why-cards grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-[14px]">
              {platformFeatures.map((feature) => <article className={`landing-why-card landing-why-card-${feature.icon} min-h-[150px] rounded-2xl border border-[#eee6f4] bg-white/85 p-[17px] sm:min-h-[168px] sm:p-[21px_22px]`} key={feature.icon}>
                <span className="landing-why-icon inline-flex h-[38px] w-[38px] items-center justify-center rounded-xl bg-[#ffe7f4] text-accent [&>svg]:h-[21px] [&>svg]:w-[21px]"><WhyIcon name={feature.icon} /></span>
                <h3 className="mb-[5px] mt-3 text-[.85rem] leading-[1.35]">{feature.title}</h3>
                <p className="m-0 text-[.7rem] leading-[1.55] text-[#7884a1]">{feature.text}</p>
              </article>)}
            </div>
          </div>
        </div>
      </section>

      <DeferredFeaturedTutors />

      <section className="landing-final-cta landing-inner mx-auto flex max-w-[1250px] flex-col items-stretch gap-6 px-5 pb-[52px] sm:flex-row sm:items-center sm:justify-between sm:px-[clamp(20px,5vw,64px)]" aria-label="Bắt đầu với TutorLink">
        <div>
          <p className="landing-eyebrow">SẴN SÀNG BẮT ĐẦU?</p>
          <h2>Mục tiêu của bạn xứng đáng có một người đồng hành phù hợp.</h2>
          <p>Khám phá dịch vụ và chọn bước tiếp theo theo cách của bạn.</p>
        </div>
        <div className="landing-cta-actions flex flex-1 flex-wrap gap-3 sm:flex-none">
          <Link className="button landing-cta-light" href="/marketplace" prefetch={true}>Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
          <Link className="button landing-cta-quiet" href="/auth/register">Tạo tài khoản</Link>
        </div>
      </section>
    </div></FeaturedListingProvider>
  </AppShell>;
}
