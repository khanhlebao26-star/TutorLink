'use client';
import Link from 'next/link';
export default function ErrorPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return <main className="error-page" role="alert"><p className="eyebrow">TUTORLINK · SỰ CỐ HỆ THỐNG</p><h1>Trang đang gặp sự cố</h1><p>Bạn có thể thử tải lại hoặc quay về trang chủ.</p>{error.digest ? <p>Mã hỗ trợ: <code>{error.digest}</code></p> : null}<div className="button-row"><button className="button" onClick={reset}>Thử lại</button><Link className="button button-outline" href="/">Về trang chủ</Link></div></main>;
}
