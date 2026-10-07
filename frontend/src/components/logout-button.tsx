"use client";

import { useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { logout } from "@/lib/data/services";

export function LogoutButton() {
  const pathname = usePathname();
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);

  if (pathname.startsWith("/auth/")) return null;

  async function handleLogout() {
    setSubmitting(true);
    try {
      await logout();
    } catch {
      // A missing session is already logged out; continue to the login screen.
    } finally {
      router.push("/auth/login");
      setSubmitting(false);
    }
  }

  return (
    <button className="button button-secondary" type="button" onClick={handleLogout} disabled={submitting}>
      {submitting ? "Đang đăng xuất…" : "Đăng xuất"}
    </button>
  );
}
