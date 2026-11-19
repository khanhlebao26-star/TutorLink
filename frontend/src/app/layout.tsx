import "@/app/globals.css";
import { SessionHintProvider } from "@/components/layout/session-hint";
import { AUTH_HINT_COOKIE } from "@/lib/auth-navigation";
import type { Metadata } from "next";
import { cookies } from "next/headers";
import type { ReactNode } from "react";

export const metadata: Metadata = {
  title: "TutorLink",
  description: "Marketplace kết nối Customer và Tutor",
};

export default async function RootLayout({ children }: { children: ReactNode }) {
  const authenticated = (await cookies()).get(AUTH_HINT_COOKIE)?.value === "1";

  return (
    <html lang="vi">
      <body>
        <SessionHintProvider authenticated={authenticated}>
          {children}
        </SessionHintProvider>
      </body>
    </html>
  );
}
