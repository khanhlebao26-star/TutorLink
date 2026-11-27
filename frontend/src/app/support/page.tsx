import { AppShell } from "@/components/layout/app-shell";
import { MagnifyingGlassIcon } from "@phosphor-icons/react/dist/ssr/MagnifyingGlass";
import Link from "next/link";
import type { ReactNode } from "react";

const commonQuestions = [
  {
    question: "Làm cách nào để cập nhật thông tin tài khoản?",
    answer: <>Mở menu avatar, chọn <Link href="/settings">Cài đặt</Link> để cập nhật tên hiển thị và xem thông tin tài khoản. Email đăng nhập hiện được dùng để nhận các thông báo liên quan.</>,
  },
  {
    question: "Tôi không thấy tin nhắn hoặc lời mời của mình ở đâu?",
    answer: <>Bạn có thể mở <Link href="/messages">Tin nhắn</Link> để xem cuộc trò chuyện, hoặc vào <Link href="/programs">Workspace chương trình</Link> để tiếp tục theo dõi Program. Hãy đăng nhập đúng tài khoản nếu các mục này chưa hiển thị.</>,
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
    answer: <>Bạn có thể theo dõi các chương trình và lời mời trong <Link href="/programs">Workspace gia sư</Link>, sau đó tiếp tục trao đổi tại <Link href="/messages">Tin nhắn</Link>.</>,
  },
  {
    question: "Tôi xem báo cáo của mình ở đâu?",
    answer: <>Mở <Link href="/reports">Báo cáo của tôi</Link> để xem các báo cáo liên quan đến hoạt động tài khoản.</>,
  },
];

const eyebrow = "mb-3 text-[.72rem] font-extrabold tracking-[.1em] text-[var(--accent-dark)]";

function QuestionList({ items }: { items: { question: string; answer: ReactNode }[] }) {
  return <div className="grid gap-[10px]">
    {items.map(({ question, answer }) => <details className="group rounded-xl border border-[var(--line)] bg-white px-[17px] transition-[border-color,box-shadow] duration-[180ms] open:border-[#f0bfdd] open:shadow-[0_8px_22px_#19275508] motion-reduce:transition-none" key={question}>
      <summary className="flex min-h-[58px] cursor-pointer list-none items-center justify-between text-[.87rem] font-bold text-[var(--brand)] [&::-webkit-details-marker]:hidden"><span>{question}</span><span className="ml-4 inline-flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full bg-[#fff0f8] text-[1.1rem] text-[var(--accent)] transition-transform duration-200 group-open:rotate-45" aria-hidden="true">+</span></summary>
      <div className="grid grid-rows-[1fr]"><p className="m-0 px-[34px] pb-[17px] text-[.83rem] leading-[1.7] text-[#7381a3] [&_a]:font-semibold [&_a]:text-[var(--accent-dark)] [&_a]:underline [&_a]:underline-offset-2">{answer}</p></div>
    </details>)}
  </div>;
}

