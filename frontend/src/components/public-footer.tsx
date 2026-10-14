import Link from "next/link";

export function PublicFooter() {
  return <footer className="public-footer">
    <div className="public-footer-inner">
      <div className="public-footer-brand">
        <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
          <span>Tutor</span><strong>Link</strong><span className="brand-star" aria-hidden="true">✦</span>
          <small>Vietnam</small>
        </Link>
        <p>Kết nối người học và người dạy trên cùng một hành trình phát triển.</p>
      </div>
      <nav className="public-footer-nav" aria-label="Liên kết TutorLink">
        <div>
          <h2>Khám phá</h2>
          <Link href="/marketplace">Tất cả dịch vụ</Link>
          <Link href="/#why-tutorlink">Về TutorLink</Link>
          <Link href="/#faq">Câu hỏi thường gặp</Link>
        </div>
        <div>
          <h2>Dành cho người dạy</h2>
          <Link href="/become-a-tutor">Bắt đầu giảng dạy</Link>
          <Link href="/auth/register">Tạo tài khoản</Link>
          <Link href="/profile">Hồ sơ của tôi</Link>
        </div>
        <div>
          <h2>Tài khoản</h2>
          <Link href="/auth/login">Đăng nhập</Link>
          <Link href="/auth/register">Đăng ký</Link>
        </div>
      </nav>
    </div>
    <div className="public-footer-bottom">
      <p>© TutorLink Vietnam</p>
      <p>Kết nối để cùng phát triển.</p>
    </div>
  </footer>;
}
