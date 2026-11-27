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
  return <footer className="public-footer">
    <div className="public-footer-inner">
      <div className="public-footer-brand">
        <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
          <span>Tutor</span><strong>Link</strong><span className="brand-star" aria-hidden="true">✦</span>
          <small>Vietnam</small>
        </Link>
        <p>Kết nối đúng người, phát triển đúng mục tiêu. Cùng bạn tiến bộ mỗi ngày.</p>
        <div className="public-footer-socials" role="group" aria-label="Mạng xã hội TutorLink">
          {socialNetworks.map((name) => <span className="public-footer-social" key={name} role="img" aria-label={name} title={name}><SocialIcon name={name} /></span>)}
        </div>
      </div>
      <nav className="public-footer-nav" aria-label="Liên kết TutorLink">
        <div>
          <h2>Khám phá</h2>
          <Link href="/marketplace">Tất cả dịch vụ</Link>
          <Link href="/marketplace?category=education">Giáo dục</Link>
          <Link href="/marketplace?category=fitness">Fitness</Link>
          <Link href="/marketplace?category=sports">Thể thao</Link>
        </div>
        <div>
          <h2>Về TutorLink</h2>
          <Link href="/about">Về chúng tôi</Link>
          <Link href="/become-a-tutor">Trở thành Tutor</Link>
          <span>Câu chuyện cộng đồng · Sắp ra mắt</span>
          <span>Tuyển dụng · Sắp ra mắt</span>
        </div>
        <div id="support">
          <h2>Hỗ trợ</h2>
          <Link href="/support">Trung tâm hỗ trợ</Link>
          <span>Điều khoản sử dụng · Sắp ra mắt</span>
          <span>Chính sách bảo mật · Sắp ra mắt</span>
          <span>Liên hệ · Sắp ra mắt</span>
        </div>
        <div className="public-footer-newsletter">
          <h2>Nhận tin mới</h2>
          <p>Cập nhật dịch vụ và câu chuyện truyền cảm hứng từ cộng đồng.</p>
          <span className="public-footer-coming-soon">Đăng ký nhận tin sẽ sớm khả dụng</span>
        </div>
      </nav>
    </div>
    <div className="public-footer-bottom">
      <p>© 2026 TutorLink Vietnam. All rights reserved.</p>
      <p>Made with <span aria-label="love" role="img">♥</span> for lifelong learners.</p>
    </div>
  </footer>;
}
