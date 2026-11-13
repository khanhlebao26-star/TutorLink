const testimonials = [
  {
    quote: "Mình tìm được cô Mai chỉ sau vài phút. Lộ trình học rõ ràng giúp mình tự tin hơn và tăng 1,5 điểm Toán sau hai tháng.",
    name: "An Nhiên",
    detail: "Học viên lớp 9",
    initials: "AN",
  },
  {
    quote: "Coach Minh theo sát từng động tác và điều chỉnh giáo án rất thực tế. Đây là lần đầu mình duy trì tập luyện đều được hơn ba tháng.",
    name: "Hoàng Tú",
    detail: "Học viên Fitness",
    initials: "HT",
  },
  {
    quote: "Mình thích cách có thể trao đổi trước khi tham gia Program. Mọi thông tin đều minh bạch nên cảm giác rất yên tâm.",
    name: "Khánh Linh",
    detail: "Học viên Pickleball",
    initials: "KL",
  },
];

export function StudentTestimonials() {
  return <section className="landing-testimonials landing-inner" aria-labelledby="landing-testimonials-title">
    <div className="landing-section-intro">
      <h2 id="landing-testimonials-title">Học viên nói gì về TutorLink?</h2>
    </div>
    <div className="landing-testimonial-grid">
      {testimonials.map((item) => <article className="landing-testimonial-card" key={item.name}>
        <span className="landing-testimonial-quote" aria-hidden="true">“</span>
        <blockquote>{item.quote}</blockquote>
        <footer>
          <span className="landing-testimonial-avatar" aria-hidden="true">{item.initials}</span>
          <span className="landing-testimonial-author"><strong>{item.name}</strong><small>{item.detail}</small></span>
          <span className="landing-testimonial-stars" role="img" aria-label="5 trên 5 sao">★★★★★</span>
        </footer>
      </article>)}
    </div>
  </section>;
}
