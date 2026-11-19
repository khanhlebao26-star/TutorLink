"use client";

import { LogoutButton } from "@/components/auth/logout-button";
import { initials } from "@/components/layout/navigation-utils";
import type { User } from "@/lib/data/types";
import "@/styles/landing.css";
import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";

export function AccountMenu({ user }: { user: User }) {
  const [open, setOpen] = useState(false);
  const container = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const panelId = useId();

  useEffect(() => {
    if (!open) return;
    const dismissOutside = (event: PointerEvent) => {
      if (!container.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("pointerdown", dismissOutside);
    return () => document.removeEventListener("pointerdown", dismissOutside);
  }, [open]);

  return (
    <div className="account-menu" ref={container} onBlur={(event) => {
      if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
    }} onKeyDown={(event) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        trigger.current?.focus();
      }
    }}>
      <button
        ref={trigger}
        className="account-avatar"
        type="button"
        aria-label={`Tài khoản ${user.full_name}`}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((previous) => !previous)}
      >
        {initials(user.full_name)}
      </button>
      <div className="account-menu-panel" id={panelId} hidden={!open}>
        {open && <>
          <strong>{user.full_name}</strong>
          {user.role === "customer" ? <nav className="account-menu-links" aria-label="Điều hướng tài khoản học viên">
            <Link href="/learning-profile" onClick={() => setOpen(false)}>Hồ sơ học tập</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/settings/password" onClick={() => setOpen(false)}>Cài đặt</Link>
          </nav> : user.role === "tutor" ? <nav className="account-menu-links" aria-label="Điều hướng tài khoản gia sư">
            <Link href="/profile" onClick={() => setOpen(false)}>Hồ sơ của tôi</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/support" onClick={() => setOpen(false)}>Hỗ trợ</Link>
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
          </nav> : <nav className="account-menu-links" aria-label="Điều hướng tài khoản quản trị">
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
            <Link href="/settings/password" onClick={() => setOpen(false)}>Đổi mật khẩu</Link>
          </nav>}
          <LogoutButton />
        </>}
      </div>
    </div>
  );
}
