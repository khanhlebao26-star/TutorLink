import "@/app/globals.css";
import { MockRoleSwitcher } from "@/components/layout/mock-role-switcher";
import { SessionHintProvider } from "@/components/layout/session-hint";
import type { Metadata } from "next";
import { cookies } from "next/headers";
import type { ReactNode } from "react";

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
