import Link from "next/link";
import { FacebookLogoIcon } from "@phosphor-icons/react/dist/ssr/FacebookLogo";
import { InstagramLogoIcon } from "@phosphor-icons/react/dist/ssr/InstagramLogo";
import { XLogoIcon } from "@phosphor-icons/react/dist/ssr/XLogo";
import { YoutubeLogoIcon } from "@phosphor-icons/react/dist/ssr/YoutubeLogo";

function SocialIcon({ name }: { name: string }) {
  if (name === "Facebook") return <FacebookLogoIcon weight="regular" aria-hidden="true" />;
  if (name === "Instagram") return <InstagramLogoIcon weight="regular" aria-hidden="true" />;
  if (name === "YouTube") return <YoutubeLogoIcon weight="regular" aria-hidden="true" />;
  return <XLogoIcon weight="regular" aria-hidden="true" />;
}

const socialNetworks = ["Facebook", "Instagram", "YouTube", "X"];

export function PublicFooter() {
  return <footer className="public-footer bg-[#07143b] px-[clamp(20px,7vw,92px)] pb-5 pt-12 text-white">
    <div className="public-footer-inner mx-auto grid max-w-[1288px] grid-cols-1 gap-8 pb-[38px] sm:grid-cols-[minmax(250px,1.05fr)_minmax(0,3.3fr)] sm:gap-12">
      <div className="public-footer-brand">
        <Link className="brand text-white [&>small]:text-[#d4d9ef]" href="/" aria-label="TutorLink Việt Nam">
          <span>Tutor</span><strong>Link</strong><span className="brand-star" aria-hidden="true">✦</span>
          <small>Vietnam</small>
        </Link>
        <p className="mb-[18px] mt-5 max-w-80 text-[.8rem] leading-[1.65] text-[#adb8d8]">Kết nối đúng người, phát triển đúng mục tiêu. Cùng bạn tiến bộ mỗi ngày.</p>
        <div className="public-footer-socials flex items-center gap-[9px]" role="group" aria-label="Mạng xã hội TutorLink">
          {socialNetworks.map((name) => <span className="public-footer-social inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#1c2b51] text-white [&>svg]:h-4 [&>svg]:w-4" key={name} role="img" aria-label={name} title={name}><SocialIcon name={name} /></span>)}
        </div>
      </div>
      <nav className="public-footer-nav grid grid-cols-2 gap-6 text-[.77rem] text-[#adb8d8] [&_a]:w-fit [&_a]:text-[#adb8d8] [&_a]:transition-colors [&_a:hover]:text-[#ff65b8] [&_div>span]:w-fit [&_div>span]:text-[#adb8d8] sm:grid-cols-[.85fr_1.1fr_.95fr_1.5fr] sm:gap-7" aria-label="Liên kết TutorLink">
        <div className="grid content-start gap-3">
          <h2 className="mb-1 mt-[3px] text-[.79rem] tracking-[.02em] text-white">Khám phá</h2>
          <Link href="/marketplace">Tất cả dịch vụ</Link>
          <Link href="/marketplace?category=education">Giáo dục</Link>
          <Link href="/marketplace?category=fitness">Fitness</Link>
          <Link href="/marketplace?category=sports">Thể thao</Link>
        </div>
        <div className="grid content-start gap-3">
          <h2 className="mb-1 mt-[3px] text-[.79rem] tracking-[.02em] text-white">Về TutorLink</h2>
          <Link href="/about">Về chúng tôi</Link>
          <Link href="/become-a-tutor">Trở thành Tutor</Link>
          <span>Câu chuyện cộng đồng · Sắp ra mắt</span>
          <span>Tuyển dụng · Sắp ra mắt</span>
        </div>
        <div className="grid content-start gap-3" id="support">
          <h2 className="mb-1 mt-[3px] text-[.79rem] tracking-[.02em] text-white">Hỗ trợ</h2>
          <Link href="/support">Trung tâm hỗ trợ</Link>
          <span>Điều khoản sử dụng · Sắp ra mắt</span>
          <span>Chính sách bảo mật · Sắp ra mắt</span>
          <span>Liên hệ · Sắp ra mắt</span>
        </div>
        <div className="public-footer-newsletter grid content-start gap-3">
          <h2 className="mb-1 mt-[3px] text-[.79rem] tracking-[.02em] text-white">Nhận tin mới</h2>
          <p className="mb-2 mt-px text-[.77rem] leading-[1.65] text-[#adb8d8]">Cập nhật dịch vụ và câu chuyện truyền cảm hứng từ cộng đồng.</p>
          <span className="inline-flex rounded-[10px] border border-white/10 bg-[#1c2b51] px-3.5 py-3 text-[.72rem] leading-[1.5] text-[#c4cce1]">Đăng ký nhận tin sẽ sớm khả dụng</span>
        </div>
      </nav>
    </div>
    <div className="public-footer-bottom mx-auto flex max-w-[1288px] flex-col justify-between gap-[5px] border-t border-white/10 pt-4 text-[.7rem] text-[#8e9abb] sm:flex-row sm:items-center sm:gap-4">
      <p className="m-0">© 2026 TutorLink Vietnam. All rights reserved.</p>
      <p className="m-0">Made with <span className="text-[#ff269f]" aria-label="love" role="img">♥</span> for lifelong learners.</p>
    </div>
  </footer>;
}
