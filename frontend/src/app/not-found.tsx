import Link from 'next/link';
export default function NotFound() {
  return <main className="error-page"><p className="eyebrow">TUTORLINK · 404</p><h1>Không tìm thấy trang</h1><p>Đường dẫn có thể đã thay đổi hoặc trang không còn tồn tại.</p><div className="button-row"><Link className="button" href="/">Về trang chủ</Link><Link className="button button-outline" href="/marketplace">Khám phá dịch vụ</Link></div></main>;
}