export default function SupportPage() {
  return <AppShell mode="public" title="Hỗ trợ">
    <div className="-mx-[clamp(16px,2vw,32px)] -mt-8 -mb-14 overflow-hidden">
      <section className="relative grid min-h-[300px] place-items-center overflow-hidden bg-[radial-gradient(ellipse_at_0_0,#f0ecff_0_18%,transparent_18.2%),radial-gradient(ellipse_at_100%_0,#ffe9f7_0_20%,transparent_20.2%),linear-gradient(120deg,#fff_5%,#fffaff_55%,#fff_100%)] px-6 py-[52px] sm:min-h-[330px]">
        <span className="pointer-events-none absolute -left-[110px] top-[90px] h-[170px] w-[170px] rounded-full border border-[#f3e5f5] sm:left-[8%] sm:top-[105px] sm:h-[270px] sm:w-[270px]" aria-hidden="true" />
        <span className="pointer-events-none absolute -right-[110px] -top-[120px] h-[210px] w-[210px] rounded-full bg-[linear-gradient(135deg,#fff0f8aa,#f1edffbb)] sm:right-[8%] sm:-top-[190px] sm:h-[340px] sm:w-[340px]" aria-hidden="true" />
        <div className="relative z-[1] mx-auto max-w-[780px] text-center">
          <p className={eyebrow}>TRUNG TÂM HỖ TRỢ TUTORLINK</p>
          <h1 className="mb-[14px] text-[clamp(2.25rem,4.2vw,3.6rem)]">Chúng tôi sẵn sàng <span className="text-[var(--accent)]">đồng hành</span></h1>
          <p className="m-0 text-[1.05rem] text-[#7180a4]">Tìm câu trả lời và hướng dẫn dành cho cả học viên lẫn gia sư.</p>
          <a className="mt-[25px] inline-flex items-center gap-3 rounded-[11px] bg-[var(--accent)] px-[19px] py-3 text-[.9rem] font-bold text-white shadow-[0_10px_22px_#f414982b] transition-[transform,box-shadow] duration-150 hover:-translate-y-0.5 hover:text-white hover:shadow-[0_13px_26px_#f4149838] motion-reduce:transition-none" href="#cau-hoi">Xem câu hỏi thường gặp <span className="text-[1.1rem]" aria-hidden="true">↓</span></a>
        </div>
      </section>

      <div className="mx-auto max-w-[1250px] px-[clamp(20px,5vw,64px)] pb-20">
        <nav className="relative z-[2] mt-[-18px] grid grid-cols-1 gap-[10px] sm:mt-[-27px] sm:grid-cols-3 sm:gap-4" aria-label="Chủ đề hỗ trợ">
          {[
            { href: "#chung", icon: "?", tone: "bg-[#fff0f8] text-[var(--accent)]", heading: "Tài khoản & sử dụng", detail: "Cài đặt, tin nhắn và thông báo" },
            { href: "#hoc-vien", icon: <MagnifyingGlassIcon weight="regular" />, tone: "bg-[#edf5ff] text-[#3474ff]", heading: "Dành cho học viên", detail: "Tìm dịch vụ và học cùng gia sư" },
            { href: "#gia-su", icon: "✦", tone: "bg-[#f2edff] text-[#8052f6]", heading: "Dành cho gia sư", detail: "Hồ sơ, dịch vụ và lịch dạy" },
          ].map((item) => <a className="grid min-h-[78px] grid-cols-[46px_minmax(0,1fr)_18px] items-center gap-[13px] rounded-[15px] border border-[#eee7f4] bg-white p-[17px] shadow-[0_12px_30px_#18264f0d] transition-[border-color,transform,box-shadow] duration-150 hover:-translate-y-0.5 hover:border-[#f4b8dc] hover:text-[var(--brand)] hover:shadow-[0_15px_32px_#18264f14] motion-reduce:transition-none sm:min-h-24" href={item.href} key={item.href}>
            <span className={"inline-flex h-11 w-11 items-center justify-center rounded-[13px] text-[1.3rem] font-bold [&_svg]:h-5 [&_svg]:w-5 " + item.tone} aria-hidden="true">{item.icon}</span><span className="grid gap-[3px]"><strong className="text-[.88rem]">{item.heading}</strong><small className="text-[.72rem] leading-[1.4] text-[#7784a7]">{item.detail}</small></span><b className="text-[1.1rem] text-[var(--accent)]" aria-hidden="true">→</b>
          </a>)}
        </nav>

        <section className="pt-[58px] sm:pt-[74px]" id="cau-hoi" aria-labelledby="support-faq-title">
          <header className="mb-[34px] text-center sm:mb-12">
            <p className={eyebrow}>CÂU HỎI THƯỜNG GẶP</p>
            <h2 className="mb-[9px] text-[clamp(1.75rem,3vw,2.5rem)] tracking-[-.04em]" id="support-faq-title">Bạn cần trợ giúp điều gì?</h2>
            <p className="m-0 text-[#7784a7]">Chọn chủ đề phù hợp để xem hướng dẫn nhanh.</p>
          </header>

          <section className="mx-auto mb-[39px] grid max-w-[1050px] scroll-mt-[105px] grid-cols-1 gap-4 sm:mb-[52px] sm:grid-cols-[minmax(210px,.6fr)_minmax(0,1.4fr)] sm:gap-7" id="chung">
            <div className="flex items-start gap-3 pt-[5px] sm:gap-[15px]"><span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#fff0f8] text-[.78rem] font-extrabold text-[var(--accent)]">01</span><div><h3 className="mt-0.5 mb-1.5 text-[1.08rem] tracking-[-.02em]">Tài khoản &amp; sử dụng TutorLink</h3><p className="m-0 text-[.8rem] leading-[1.55] text-[#7784a7]">Thông tin hữu ích cho mọi thành viên.</p></div></div>
            <QuestionList items={commonQuestions} />
          </section>

          <section className="mx-auto mb-[39px] grid max-w-[1050px] scroll-mt-[105px] grid-cols-1 gap-4 sm:mb-[52px] sm:grid-cols-[minmax(210px,.6fr)_minmax(0,1.4fr)] sm:gap-7" id="hoc-vien">
            <div className="flex items-start gap-3 pt-[5px] sm:gap-[15px]"><span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#fff0f8] text-[.78rem] font-extrabold text-[var(--accent)]">02</span><div><h3 className="mt-0.5 mb-1.5 text-[1.08rem] tracking-[-.02em]">Học viên</h3><p className="m-0 text-[.8rem] leading-[1.55] text-[#7784a7]">Tìm người đồng hành và quản lý hành trình học.</p></div></div>
            <QuestionList items={learnerQuestions} />
          </section>

          <section className="mx-auto mb-[39px] grid max-w-[1050px] scroll-mt-[105px] grid-cols-1 gap-4 sm:mb-[52px] sm:grid-cols-[minmax(210px,.6fr)_minmax(0,1.4fr)] sm:gap-7" id="gia-su">
            <div className="flex items-start gap-3 pt-[5px] sm:gap-[15px]"><span className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#fff0f8] text-[.78rem] font-extrabold text-[var(--accent)]">03</span><div><h3 className="mt-0.5 mb-1.5 text-[1.08rem] tracking-[-.02em]">Gia sư &amp; Coach</h3><p className="m-0 text-[.8rem] leading-[1.55] text-[#7784a7]">Xây dựng hồ sơ và tổ chức hoạt động dạy.</p></div></div>
            <QuestionList items={tutorQuestions} />
          </section>
        </section>

        <section className="relative mt-[55px] flex flex-col items-start gap-[19px] overflow-hidden rounded-[18px] bg-[linear-gradient(110deg,#101f56,#5831b3_68%,#d9219b)] p-6 text-white sm:mt-[78px] sm:flex-row sm:items-center sm:justify-between sm:gap-7 sm:p-[clamp(24px,4vw,42px)]" aria-labelledby="support-contact-title">
          <span className="pointer-events-none absolute -right-[11%] -top-[190px] h-[270px] w-[270px] rounded-full border border-white/15" aria-hidden="true" />
          <div className="relative z-[1]"><p className={eyebrow + " text-[#ffb7df]"}>CẦN HỖ TRỢ THÊM?</p><h2 className="mb-2 text-[clamp(1.35rem,2.5vw,1.9rem)]" id="support-contact-title">Hãy bắt đầu một cuộc trò chuyện</h2><p className="m-0 text-[.88rem] text-[#e0e5fa]">Đăng nhập để nhắn tin và cung cấp thêm thông tin về vấn đề bạn gặp phải.</p></div>
          <Link className="relative z-[1] flex shrink-0 items-center rounded-[11px] border border-white bg-white px-[19px] py-[13px] text-[.86rem] font-bold text-[var(--accent-dark)] transition-transform duration-150 hover:-translate-y-0.5 hover:text-[#bf0870] motion-reduce:transition-none" href="/messages">Đi đến tin nhắn <span className="ml-[9px]" aria-hidden="true">→</span></Link>
        </section>
      </div>
    </div>
  </AppShell>;
}
