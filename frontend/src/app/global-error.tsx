/* eslint-disable @next/next/no-location-assign-relative-destination -- Root-layout recovery needs a full document navigation. */
'use client';
export default function GlobalError({ reset }: { reset: () => void }) {
  return <html lang="vi"><body style={{fontFamily:'system-ui',background:'#f8f7ff',color:'#071a4c',padding:'10vh 8vw'}}><main role="alert"><p>TUTORLINK · SỰ CỐ HỆ THỐNG</p><h1>Không thể mở ứng dụng</h1><p>Vui lòng thử lại sau ít phút.</p><button onClick={reset}>Thử lại</button><p><button onClick={() => window.location.assign("/")}>Về trang chủ</button></p></main></body></html>;
}
