"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Suspense, createContext, useCallback, useContext, useEffect, useId, useRef, useState, type ReactNode } from "react";
import { dataMode, describeApiError, ApiClientError, request, setAuthHint } from "@/lib/api/client";
import { getCurrentUser } from "@/lib/data/services";
import { getMarketplaceCatalog } from "@/lib/data/marketplace";
import { favoriteUpdateEvent, getFavoriteTutors } from "@/lib/data/favorites";
import type { ApiEnvelope, Role, User } from "@/lib/data/types";
import { useAsyncData } from "@/lib/hooks/use-async-data";
import { LogoutButton } from "./logout-button";
import { PublicFooter } from "./public-footer";
import { FavoritesLoadingState } from "./favorites-loading-state";
import { useSessionHint } from "./session-hint";
import { Button, StatePanel } from "./ui";
import "@/app/landing.css";

const ActorContext=createContext<User|null>(null);
export const useActor=()=>useContext(ActorContext);

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

let cachedSession: Session | null = null;

type AppShellProps = {
  children: ReactNode;
  role?: Role;
  title?: string;
  mode?: "public" | "auth";
  hideSidebar?: boolean;
};

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

function MenuIcon() {
  return <span className="menu-icon" aria-hidden="true"><span /><span /><span /></span>;
}

function MessagingLoadingState() {
  return <section className="message-page-loading" role="status" aria-label="Đang tải hộp thư">
    <div className="message-loading-sidebar" aria-hidden="true">
      <div className="message-loading-title"><span className="message-loading-line is-title" /><span className="message-loading-line is-short" /></div>
      <span className="message-loading-search" />
      {[0, 1, 2, 3].map((item) => <div className="message-loading-contact" key={item}>
        <span className="message-loading-avatar" />
        <span className="message-loading-contact-copy"><i /><i /></span>
      </div>)}
    </div>
    <div className="message-loading-thread" aria-hidden="true">
      <div className="message-loading-thread-heading"><span className="message-loading-avatar" /><span><i /><i /></span></div>
      <div className="message-loading-messages"><i /><i /><i /><i /></div>
      <div className="message-loading-composer"><i /><b>➤</b></div>
    </div>
    <aside className="message-loading-context" aria-hidden="true"><i /><i /><i /><i /><i /></aside>
    <div className="message-loading-status"><span className="message-loading-spinner" aria-hidden="true" /><span><strong>Đang kết nối hộp thư</strong><small>Đang xác minh phiên đăng nhập và tải cuộc trò chuyện…</small></span></div>
  </section>;
}

function ReportsLoadingState() {
  return <section className="reports-loading-state" role="status" aria-label="Đang tải trang báo cáo">
    <div className="reports-loading-banner">
      <span className="reports-loading-spinner" aria-hidden="true" />
      <span><strong>Đang chuẩn bị báo cáo của bạn</strong><small>Đang xác minh phiên và tải thông tin an toàn…</small></span>
      <span className="reports-loading-banner-mark" aria-hidden="true">▤</span>
    </div>
    <div className="reports-loading-heading" aria-hidden="true">
      <i className="reports-skeleton reports-skeleton-eyebrow" />
      <i className="reports-skeleton reports-skeleton-title" />
      <i className="reports-skeleton reports-skeleton-subtitle" />
    </div>
    <div className="reports-loading-stats" aria-hidden="true">
      {[0, 1, 2].map((item) => <div className="reports-loading-stat" key={item}>
        <i className="reports-skeleton reports-skeleton-stat-icon" />
        <span><i className="reports-skeleton reports-skeleton-stat-label" /><i className="reports-skeleton reports-skeleton-stat-value" /></span>
      </div>)}
    </div>
    <div className="reports-loading-list" aria-hidden="true">
      <div className="reports-loading-list-head"><span><i className="reports-skeleton reports-skeleton-list-title" /><i className="reports-skeleton reports-skeleton-list-subtitle" /></span><i className="reports-skeleton reports-skeleton-filter" /></div>
      {[0, 1, 2].map((item) => <div className="reports-loading-row" key={item}>
        <i className="reports-skeleton reports-skeleton-row-icon" />
        <span><i className="reports-skeleton reports-skeleton-row-title" /><i className="reports-skeleton reports-skeleton-row-meta" /><i className="reports-skeleton reports-skeleton-row-description" /></span>
        <i className="reports-skeleton reports-skeleton-row-action" />
      </div>)}
    </div>
  </section>;
}

