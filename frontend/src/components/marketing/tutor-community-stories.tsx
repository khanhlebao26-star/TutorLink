import Image from "next/image";

const stories = [
  {
    category: "Giáo dục",
    tone: "education",
    image: "/figma/auth/student-woman.png",
    imageAlt: "Gia sư đang hướng dẫn học viên bên máy tính",
    quote: "TutorLink giúp mình xây dựng một lộ trình rõ ràng cho từng học viên và chủ động hơn rất nhiều trong công việc.",
    name: "Nguyễn Mai",
    initials: "NM",
    role: "Gia sư Toán",
    location: "Hà Nội",
    rating: "4.9",
  },
  {
    category: "Fitness",
    tone: "fitness",
    image: "/figma/auth/student-man.png",
    imageAlt: "Huấn luyện viên thể hình đang làm việc bên máy tính",
    quote: "Mình có thể theo dõi từng Program, trao đổi trực tiếp và duy trì sự gắn kết với học viên ở cùng một nơi.",
    name: "Trần Minh",
    initials: "TM",
    role: "Personal Trainer",
    location: "TP.HCM",
    rating: "4.8",
  },
  {
    category: "Thể thao",
    tone: "sports",
    image: "/figma/auth/student-woman.png",
    imageAlt: "Huấn luyện viên đang chuẩn bị cho buổi học",
    quote: "Hồ sơ xác thực giúp học viên tin tưởng ngay từ đầu. Lịch dạy của mình đã ổn định chỉ sau hai tháng.",
    name: "Lê Thảo",
    initials: "LT",
    role: "Pickleball Coach",
    location: "Đà Nẵng",
    rating: "5.0",
  },
];

function LocationIcon() {
  return <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 6.7c0 3.3-5 7.3-5 7.3S3 10 3 6.7a5 5 0 1 1 10 0Z" /><circle cx="8" cy="6.5" r="1.6" /></svg>;
}

export function TutorCommunityStories() {
  return <section className="landing-section landing-inner tutor-community" aria-labelledby="tutor-community-title">
    <div className="landing-section-intro">
      <p className="landing-eyebrow">CÂU CHUYỆN CỘNG ĐỒNG</p>
      <h2 id="tutor-community-title">Người dạy phát triển cùng TutorLink</h2>
      <p>Mỗi chuyên môn là một hành trình riêng, nhưng tất cả đều bắt đầu từ một kết nối đúng.</p>
    </div>
    <div className="tutor-community-grid">
      {stories.map((story) => <article className={`tutor-community-card tutor-community-card-${story.tone}`} key={story.name}>
        <div className="tutor-community-image">
          <Image src={story.image} alt={story.imageAlt} fill sizes="(max-width: 700px) 100vw, (max-width: 1000px) 50vw, 33vw" />
          <span>{story.category}</span>
        </div>
        <div className="tutor-community-body">
          <blockquote>“{story.quote}”</blockquote>
          <footer className="tutor-community-meta">
            <span className="tutor-community-avatar" aria-hidden="true">{story.initials}</span>
            <span className="tutor-community-person">
              <strong>{story.name}<span className="tutor-community-verified" aria-label="Đã xác minh">✓</span></strong>
              <span className="tutor-community-details">
                <span className="tutor-community-role">{story.role}</span>
                <span className="tutor-community-location"><LocationIcon />{story.location}</span>
              </span>
            </span>
            <span className="tutor-community-rating" aria-label={`${story.rating} sao trên 5`}><span aria-hidden="true">★</span> {story.rating}</span>
          </footer>
        </div>
      </article>)}
    </div>
  </section>;
}
