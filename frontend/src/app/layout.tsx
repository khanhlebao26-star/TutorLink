import type { Metadata } from "next";
import type { ReactNode } from "react";
import "./globals.css";

export const metadata: Metadata = {
  title: "TutorLink",
  description: "Marketplace kết nối Customer và Tutor",
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return <html lang="vi"><body>{children}</body></html>;
}
