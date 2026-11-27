"use client";

import { LogoutButton } from "@/components/auth/logout-button";
import { initials } from "@/components/layout/navigation-utils";
import type { User } from "@/lib/data/types";
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
    <div className="account-menu relative" ref={container} onBlur={(event) => {
      if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
    }} onKeyDown={(event) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        trigger.current?.focus();
      }
    }}>
      <button
        ref={trigger}
        className="account-avatar inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-line bg-surface-soft text-xs font-extrabold text-brand transition-colors hover:bg-accent-soft aria-expanded:bg-accent-soft"
        type="button"
        aria-label={`Tài khoản ${user.full_name}`}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((previous) => !previous)}
      >
        {initials(user.full_name)}
      </button>
      <div className="account-menu-panel absolute end-0 top-[calc(100%+10px)] z-20 max-h-[min(70vh,560px)] w-[min(260px,calc(100vw-32px))] min-w-[200px] grid-cols-1 gap-2.5 overflow-y-auto [overflow-wrap:anywhere] rounded-xl border border-line bg-white p-3 shadow-[0_12px_32px_#071a4c1f] [&:not([hidden])]:grid" id={panelId} hidden={!open}>
        {open && <>
          <strong>{user.full_name}</strong>
          {user.role === "customer" ? <nav className="account-menu-links my-0.5 grid gap-0.5 border-y border-line py-1.5 [&>a]:rounded-[7px] [&>a]:px-2.5 [&>a]:py-[9px] [&>a]:text-sm [&>a]:text-foreground [&>a:hover]:bg-surface-soft [&>a:hover]:text-accent-dark" aria-label="Điều hướng tài khoản học viên">
            <Link href="/learning-profile" onClick={() => setOpen(false)}>Hồ sơ học tập</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
            <Link href="/settings/password" onClick={() => setOpen(false)}>Đổi mật khẩu</Link>
            <Link href="/support" onClick={() => setOpen(false)}>Hỗ trợ</Link>
          </nav> : user.role === "tutor" ? <nav className="account-menu-links my-0.5 grid gap-0.5 border-y border-line py-1.5 [&>a]:rounded-[7px] [&>a]:px-2.5 [&>a]:py-[9px] [&>a]:text-sm [&>a]:text-foreground [&>a:hover]:bg-surface-soft [&>a:hover]:text-accent-dark" aria-label="Điều hướng tài khoản gia sư">
            <Link href="/profile" onClick={() => setOpen(false)}>Hồ sơ của tôi</Link>
            <Link href="/me/listings" onClick={() => setOpen(false)}>Dịch vụ & Workspace</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
          </nav> : <nav className="account-menu-links my-0.5 grid gap-0.5 border-y border-line py-1.5 [&>a]:rounded-[7px] [&>a]:px-2.5 [&>a]:py-[9px] [&>a]:text-sm [&>a]:text-foreground [&>a:hover]:bg-surface-soft [&>a:hover]:text-accent-dark" aria-label="Điều hướng tài khoản quản trị">
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
            <Link href="/settings/password" onClick={() => setOpen(false)}>Đổi mật khẩu</Link>
          </nav>}
          <LogoutButton />
        </>}
      </div>
    </div>
  );
}
