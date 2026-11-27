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

const eyebrow = "mb-3 text-[.72rem] font-extrabold tracking-[.12em] text-[var(--accent-dark)]";
const sectionHeading = "mb-[35px] mx-auto max-w-[700px] text-center";
const title = "text-[clamp(1.8rem,3.3vw,2.65rem)] leading-[1.15] tracking-[-.045em]";
const mutedCopy = "text-[#7884a2] text-[.9rem] leading-[1.7]";
const container = "mx-auto max-w-[1190px] px-[clamp(22px,5vw,66px)]";

export default function AboutPage() {
  return <AppShell mode="public" title="About TutorLink">
    <div className="-mx-4 -mt-5 -mb-9 overflow-hidden text-[var(--brand)] sm:-mx-[clamp(16px,2vw,32px)] sm:-mt-8 sm:-mb-14">
      <section className="relative block min-h-0 overflow-hidden bg-[linear-gradient(112deg,#fff_0%,#fff8fc_46%,#f5f0ff_100%)] px-[22px] pt-[34px] sm:grid sm:min-h-[495px] sm:grid-cols-[minmax(0,1fr)_minmax(240px,.7fr)] sm:px-7 sm:py-[30px] md:grid-cols-[minmax(0,1.05fr)_minmax(300px,.95fr)] md:px-[max(5vw,calc((100vw-1190px)/2))]">
        <div className="pointer-events-none absolute -right-[90px] -top-[170px] h-[430px] w-[430px] rounded-full border border-[#e8d9fb]" aria-hidden="true" />
        <div className="pointer-events-none absolute -right-[177px] -top-[260px] h-[610px] w-[610px] rounded-full border border-[#e8d9fb]" aria-hidden="true" />
        <div className="relative z-[2] max-w-[720px] py-[15px] sm:py-9 sm:pb-[38px]">
          <p className={eyebrow}>VỀ TUTORLINK</p>
          <h1 className="mb-[18px] max-w-[660px] text-[clamp(2.25rem,10vw,3rem)] leading-[1.07] tracking-[-.055em] sm:text-[clamp(2.45rem,4.4vw,3.75rem)]">Kết nối người học với người dạy phù hợp.</h1>
          <p className="mb-0 max-w-[560px] text-[clamp(1rem,1.3vw,1.13rem)] leading-[1.7] text-[#697797]">TutorLink giúp bạn khám phá gia sư, huấn luyện viên và dịch vụ phù hợp — rồi cùng xây dựng lộ trình để tiến bộ.</p>
          <div className="mt-[25px] flex flex-wrap items-center gap-[14px]">
            <Link className="button button-primary min-h-[46px] px-[19px]" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link>
            <Link className="py-[10px] px-1 text-[.88rem] font-bold text-[var(--brand)]" href="/become-a-tutor">Trở thành gia sư <span className="ml-1 text-[var(--accent)]" aria-hidden="true">↗</span></Link>
          </div>
          <div className="mt-[25px] flex items-center gap-[9px] text-[.78rem] text-[#7582a1]"><SealCheckIcon className="h-[18px] w-[18px] shrink-0 text-[var(--accent)]" aria-hidden="true" weight="regular" /><span>Hồ sơ được kiểm duyệt · Trao đổi trực tiếp · Theo dõi lộ trình</span></div>
        </div>
        <div className="relative flex min-h-0 items-center justify-center bg-[radial-gradient(ellipse_at_50%_47%,#eadcff_0,#f5eaff_35%,transparent_69%)] px-0 pt-3 pb-[26px] sm:min-h-[340px] sm:p-0 md:min-h-[430px]" aria-hidden="true">
          <Image className="relative block h-auto w-full max-w-[540px] object-contain sm:max-w-[700px]" src="/about/about-hero-match.png" alt="" width={1750} height={1024} priority sizes="(max-width: 620px) 100vw, (max-width: 1100px) 45vw, 620px" />
        </div>
      </section>

      <section className={`${container} grid grid-cols-1 items-center gap-2 py-[54px] sm:grid-cols-[.9fr_1.1fr] sm:gap-[30px] sm:py-[78px] md:gap-[50px]`}>
        <div className="flex items-center gap-[18px]"><span className="flex h-[58px] w-[58px] shrink-0 items-center justify-center rounded-[18px] border border-[#ffdaed] bg-[#fff0f8] text-[var(--accent)] [&_svg]:h-7 [&_svg]:w-7"><TargetIcon aria-hidden="true" weight="regular" /></span><div><p className={eyebrow}>MỘT NỀN TẢNG, NHIỀU MỤC TIÊU</p><h2 className={`${title} m-0`}>Mỗi hành trình bắt đầu từ mục tiêu riêng.</h2></div></div>
        <p className="mt-[10px] mb-0 text-[.97rem] leading-[1.85] text-[#6e7b9b] sm:mt-5">Từ học một môn mới đến rèn luyện thể chất, mỗi người bắt đầu với điều mình muốn làm tốt hơn. TutorLink giúp bạn tìm người hướng dẫn và dịch vụ phù hợp để bắt đầu rõ ràng hơn.</p>
      </section>

      <section className="bg-[linear-gradient(120deg,#f8f5ff,#fff5fb)] py-[54px] sm:py-[76px]">
        <div className={container}>
          <div className={sectionHeading}><p className={eyebrow}>CÁCH TUTORLINK HOẠT ĐỘNG</p><h2 className={`${title} mb-0`}>Từ ý tưởng đến hành động, từng bước một.</h2><p className={`${mutedCopy} mt-3 mb-0`}>Không cần biết hết mọi thứ ngay từ đầu. Hãy bắt đầu bằng điều bạn muốn đạt được.</p></div>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {[
              { title: "Khám phá người dạy", text: "Tìm kiếm và lựa chọn gia sư phù hợp với nhu cầu, mục tiêu và lịch học của bạn.", Icon: MagnifyingGlassIcon, tone: "bg-[#fff0f8] text-[var(--accent)]" },
              { title: "Trao đổi trước khi bắt đầu", text: "Thống nhất mục tiêu, nội dung, hình thức và lịch học trước khi cùng bắt đầu.", Icon: ChatCircleDotsIcon, tone: "bg-[#edf5ff] text-[#3478ed]" },
              { title: "Theo dõi tiến độ", text: "Cùng nhìn lại quá trình học, đánh giá tiến bộ và điều chỉnh khi cần.", Icon: ChartLineUpIcon, tone: "bg-[#f2edff] text-[#8051ed]" },
            ].map(({ title: stepTitle, text, Icon, tone }) => <article className="relative min-h-0 overflow-hidden rounded-[18px] border border-[#e9e8f3] bg-white p-6 shadow-[0_12px_30px_#242b5a08] sm:min-h-[250px]" key={stepTitle}>
              <div className="relative z-[1] flex items-center gap-[13px]"><span className={`flex h-[54px] w-[54px] items-center justify-center rounded-[14px] [&_svg]:h-[26px] [&_svg]:w-[26px] ${tone}`}><Icon aria-hidden="true" weight="regular" /></span></div>
              <div className="my-[22px] h-0.5 max-w-[90px] bg-[linear-gradient(90deg,#ff0a99,#a674fb)]" aria-hidden="true" />
              <h3 className="mb-2 text-[1.05rem] leading-[1.4]">{stepTitle}</h3><p className="mb-0 text-[.84rem] leading-[1.75] text-[#74809e]">{text}</p>
            </article>)}
          </div>
        </div>
      </section>

      <section className={`${container} py-[54px] sm:py-[82px]`}>
        <div className="mx-auto mb-[34px] max-w-[690px] text-center"><p className={eyebrow}>DÀNH CHO CẢ HAI PHÍA</p><h2 className={`${title} mb-0`}>Học và dạy hiệu quả trên cùng một nền tảng.</h2><p className={`${mutedCopy} mt-3 mb-0`}>Những công cụ cần thiết để kết nối, tổ chức việc học và cùng tiến bộ.</p></div>
        <div className="grid grid-cols-1 gap-[13px] sm:grid-cols-2">
          {[
            { title: "Dành cho học viên", Icon: GraduationCapIcon, icon: "bg-[#fff0f8] text-[var(--accent)]", href: "/marketplace", action: "Khám phá dịch vụ", benefits: [{ Icon: MagnifyingGlassIcon, heading: "Tìm gia sư phù hợp", detail: "Dễ dàng tìm và lựa chọn người dạy đúng nhu cầu." }, { Icon: CalendarDotsIcon, heading: "Học linh hoạt", detail: "Chủ động sắp xếp lịch học theo thời gian của bạn." }, { Icon: ChartLineUpIcon, heading: "Theo dõi tiến bộ", detail: "Nắm bắt quá trình học và kết quả một cách rõ ràng." }] },
            { title: "Dành cho gia sư", Icon: ChalkboardTeacherIcon, icon: "bg-[#f1edff] text-[#8051ed]", href: "/become-a-tutor", action: "Trở thành gia sư", benefits: [{ Icon: SealCheckIcon, heading: "Tiếp cận học viên phù hợp", detail: "Kết nối với người đang tìm đúng chuyên môn của bạn." }, { Icon: PathIcon, heading: "Chủ động giảng dạy", detail: "Thống nhất nội dung, phương pháp và lịch học linh hoạt." }, { Icon: ChartLineUpIcon, heading: "Đồng hành và phát triển", detail: "Theo dõi hành trình dạy và nâng cao trải nghiệm giảng dạy." }] },
          ].map(({ title: audience, Icon, icon, href, action, benefits }, index) => <article className={`rounded-[20px] border border-[#e8e8f2] p-[26px] shadow-[0_14px_34px_#1b26520a] ${index === 0 ? "bg-[linear-gradient(145deg,#fff_55%,#fff8fc)]" : "bg-[linear-gradient(145deg,#fff_55%,#f8f5ff)]"}`} key={audience}>
            <div className={`mb-[17px] flex h-16 w-16 items-center justify-center rounded-[17px] [&_svg]:h-[34px] [&_svg]:w-[34px] ${icon}`}><Icon aria-hidden="true" weight="regular" /></div>
            <h3 className="mb-[18px] text-[1.15rem]">{audience}</h3>
            <ul className="m-0 grid list-none gap-[15px] p-0">{benefits.map(({ Icon: BenefitIcon, heading, detail }) => <li className="flex items-start gap-[11px]" key={heading}><BenefitIcon className={`mt-0.5 h-[19px] w-[19px] shrink-0 ${index === 0 ? "text-[var(--accent)]" : "text-[#8051ed]"}`} aria-hidden="true" weight="regular" /><span className="grid gap-[3px]"><strong className="text-[.84rem] text-[var(--brand)]">{heading}</strong><small className="text-[.76rem] leading-[1.55] text-[#74809e]">{detail}</small></span></li>)}</ul>
            <Link className="mt-[21px] inline-flex items-center gap-[7px] text-[.82rem] font-bold text-[var(--accent-dark)]" href={href}>{action} <b className="text-base" aria-hidden="true">→</b></Link>
          </article>)}
        </div>
      </section>

      <section className="bg-[#101f4a] py-[54px] text-white sm:py-[76px]">
        <div className={`${container} grid gap-[30px]`}>
          <div className="mx-auto max-w-[730px] text-center"><p className={`${eyebrow} text-[#ff87c8]`}>MINH BẠCH TRONG TỪNG KẾT NỐI</p><h2 className="text-[clamp(1.8rem,3.1vw,2.5rem)]">Rõ ràng từ lựa chọn đầu tiên đến từng bước tiến.</h2><p className="mt-3 mb-0 text-[.9rem] leading-[1.75] text-[#c4cce0]">TutorLink tập trung vào trải nghiệm học và dạy có định hướng, thuận tiện và đáng tin cậy.</p></div>
          <div className="grid grid-cols-1 gap-[11px] md:grid-cols-3 md:gap-[14px]">{[
            { Icon: SealCheckIcon, heading: "Hồ sơ được kiểm duyệt", detail: "Thông tin người dạy và người học được xác minh, rõ ràng và đáng tin cậy.", iconTone: "bg-[#fff0f8] text-[var(--accent)]" },
            { Icon: ChatCircleDotsIcon, heading: "Trao đổi trực tiếp", detail: "Kết nối và trao đổi dễ dàng ngay trên nền tảng trước khi bắt đầu.", iconTone: "bg-[#edf5ff] text-[#3478ed]" },
            { Icon: PathIcon, heading: "Theo dõi trong Program", detail: "Theo dõi tiến độ học tập và các nội dung đã thống nhất trong Program.", iconTone: "bg-[#f1edff] text-[#8051ed]" },
          ].map(({ Icon, heading, detail, iconTone }) => <article className="min-h-0 rounded-[17px] border border-white/50 bg-white p-[22px] text-[var(--brand)] md:min-h-[190px]" key={heading}><span className={`flex h-11 w-11 items-center justify-center rounded-xl [&_svg]:h-[23px] [&_svg]:w-[23px] ${iconTone}`}><Icon aria-hidden="true" weight="regular" /></span><h3 className="mt-4 mb-[7px] text-[.96rem]">{heading}</h3><p className="mb-0 text-[.8rem] leading-[1.65] text-[#74809e]">{detail}</p></article>)}
          </div>
        </div>
      </section>

      <section className={`${container} flex flex-col gap-[30px] py-[54px] sm:py-[74px] md:flex-row md:items-center md:justify-between`}>
        <div className="max-w-[620px]"><p className={eyebrow}>BƯỚC TIẾP THEO LÀ CỦA BẠN</p><h2 className={`${title} mb-0`}>Sẵn sàng bắt đầu hành trình mới?</h2><p className="mt-[11px] mb-0 text-[.88rem] leading-[1.6] text-[#7884a2]">Khám phá các dịch vụ trên TutorLink hoặc tham gia cộng đồng với vai trò gia sư.</p></div>
        <div className="flex flex-col items-stretch gap-[14px] sm:flex-row md:justify-end"><Link className="button button-primary min-h-[46px] px-[19px]" href="/marketplace">Khám phá dịch vụ <span aria-hidden="true">→</span></Link><Link className="button button-secondary min-h-[46px] px-[19px]" href="/become-a-tutor">Trở thành gia sư</Link></div>
      </section>
    </div>
  </AppShell>;
}
