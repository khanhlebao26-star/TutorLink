import type { ReactNode } from "react";
import Image from "next/image";
import "./auth.css";

type AuthIllustration = "student-man" | "student-woman" | "verification" | "forgot" | "reset";

export function AuthLayout({
  title,
  description,
  children,
  footer,
  variant = "centered",
  cardSize = "login",
  illustration,
  tagline,
  secondaryTagline,
}: {
  title: string;
  description?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  variant?: "centered" | "support";
  cardSize?: "login" | "register";
  illustration?: AuthIllustration;
  tagline?: ReactNode;
  secondaryTagline?: ReactNode;
}) {
  const sceneSize = variant === "support" ? "support" : cardSize;
  const sceneClassName = [
    "auth-scene",
    `auth-scene-${variant}`,
    variant === "centered" ? `auth-scene-${cardSize}` : "",
    illustration ? `auth-scene-${illustration}` : "",
  ].filter(Boolean).join(" ");

  return (
    <section className={sceneClassName} aria-labelledby="auth-title">
      <span className="auth-orb auth-orb-lavender" aria-hidden="true" />
      <span className="auth-orb auth-orb-pink" aria-hidden="true" />
      <span className="auth-orb auth-orb-bottom-left" aria-hidden="true" />
      <span className="auth-orb auth-orb-bottom-right" aria-hidden="true" />
      <span className="auth-spark auth-spark-one" aria-hidden="true" />
      <span className="auth-spark auth-spark-two" aria-hidden="true" />

      {illustration ? (
        <div className={`auth-illustration auth-illustration-${illustration}`} aria-hidden="true">
          {illustration === "student-man" || illustration === "student-woman" || illustration === "verification" ? (
            <Image
              className="auth-illustration-image"
              src={`/figma/auth/${illustration === "student-man" ? "student-man" : illustration === "student-woman" ? "student-woman" : "verification-envelope"}.png`}
              alt=""
              fill
              sizes="(max-width: 780px) 0px, 520px"
              priority
            />
          ) : (
            <>
              <span className="auth-art-halo" />
              <span className="auth-art-paper" />
              <span className="auth-art-lock" />
              <span className="auth-art-spark" />
            </>
          )}
        </div>
      ) : null}
      {tagline ? <p className={`auth-slogan auth-slogan-${sceneSize}`}>{tagline}</p> : null}
      {secondaryTagline ? <p className="auth-slogan auth-slogan-secondary">{secondaryTagline}</p> : null}

      <div className={`auth-card ${variant === "support" ? "auth-card-support" : ""}`}>
        <header className="auth-card-heading">
          <h1 id="auth-title">{title}</h1>
          {description ? <p>{description}</p> : null}
        </header>
        {children}
        {footer ? <div className="auth-card-footer">{footer}</div> : null}
      </div>
    </section>
  );
}
