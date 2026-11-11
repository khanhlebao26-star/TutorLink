import Link from "next/link";

function SocialIcon({ name }: { name: string }) {
  if (name === "Facebook") return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.4 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.1H7.2V13H10v8h3.4Z" /></svg>;
  if (name === "Instagram") return <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4" /><circle cx="12" cy="12" r="3.5" /><circle className="public-footer-social-dot" cx="17.5" cy="6.8" r=".9" /></svg>;
  if (name === "YouTube") return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8.2a2.8 2.8 0 0 0-2-2C17.3 5.7 12 5.7 12 5.7s-5.3 0-7 .5a2.8 2.8 0 0 0-2 2A29 29 0 0 0 2.5 12a29 29 0 0 0 .5 3.8 2.8 2.8 0 0 0 2 2c1.7.5 7 .5 7 .5s5.3 0 7-.5a2.8 2.8 0 0 0 2-2 29 29 0 0 0 .5-3.8 29 29 0 0 0-.5-3.8Z" /><path className="public-footer-social-play" d="m10 15.2 5.2-3.2L10 8.8v6.4Z" /></svg>;
  return <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.9 3h2.8l-6.1 7 7.2 11h-5.6l-4.4-6.7L6.9 21H4l6.5-7.5L3.6 3h5.8l4 6.1L18.9 3Zm-1 16h1.6L8.3 4.9H6.6L17.9 19Z" /></svg>;
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
          <Link href="/#why-tutorlink">Về chúng tôi</Link>
          <Link href="/become-a-tutor">Trở thành Tutor</Link>
          <span>Câu chuyện cộng đồng</span>
          <span>Tuyển dụng</span>
        </div>
        <div>
          <h2>Hỗ trợ</h2>
          <span>Trung tâm trợ giúp</span>
          <span>Điều khoản sử dụng</span>
          <span>Chính sách bảo mật</span>
          <span>Liên hệ</span>
        </div>
        <div className="public-footer-newsletter">
          <h2>Nhận tin mới</h2>
          <p>Cập nhật dịch vụ và câu chuyện truyền cảm hứng từ cộng đồng.</p>
          <div className="public-footer-newsletter-form">
            <label className="landing-sr-only" htmlFor="footer-newsletter-email">Email của bạn</label>
            <input id="footer-newsletter-email" type="email" placeholder="Email của bạn" />
            <button type="button" aria-label="Đăng ký nhận tin"><span aria-hidden="true">→</span></button>
          </div>
        </div>
      </nav>
    </div>
    <div className="public-footer-bottom">
      <p>© 2026 TutorLink Vietnam. All rights reserved.</p>
      <p>Made with <span aria-label="love" role="img">♥</span> for lifelong learners.</p>
    </div>
  </footer>;
}
