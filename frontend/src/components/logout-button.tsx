"use client";

import { useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import { logout } from "@/lib/data/services";
import { ApiClientError, describeApiError } from "@/lib/api/client";
import { Button } from "./ui";

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
    <div className="logout-area">
      <Button variant="outline" type="button" onClick={handleLogout} disabled={submitting} aria-busy={submitting}>
        {submitting ? "Đang đăng xuất…" : "Đăng xuất"}
      </Button>
      {error ? <p className="logout-error" role="alert">{error}</p> : null}
    </div>
  );
}
