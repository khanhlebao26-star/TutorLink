"use client";

import { useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { logout } from "@/lib/data/services";
import { ApiClientError, describeApiError } from "@/lib/api/client";

export function LogoutButton() {
  const pathname = usePathname();
  const router = useRouter();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (pathname.startsWith("/auth/")) return null;

  async function handleLogout() {
    setSubmitting(true);
    setError(null);
    try {
      await logout();
      router.push("/auth/login");
    } catch (reason) {
      if (reason instanceof ApiClientError && reason.status === 401) {
        router.push("/auth/login");
      } else {
        setError(describeApiError(reason));
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <button className="button button-secondary" type="button" onClick={handleLogout} disabled={submitting}>
        {submitting ? "Đang đăng xuất…" : "Đăng xuất"}
      </button>
      {error ? <p role="alert">{error}</p> : null}
    </div>
  );
}
