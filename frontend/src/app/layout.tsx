import type { Metadata } from "next";
import { cookies } from "next/headers";
import type { ReactNode } from "react";
import { SessionHintProvider } from "@/components/session-hint";
import { MockRoleSwitcher } from "@/components/mock-role-switcher";
import "./globals.css";

export const metadata: Metadata = {
  title: "TutorLink",
  description: "Marketplace kết nối Customer và Tutor",
};

export default async function RootLayout({ children }: { children: ReactNode }) {
  const authenticated = (await cookies()).get("tutorlink-auth")?.value === "1";

  return (
    <html lang="vi">
      <body>
        <SessionHintProvider authenticated={authenticated}>
          <MockRoleSwitcher />
          {children}
        </SessionHintProvider>
      </body>
    </html>
  );
}
