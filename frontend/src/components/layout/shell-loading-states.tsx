"use client";

import "@/styles/landing.css";

export function MessagingLoadingState() {
  return <section className="message-page-loading" role="status" aria-label="Đang tải hộp thư">
    <div className="message-loading-sidebar" aria-hidden="true">
      <div className="message-loading-title"><span className="message-loading-line is-title" /><span className="message-loading-line is-short" /></div>
      <span className="message-loading-search" />
      {[0, 1, 2, 3].map((item) => <div className="message-loading-contact" key={item}>
        <span className="message-loading-avatar" />
        <span className="message-loading-contact-copy"><i /><i /></span>
      </div>)}
    </div>
    <div className="message-loading-thread" aria-hidden="true">
      <div className="message-loading-thread-heading"><span className="message-loading-avatar" /><span><i /><i /></span></div>
      <div className="message-loading-messages"><i /><i /><i /><i /></div>
      <div className="message-loading-composer"><i /><b>➤</b></div>
    </div>
    <aside className="message-loading-context" aria-hidden="true"><i /><i /><i /><i /><i /></aside>
    <div className="message-loading-status"><span className="message-loading-spinner" aria-hidden="true" /><span><strong>Đang kết nối hộp thư</strong><small>Đang xác minh phiên đăng nhập và tải cuộc trò chuyện…</small></span></div>
  </section>;
}

export function ReportsLoadingState() {
  return <section className="reports-loading-state" role="status" aria-label="Đang tải trang báo cáo">
    <div className="reports-loading-banner">
      <span className="reports-loading-spinner" aria-hidden="true" />
      <span><strong>Đang chuẩn bị báo cáo của bạn</strong><small>Đang xác minh phiên và tải thông tin an toàn…</small></span>
      <span className="reports-loading-banner-mark" aria-hidden="true">▤</span>
    </div>
    <div className="reports-loading-heading" aria-hidden="true">
      <i className="reports-skeleton reports-skeleton-eyebrow" />
      <i className="reports-skeleton reports-skeleton-title" />
      <i className="reports-skeleton reports-skeleton-subtitle" />
    </div>
    <div className="reports-loading-stats" aria-hidden="true">
      {[0, 1, 2].map((item) => <div className="reports-loading-stat" key={item}>
        <i className="reports-skeleton reports-skeleton-stat-icon" />
        <span><i className="reports-skeleton reports-skeleton-stat-label" /><i className="reports-skeleton reports-skeleton-stat-value" /></span>
      </div>)}
    </div>
    <div className="reports-loading-list" aria-hidden="true">
      <div className="reports-loading-list-head"><span><i className="reports-skeleton reports-skeleton-list-title" /><i className="reports-skeleton reports-skeleton-list-subtitle" /></span><i className="reports-skeleton reports-skeleton-filter" /></div>
      {[0, 1, 2].map((item) => <div className="reports-loading-row" key={item}>
        <i className="reports-skeleton reports-skeleton-row-icon" />
        <span><i className="reports-skeleton reports-skeleton-row-title" /><i className="reports-skeleton reports-skeleton-row-meta" /><i className="reports-skeleton reports-skeleton-row-description" /></span>
        <i className="reports-skeleton reports-skeleton-row-action" />
      </div>)}
    </div>
  </section>;
}