function initials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase();
}

function TutorWorkspaceProfile({ user, pathname }: { user: User; pathname: string }) {
  const tabs = [
    { href: "/profile", label: "Hồ sơ" },
    { href: "/me/listings", label: "Dịch vụ" },
    { href: "/programs", label: "Chương trình học" },
    { href: "/me/availability", label: "Lịch khả dụng" },
  ];
  return <section className="tutor-workspace-profile" aria-label="Hồ sơ gia sư">
    <div className="tutor-profile-cover" aria-hidden="true" />
    <div className="tutor-profile-identity">
      <div className="tutor-profile-avatar" aria-label={`Ảnh đại diện ${user.full_name}`}>{initials(user.full_name)}</div>
      <div className="tutor-profile-name"><p>HỒ SƠ GIA SƯ</p><h1>{user.full_name}</h1><span>Gia sư · TutorLink</span></div>
    </div>
    <nav className="tutor-profile-tabs" aria-label="Khu vực gia sư">
      {tabs.map((tab) => <Link href={tab.href} key={tab.href} aria-current={isActive(pathname, tab.href) ? "page" : undefined}>{tab.label}</Link>)}
    </nav>
  </section>;
}

function BrandMark() {
  return <svg className="brand-mark" viewBox="0 0 32 28" fill="none" aria-hidden="true">
    <path d="M2 4.2c5.1-1.9 9.7-.8 14 3.1v17c-4.3-3.9-8.9-5-14-3.1v-17Z" fill="currentColor" />
    <path d="M30 4.2c-5.1-1.9-9.7-.8-14 3.1v17c4.3-3.9 8.9-5 14-3.1v-17Z" fill="currentColor" />
    <path d="M16 7.4v16.8" stroke="white" strokeWidth="1.5" />
  </svg>;
}

function AccountMenu({ user }: { user: User }) {
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
            <Link href="/invitations" onClick={() => setOpen(false)}>Lời mời học</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt</Link>
          </nav> : user.role === "tutor" ? <nav className="account-menu-links" aria-label="Điều hướng tài khoản gia sư">
            <Link href="/profile" onClick={() => setOpen(false)}>Hồ sơ của tôi</Link>
            <Link href="/reports" onClick={() => setOpen(false)}>Báo cáo của tôi</Link>
            <Link href="/support" onClick={() => setOpen(false)}>Hỗ trợ</Link>
            <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>
          </nav> : <Link href="/settings" onClick={() => setOpen(false)}>Cài đặt tài khoản</Link>}
          <LogoutButton />
        </>}
      </div>
    </div>
  );
}

function LocaleDisplay() {
  return (
    <span className="locale-display">
      <span className="locale-flag" aria-hidden="true">🇻🇳</span>
      Tiếng Việt
    </span>
  );
}

function ServicesNavMenu({ onNavigate }: { onNavigate: () => void }) {
  const [open, setOpen] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const menuId = useId();
  const loadCatalog = useCallback(() => getMarketplaceCatalog(), []);
  const catalog = useAsyncData(loadCatalog);

  return (
    <div className={`public-nav-dropdown${open ? " is-open" : ""}`} onMouseLeave={() => setOpen(false)} onBlur={(event) => {
      if (event.relatedTarget && !event.currentTarget.contains(event.relatedTarget)) setOpen(false);
    }} onKeyDown={(event) => {
      if (event.key === "Escape" && open) {
        setOpen(false);
        trigger.current?.focus();
      }
    }}>
      <button
        ref={trigger}
        className="public-nav-trigger"
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        onClick={() => setOpen((value) => !value)}
        onMouseEnter={() => setOpen(true)}
        onFocus={() => setOpen(true)}
      >
        Dịch vụ
      </button>
      <div className="public-nav-menu" id={menuId} role="group" aria-label="Danh mục dịch vụ">
        <strong className="public-nav-menu-title">Khám phá theo danh mục</strong>
        {catalog.loading ? <span className="public-nav-menu-state">Đang tải danh mục…</span> : catalog.error ? <span className="public-nav-menu-state">Chưa tải được danh mục.</span> : catalog.data?.length ? catalog.data.map((category) => (
          <Link
            href={`/marketplace?category=${category.id}`}
            key={category.id}
            onClick={() => { setOpen(false); onNavigate(); }}
          >
            <span>{category.name}</span><span aria-hidden="true">→</span>
          </Link>
        )) : <span className="public-nav-menu-state">Chưa có danh mục dịch vụ.</span>}
      </div>
    </div>
  );
}

