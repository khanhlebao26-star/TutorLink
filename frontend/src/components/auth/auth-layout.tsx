import Image from "next/image";
import type { ReactNode } from "react";

export const authSubmitButtonClasses = "!min-h-[45px] !w-full !rounded-full !border-0 !bg-[linear-gradient(135deg,#ff1b98_0%,#ed2aa8_100%)] !px-5 !py-2.5 !text-sm !text-white !shadow-[0_9px_18px_rgba(236,34,151,.17)] hover:!bg-[linear-gradient(100deg,#f20c8c_0%,#d81e8d_100%)] hover:!shadow-[0_9px_18px_rgba(236,34,151,.17)]";

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
    "grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,455px)_minmax(0,1fr)] md:justify-items-center",
    `auth-scene-${variant}`,
    variant === "centered" ? `auth-scene-${cardSize}` : "",
    variant === "support" ? "lg:grid-cols-[minmax(0,520px)_minmax(0,440px)] lg:justify-center" : "",
    illustration ? `auth-scene-${illustration}` : "",
  ].filter(Boolean).join(" ");
  const cardWidth = variant === "support" ? "max-w-[440px] px-[22px] pt-7 sm:px-[34px] sm:pt-[34px]" : cardSize === "register" ? "max-w-[455px]" : "max-w-[435px]";

  return (
    <section className={sceneClassName} aria-labelledby="auth-title">
      <span className="auth-orb auth-orb-lavender" aria-hidden="true" />
      <span className="auth-orb auth-orb-pink" aria-hidden="true" />
      <span className="auth-orb auth-orb-bottom-left" aria-hidden="true" />
      <span className="auth-orb auth-orb-bottom-right" aria-hidden="true" />
      <span className="auth-bubble auth-bubble-left" aria-hidden="true" />
      <span className="auth-bubble auth-bubble-right" aria-hidden="true" />
      <span className="auth-spark auth-spark-one" aria-hidden="true" />
      <span className="auth-spark auth-spark-two" aria-hidden="true" />

      {illustration ? (
        <div className={`auth-illustration auth-illustration-${illustration}`} aria-hidden="true">
          {illustration === "student-man" || illustration === "student-woman" || illustration === "verification" || illustration === "forgot" ? (
            <Image
              className="auth-illustration-image"
              src={illustration === "forgot" ? "/figma/auth/forgot-password-v2.png" : `/figma/auth/${illustration === "student-man" ? "student-man" : illustration === "student-woman" ? "student-woman" : "verification-envelope"}.png`}
              alt=""
              fill
              sizes={illustration === "student-woman" ? "(max-width: 780px) 0px, 560px" : "(max-width: 780px) 0px, 520px"}
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

      <div className={`auth-card relative z-[1] col-start-1 row-start-1 w-full ${cardWidth} rounded-[14px] border border-[#dde4f2] bg-white/[.96] pb-[22px] pt-[25px] shadow-[0_18px_48px_rgba(31,38,94,.09)] sm:pb-6 sm:pt-[31px] md:col-start-2 ${variant === "support" ? "auth-card-support max-lg:col-start-1" : "px-5 sm:px-[31px]"}`}>
        <header className="auth-card-heading mb-[19px] sm:mb-[22px]">
          <h1 className="mb-2 text-[2rem] font-extrabold leading-none tracking-[-.045em] text-[#071a4c] sm:text-[38px]" id="auth-title">{title}</h1>
          {description ? <p className="mb-0 text-sm leading-[1.55] text-[#7180a4]">{description}</p> : null}
        </header>
        {children}
        {footer ? <div className="auth-card-footer mt-[14px] border-t border-[#e8edf5] pt-3">{footer}</div> : null}
      </div>
    </section>
  );
}
