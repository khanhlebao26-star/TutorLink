"use client";

import Link from "next/link";
import Image from "next/image";
import { usePathname } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { describeApiError, ApiClientError, setAuthHint } from "@/lib/api/client";
import { getCurrentUser } from "@/lib/data/services";
import type { Role, User } from "@/lib/data/types";
import { LogoutButton } from "./logout-button";
import { useSessionHint } from "./session-hint";
import { Button, StatePanel } from "./ui";

const roleLabels: Record<Role, string> = {
  customer: "Học viên",
  tutor: "Gia sư",
  admin: "Quản trị",
};

type Session =
  | { status: "loading" }
  | { status: "guest" }
  | { status: "error"; message: string }
  | { status: "authenticated"; user: User };

type AppShellProps = {
  children: ReactNode;
  role?: Role;
  title?: string;
  mode?: "public" | "auth";
};

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

function MenuIcon() {
  return <span className="menu-icon" aria-hidden="true"><span /><span /><span /></span>;
}

function initials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase();
}

function LocaleDisplay() {
  return (
    <span className="locale-display">
      <Image src="/figma/auth/globe.svg" alt="" width={20} height={20} />
      Tiếng Việt, VND
    </span>
  );
}

export function AppShell({ children, role, title = "TutorLink", mode }: AppShellProps) {
  const pathname = usePathname();
  const isAuthRoute = pathname.startsWith("/auth/");
  const publicMode = mode === "public" || mode === "auth" || isAuthRoute;
  const sessionHint = useSessionHint();
  const [session, setSession] = useState<Session>({ status: "loading" });
  const [menuOpen, setMenuOpen] = useState(false);
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    let current = true;
    if (publicMode) return;

    getCurrentUser().then((user) => {
      setAuthHint(true);
      if (current) setSession({ status: "authenticated", user });
    }).catch((reason: unknown) => {
      if (!current) return;
      if (reason instanceof ApiClientError && reason.status === 401) {
        setAuthHint(false);
        setSession({ status: "guest" });
      } else {
        setSession({ status: "error", message: describeApiError(reason) });
      }
    });

    return () => { current = false; };
  }, [pathname, publicMode, retry]);

  const requiredRole: Role | undefined = pathname === "/admin"
    ? role ?? "admin"
    : pathname === "/profile"
      ? role ?? "tutor"
      : undefined;
  const user = session.status === "authenticated" ? session.user : null;
  const roleMismatch = Boolean(user && requiredRole && user.role !== requiredRole);
  const protectedPage = Boolean(requiredRole);
  const needsLogin = protectedPage && session.status === "guest";
  const needsSessionError = protectedPage && session.status === "error";
  const needsRole = Boolean(protectedPage && user && roleMismatch);
  const needsLoading = protectedPage && session.status === "loading";
  // Protected routes must keep their account shell while /me is loading.
  // Rendering the public shell for this intermediate state causes a full
  // header/sidebar replacement as soon as the session request resolves.
  const showPublicFrame = publicMode || (!user && !protectedPage && !sessionHint);
  const showPublicActions = publicMode || session.status !== "loading";

  const accessContent = needsLoading ? (
    <StatePanel kind="loading" message="Đang xác minh phiên đăng nhập…" />
  ) : needsLogin ? (
    <section className="card access-state">
      <p className="eyebrow">Khu vực tài khoản</p>
      <h1>Vui lòng đăng nhập để tiếp tục</h1>
      <p className="muted">Bạn cần phiên đăng nhập phù hợp để mở trang này.</p>
      <Link className="button button-primary" href="/auth/login">Đăng nhập</Link>
    </section>
  ) : needsSessionError ? (
    <section className="card access-state" role="alert">
      <p className="eyebrow">Chưa xác minh được phiên</p>
      <h1>Không thể kiểm tra tài khoản</h1>
      <p className="muted">{session.status === "error" ? session.message : "Hãy thử lại."}</p>
      <div className="button-row">
        <Button variant="secondary" onClick={() => {
          setSession({ status: "loading" });
          setRetry((value) => value + 1);
        }}>Thử lại</Button>
        <Link className="button button-primary" href="/auth/login">Đăng nhập</Link>
      </div>
    </section>
  ) : needsRole ? (
    <section className="card access-state" role="alert">
      <p className="eyebrow">Không đủ quyền</p>
      <h1>Trang này dành cho {roleLabels[requiredRole!]}</h1>
      <p className="muted">Tài khoản hiện tại thuộc vai trò {roleLabels[user!.role].toLowerCase()}.</p>
      <Link className="button button-primary" href={user!.role === "admin" ? "/admin" : user!.role === "tutor" ? "/profile" : "/marketplace"}>
        Tiếp tục đến khu vực của bạn
      </Link>
    </section>
  ) : children;

  if (showPublicFrame) {
    const authFrame = mode === "auth" || isAuthRoute;
    return (
      <div className={`app-frame public-frame${authFrame ? " auth-frame" : ""}`}>
        <header className="topbar public-topbar">
          <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
            <span>Tutor</span><strong>Link</strong><span className="brand-star">✦</span>
            <small>Vietnam</small>
          </Link>
          <button
            className="menu-toggle"
            type="button"
            aria-label={menuOpen ? "Đóng điều hướng" : "Mở điều hướng"}
            aria-expanded={menuOpen}
            aria-controls="public-navigation"
            onClick={() => setMenuOpen((open) => !open)}
          >
            <MenuIcon />
          </button>
          <nav id="public-navigation" className={`public-nav${menuOpen ? " is-open" : ""}`} aria-label="Điều hướng chính">
            <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined} onClick={() => setMenuOpen(false)}>Khám phá dịch vụ</Link>
            {showPublicActions ? <Link href="/auth/register" aria-current={pathname === "/auth/register" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Dành cho người dạy</Link> : null}
          </nav>
          <div className="public-actions">
            <LocaleDisplay />
            {showPublicActions ? <>
              <Link className="public-login" href="/auth/login">Đăng nhập</Link>
              <Link className="button button-primary" href="/auth/register">Đăng ký</Link>
            </> : null}
          </div>
        </header>
        <main className={`page-content${authFrame ? " auth-page-content" : ""}`}>
          {protectedPage ? accessContent : children}
        </main>
      </div>
    );
  }

  const activeRole = user?.role ?? role ?? "customer";
  const navItems = activeRole === "admin"
    ? [{ href: "/admin", label: "Duyệt hồ sơ Tutor" }]
    : activeRole === "tutor"
      ? [{ href: "/profile", label: "Hồ sơ Tutor" }, { href: "/marketplace", label: "Khám phá dịch vụ" }]
      : [{ href: "/marketplace", label: "Tìm dịch vụ" }];

  return (
    <div className={`app-frame authenticated-frame${pathname === "/profile" ? " profile-shell" : ""}`}>
      <header className="topbar app-topbar">
        <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
          <span>Tutor</span><strong>Link</strong><span className="brand-star">✦</span>
          <small>Vietnam</small>
        </Link>
        <button
          className="menu-toggle"
          type="button"
          aria-label={menuOpen ? "Đóng menu tài khoản" : "Mở menu tài khoản"}
          aria-expanded={menuOpen}
          aria-controls="app-sidebar"
          onClick={() => setMenuOpen((open) => !open)}
        >
          <MenuIcon />
        </button>
        <nav className="app-topbar-links" aria-label="Điều hướng chính">
          <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined}>Khám phá dịch vụ</Link>
          <LocaleDisplay />
        </nav>
        <div className="topbar-meta">
          {user ? (
            <span className="account-avatar" role="img" aria-label={`Tài khoản ${user.full_name}`} title={user.full_name}>
              {initials(user.full_name)}
            </span>
          ) : null}
        </div>
      </header>
      <div className="app-layout">
        <aside id="app-sidebar" className={`app-sidebar${menuOpen ? " is-open" : ""}`} aria-label={`Menu ${roleLabels[activeRole]}`}>
          <p className="sidebar-label">{roleLabels[activeRole]}</p>
          <nav id="app-sidebar-navigation" className="sidebar-nav" aria-label="Điều hướng tài khoản">
            {navItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                aria-current={isActive(pathname, item.href) ? "page" : undefined}
                onClick={() => setMenuOpen(false)}
              >
                {item.label}
              </Link>
            ))}
          </nav>
          <div className="sidebar-bottom">{user ? <LogoutButton /> : null}</div>
        </aside>
        <main className="app-main">
          <div className="page-content">
            {pathname !== "/profile" ? <div className="eyebrow">{roleLabels[activeRole]} · {title}</div> : null}
            {requiredRole ? needsRole || needsLogin || needsSessionError || needsLoading ? accessContent : children : children}
          </div>
        </main>
      </div>
    </div>
  );
}