export function AppShell({ children, role, title = "TutorLink", mode, hideSidebar = false }: AppShellProps) {
  const pathname = usePathname();
  const authFrame = mode === "auth" || pathname.startsWith("/auth/");
  const publicMode = mode === "public" || authFrame;
  const sessionHint = useSessionHint();
  const mockPublicGuest = publicMode && dataMode === "mock" && !sessionHint;
  const [session, setSession] = useState<Session>(() => cachedSession ?? { status: "loading" });
  const [unreadNotifications, setUnreadNotifications] = useState(0);
  const [unreadMessages, setUnreadMessages] = useState(0);
  const [favoriteCount, setFavoriteCount] = useState(0);
  const [menuOpen, setMenuOpen] = useState(false);
  const [retry, setRetry] = useState(0);
  const freshSessionRequest = pathname.startsWith("/me/") || pathname.startsWith("/settings");

  useEffect(() => {
    let current = true;
    if (authFrame || mockPublicGuest) return;

    const sessionRequest = freshSessionRequest
      ? request<ApiEnvelope<User>>({ method: "GET", url: "/me" }).then((response) => response.data.data)
      : getCurrentUser();
    sessionRequest.then((user) => {
      if (!current) return;
      setAuthHint(true);
      const nextSession: Session = { status: "authenticated", user };
      cachedSession = nextSession;
      setSession(nextSession);
    }).catch((reason: unknown) => {
      if (!current) return;
      if (reason instanceof ApiClientError && reason.status === 401) {
        setAuthHint(false);
        cachedSession = { status: "guest" };
        setSession(cachedSession);
      } else {
        cachedSession = { status: "error", message: describeApiError(reason) };
        setSession(cachedSession);
      }
    });

    return () => { current = false; };
  }, [freshSessionRequest, authFrame, mockPublicGuest, retry]);

  useEffect(() => {
    const refreshAccount = () => {
      cachedSession = null;
      setSession({ status: "loading" });
      setRetry((value) => value + 1);
    };
    window.addEventListener("tutorlink-account-updated", refreshAccount);
    return () => window.removeEventListener("tutorlink-account-updated", refreshAccount);
  }, []);

  const requiredRole: Role | undefined = pathname.startsWith("/me/")
    ? role ?? "tutor"
    : (pathname === "/admin" || pathname === "/monitoring")
    ? role ?? "admin"
    : pathname === "/profile"
      ? role ?? "tutor"
      : role;
  const user = !authFrame && !mockPublicGuest && session.status === "authenticated" ? session.user : null;
  useEffect(() => {
    let current = true;
    if (!user) {
      setUnreadNotifications(0);
      return;
    }
    request<ApiEnvelope<{ count: number }>>({ method: "GET", url: "/notifications/unread-count" })
      .then((response) => {
        if (current) setUnreadNotifications(response.data.data.count);
      })
      .catch(() => {
        if (current) setUnreadNotifications(0);
      });
    return () => { current = false; };
  }, [user?.id, pathname]);
  useEffect(() => {
    let current = true;
    if (!user || !["customer", "tutor"].includes(user.role)) {
      setUnreadMessages(0);
      return;
    }
    const refreshUnreadMessages = () => {
      if (document.visibilityState === "hidden") return;
      request<ApiEnvelope<{ count: number }>>({ method: "GET", url: "/conversations/unread-count" })
        .then((response) => { if (current) setUnreadMessages(response.data.data.count); })
        .catch(() => { if (current) setUnreadMessages(0); });
    };
    refreshUnreadMessages();
    const interval = window.setInterval(refreshUnreadMessages, 5000);
    document.addEventListener("visibilitychange", refreshUnreadMessages);
    return () => {
      current = false;
      window.clearInterval(interval);
      document.removeEventListener("visibilitychange", refreshUnreadMessages);
    };
  }, [user?.id, user?.role]);
  useEffect(() => {
    let current = true;
    const loadFavorites = () => {
      if (!user) {
        setFavoriteCount(0);
        return;
      }
      getFavoriteTutors()
        .then((favorites) => { if (current) setFavoriteCount(favorites.length); })
        .catch(() => { if (current) setFavoriteCount(0); });
    };
    loadFavorites();
    window.addEventListener(favoriteUpdateEvent, loadFavorites);
    return () => {
      current = false;
      window.removeEventListener(favoriteUpdateEvent, loadFavorites);
    };
  }, [user?.id, pathname]);
  const roleMismatch = Boolean(user && requiredRole && user.role !== requiredRole);
  const protectedPage = Boolean(requiredRole) || isActive(pathname, "/settings") || ["/programs", "/invitations", "/messages", "/reports", "/notifications", "/favorites"].some(prefix => isActive(pathname, prefix));
  const needsLogin = protectedPage && session.status === "guest";
  const needsSessionError = protectedPage && session.status === "error";
  const needsRole = Boolean(protectedPage && user && roleMismatch);
  const needsLoading = protectedPage && session.status === "loading";
  // Protected routes must keep their account shell while /me is loading.
  // Rendering the public shell for this intermediate state causes a full
  // header/sidebar replacement as soon as the session request resolves.
  const showPublicFrame = publicMode || (!user && !protectedPage && !sessionHint);
  const showGuestActions = !user && (authFrame || mockPublicGuest || session.status === "guest" ||
    (session.status === "error" && !sessionHint));

  const accessContent = needsLoading ? (
    isActive(pathname, "/notifications") ? <ActorContext.Provider value={null}>{children}</ActorContext.Provider> : isActive(pathname, "/messages") ? <MessagingLoadingState /> : isActive(pathname, "/favorites") ? <FavoritesLoadingState /> : isActive(pathname, "/reports") ? <ReportsLoadingState /> : <StatePanel kind="loading" message="Đang xác minh phiên đăng nhập…" />
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
  ) : <ActorContext.Provider value={user}>{children}</ActorContext.Provider>;

  if (showPublicFrame) {
    return (
      <div className={`app-frame public-frame${authFrame ? " auth-frame" : ""}`}>
        <header className="topbar public-topbar">
          <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
            <BrandMark />
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
            <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined} onClick={() => setMenuOpen(false)}>Tìm gia sư</Link>
            <ServicesNavMenu onNavigate={() => setMenuOpen(false)} />
            <Link href="/become-a-tutor" aria-current={pathname === "/become-a-tutor" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Trở thành gia sư</Link>
            <Link href="/about" aria-current={pathname === "/about" ? "page" : undefined} onClick={() => setMenuOpen(false)}>About</Link>
          </nav>
          <div className="public-actions">
            {!authFrame ? <form className="public-header-search" action="/marketplace" method="get" role="search">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.2 4.2" /></svg>
              <label className="landing-sr-only" htmlFor="public-header-query">Tìm gia sư, môn học hoặc kỹ năng</label>
              <input id="public-header-query" name="q" type="search" placeholder="Tìm gia sư, môn học hoặc kỹ năng..." />
            </form> : null}
            <LocaleDisplay />
            {user ? <>
              <Link className="public-header-messages" href="/messages" aria-label="Tin nhắn" title="Tin nhắn">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.3A7.5 7.5 0 1 1 20 11.5Z" /><path d="M8.5 11.5h7m-7 3h4" /></svg>
                {unreadMessages > 0 ? <span className="public-favorite-count" aria-label={`${unreadMessages} tin nhắn chưa đọc`}>{unreadMessages > 50 ? "50+" : unreadMessages}</span> : null}
              </Link>
              <Link className="public-header-favorite" href="/favorites" aria-label={`Yêu thích${favoriteCount ? `, ${favoriteCount} hồ sơ đã lưu` : ""}`} title="Yêu thích">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M20.8 8.7c0 5.1-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.7A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.8 2.3Z" />
                </svg>
                {favoriteCount > 0 ? <span className="public-favorite-count">{favoriteCount > 99 ? "99+" : favoriteCount}</span> : null}
              </Link>
              <Link className="public-header-notifications" href="/notifications" aria-label="Thông báo" title="Thông báo">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" />
                </svg>
                {unreadNotifications > 0 ? <span className="public-notification-dot" aria-label={`${unreadNotifications} thông báo chưa đọc`} /> : null}
              </Link>
              <AccountMenu key={pathname} user={user} />
            </> : showGuestActions ? <>
              <Link className="public-login" href="/auth/login">Đăng nhập</Link>
              <Link className="button button-primary" href="/auth/register">Đăng ký</Link>
            </> : session.status === "error" ? (
              <Button variant="secondary" onClick={() => setRetry((value) => value + 1)}>Thử lại phiên đăng nhập</Button>
            ) : null}
          </div>
        </header>
        <main className={`page-content${authFrame ? " auth-page-content" : ""}`}>
          {accessContent}
        </main>
        {!authFrame ? <PublicFooter /> : null}
      </div>
    );
  }

  const activeRole = user?.role ?? requiredRole;
  const messagingRoute = pathname === "/messages" || pathname.startsWith("/messages/");
  const tutorWorkspace = activeRole === "tutor" && (pathname === "/profile" || isActive(pathname, "/me/listings") || isActive(pathname, "/me/availability") || isActive(pathname, "/programs"));
  const tutorSidebarRoute = activeRole === "tutor" && (isActive(pathname, "/settings") || pathname === "/reports");
  const hideTutorSidebar = activeRole === "tutor" && !tutorSidebarRoute;
  const roleNavItems = activeRole === "admin"
    ? [{ href: "/admin", label: "Duyệt hồ sơ Tutor" },{href:"/monitoring",label:"Giám sát hệ thống"}]
    : activeRole === "tutor"
      ? [{ href: "/profile", label: "Hồ sơ Tutor" }, { href: "/me/listings", label: "Quản lý giảng dạy" }]
      : [];
  const collaborationNav = !activeRole || activeRole === "admin" ? [] : activeRole === "tutor" ? [
    { href: "/reports", label: "Báo cáo của tôi" },
  ] : [
    { href: "/programs", label: "Program của tôi" },
    { href: "/invitations", label: "Lời mời học" },
    { href: "/reports", label: "Báo cáo của tôi" },
  ];
  const navItems = activeRole === "tutor" && tutorSidebarRoute ? [
    { href: "/reports", label: "Báo cáo của tôi" },
    { href: "/support", label: "Hỗ trợ" },
    { href: "/settings/password", label: "Đổi mật khẩu" },
  ] : [
    ...roleNavItems,
    ...collaborationNav,
    ...(activeRole ? [{ href: "/settings", label: "Cài đặt tài khoản" }] : []),
    ...(activeRole === "tutor" ? [{ href: "/support", label: "Hỗ trợ" }] : []),
  ];

  return (
      <div className={`app-frame authenticated-frame${pathname === "/profile" ? " profile-shell" : ""}${messagingRoute ? " messaging-shell" : ""}${hideSidebar || hideTutorSidebar ? " sidebar-hidden-shell" : ""}${tutorWorkspace ? " tutor-workspace-shell" : ""}`}>
      <header className="topbar public-topbar authenticated-public-topbar">
        <Link className="brand" href="/" aria-label="TutorLink Việt Nam">
          <BrandMark />
          <span>Tutor</span><strong>Link</strong><span className="brand-star">✦</span>
          <small>Vietnam</small>
        </Link>
        {!messagingRoute && !hideTutorSidebar && !hideSidebar ? <button
          className="menu-toggle"
          type="button"
          aria-label={menuOpen ? "Đóng menu tài khoản" : "Mở menu tài khoản"}
          aria-expanded={menuOpen}
          aria-controls={hideSidebar ? "authenticated-navigation" : "app-sidebar"}
          onClick={() => setMenuOpen((open) => !open)}
        >
          <MenuIcon />
        </button> : null}
        <nav id="authenticated-navigation" className={`public-nav${menuOpen ? " is-open" : ""}`} aria-label="Điều hướng chính">
          <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined} onClick={() => setMenuOpen(false)}>Tìm gia sư</Link>
          <ServicesNavMenu onNavigate={() => setMenuOpen(false)} />
          <Link href="/become-a-tutor" aria-current={pathname === "/become-a-tutor" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Trở thành gia sư</Link>
          <Link href="/about" aria-current={pathname === "/about" ? "page" : undefined} onClick={() => setMenuOpen(false)}>About</Link>
        </nav>
        <div className="public-actions">
          <form className="public-header-search" action="/marketplace" method="get" role="search">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" /><path d="m16 16 4.2 4.2" /></svg>
            <label className="landing-sr-only" htmlFor="authenticated-header-query">Tìm gia sư, môn học hoặc kỹ năng</label>
            <input id="authenticated-header-query" name="q" type="search" placeholder="Tìm gia sư, môn học hoặc kỹ năng..." />
          </form>
          <LocaleDisplay />
          {user ? <>
            <Link className="public-header-messages" href="/messages" aria-label="Tin nhắn" title="Tin nhắn">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.3A7.5 7.5 0 1 1 20 11.5Z" /><path d="M8.5 11.5h7m-7 3h4" /></svg>
              {unreadMessages > 0 ? <span className="public-favorite-count" aria-label={`${unreadMessages} tin nhắn chưa đọc`}>{unreadMessages > 50 ? "50+" : unreadMessages}</span> : null}
            </Link>
            <Link className="public-header-favorite" href="/favorites" aria-label={`Yêu thích${favoriteCount ? `, ${favoriteCount} hồ sơ đã lưu` : ""}`} title="Yêu thích">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.8 8.7c0 5.1-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.7A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.8 2.3Z" /></svg>
              {favoriteCount > 0 ? <span className="public-favorite-count">{favoriteCount > 99 ? "99+" : favoriteCount}</span> : null}
            </Link>
            <Link className="public-header-notifications" href="/notifications" aria-label="Thông báo" title="Thông báo">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4" /></svg>
              {unreadNotifications > 0 ? <span className="public-notification-dot" aria-label={`${unreadNotifications} thông báo chưa đọc`} /> : null}
            </Link>
            <AccountMenu key={pathname} user={user} />
          </> : null}
        </div>
      </header>
      <div className={`app-layout${messagingRoute ? " messaging-app-layout" : ""}`}>
        {!messagingRoute && !hideSidebar && (!hideTutorSidebar || tutorSidebarRoute) && !tutorWorkspace ? <aside id="app-sidebar" className={`app-sidebar${menuOpen ? " is-open" : ""}`} aria-label={tutorSidebarRoute ? "Menu tài khoản gia sư" : activeRole ? `Menu ${roleLabels[activeRole]}` : "Menu tài khoản"}>
          <p className="sidebar-label">{activeRole ? roleLabels[activeRole] : "Tài khoản"}</p>
          {!activeRole && needsLoading ? <div className="sidebar-loading" role="status" aria-label="Đang tải menu tài khoản">
            {[0, 1, 2, 3, 4].map((item) => <span key={item} aria-hidden="true" />)}
          </div> : null}
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
        </aside> : null}
        <main className="app-main">
          <div className="page-content">
            {tutorWorkspace && user && !needsRole ? <TutorWorkspaceProfile user={user} pathname={pathname} /> : null}
            {pathname !== "/profile" && !messagingRoute && !tutorWorkspace && title ? <div className="eyebrow">{activeRole ? `${roleLabels[activeRole]} · ` : ""}{title}</div> : null}
            {protectedPage ? accessContent : children}
          </div>
        </main>
      </div>
    </div>
  );
}
