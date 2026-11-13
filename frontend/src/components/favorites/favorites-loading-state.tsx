export function FavoritesLoadingState() {
  return (
    <section className="favorites-loading" role="status" aria-label="Đang tải danh sách gia sư yêu thích">
      <div className="favorites-loading-heading">
        <span className="favorites-loading-heart" aria-hidden="true">♡</span>
        <div>
          <p className="listing-program-eyebrow">DANH SÁCH CỦA BẠN</p>
          <h1>Gia sư yêu thích</h1>
          <p>Các hồ sơ bạn đã lưu để tiện xem lại và liên hệ.</p>
        </div>
      </div>
      <div className="favorites-loading-list" aria-hidden="true">
        {[0, 1, 2].map((item) => (
          <div className="favorites-loading-card" key={item}>
            <span className="favorites-loading-avatar" />
            <span className="favorites-loading-copy"><i /><i /><i /></span>
            <span className="favorites-loading-actions"><i /><i /></span>
          </div>
        ))}
      </div>
      <span className="favorites-loading-status"><i aria-hidden="true" />Đang chuẩn bị danh sách hồ sơ đã lưu…</span>
    </section>
  );
}
