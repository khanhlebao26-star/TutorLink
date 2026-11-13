"use client";

import { FavoritesLoadingState } from "@/components/favorites/favorites-loading-state";
import { PublicFooter } from "@/components/layout/public-footer";
import { useSessionHint } from "@/components/layout/session-hint";
import { Button, StatePanel } from "@/components/shared/ui";
import { ApiClientError, dataMode, describeApiError, request, setAuthHint } from "@/lib/api/client";
import { favoriteUpdateEvent, getFavoriteTutors } from "@/lib/data/favorites";
import { getCurrentUser } from "@/lib/data/services";
import type { ApiEnvelope, Role, User } from "@/lib/data/types";
import "@/styles/landing.css";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

import { AccountMenu } from "@/components/layout/account-menu";
import { BrandMark, LocaleDisplay, MenuIcon } from "@/components/layout/header-branding";
import { isActive, roleLabels } from "@/components/layout/navigation-utils";
import { ServicesNavMenu } from "@/components/layout/services-nav-menu";
import { MessagingLoadingState, ReportsLoadingState } from "@/components/layout/shell-loading-states";
import { TutorWorkspaceProfile } from "@/components/layout/tutor-workspace-profile";

const ActorContext = createContext<User | null>(null);
export const useActor = () => useContext(ActorContext);

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
    : (isActive(pathname, "/admin") || pathname === "/monitoring")
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
    if (!user || user.role === "admin") {
      setFavoriteCount(0);
      return () => { current = false; };
    }
    const loadFavorites = () => {
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
  }, [user?.id, user?.role, pathname]);
  const roleMismatch = Boolean(user && requiredRole && user.role !== requiredRole);
  const protectedPage = Boolean(requiredRole) || isActive(pathname, "/settings") || ["/programs", "/invitations", "/messages", "/reports", "/notifications", "/favorites", "/learning-profile"].some(prefix => isActive(pathname, prefix));
  const invitationDetailRoute = /^\/invitations\/\d+$/.test(pathname);
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
    isActive(pathname, "/notifications") || invitationDetailRoute ? <ActorContext.Provider value={null}>{children}</ActorContext.Provider> : isActive(pathname, "/messages") ? <MessagingLoadingState /> : isActive(pathname, "/favorites") ? <FavoritesLoadingState /> : isActive(pathname, "/reports") ? <ReportsLoadingState /> : <StatePanel kind="loading" message="Đang xác minh phiên đăng nhập…" />
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
  const learningProfileRoute = activeRole === "customer" && pathname === "/learning-profile";
  const tutorWorkspace = activeRole === "tutor" && (pathname === "/profile" || isActive(pathname, "/me/listings") || isActive(pathname, "/me/availability") || pathname === "/programs");
  const tutorSidebarRoute = activeRole === "tutor" && (isActive(pathname, "/settings") || pathname === "/reports");
  const hideTutorSidebar = activeRole === "tutor" && !tutorSidebarRoute;
  const roleNavItems = activeRole === "admin"
    ? [{ href: "/admin/dashboard", label: "Dashboard quản trị" }, { href: "/admin", label: "Duyệt hồ sơ Tutor" }, { href: "/admin/reports", label: "Quản lý báo cáo" }, { href: "/monitoring", label: "Giám sát hệ thống" }]
    : activeRole === "tutor"
      ? [{ href: "/profile", label: "Hồ sơ Tutor" }, { href: "/me/listings", label: "Quản lý giảng dạy" }]
      : activeRole === "customer" ? [{ href: "/learning-profile", label: "Hồ sơ học tập" }] : [];
  const collaborationNav = !activeRole || activeRole === "admin" ? [] : activeRole === "tutor" ? [
    { href: "/reports", label: "Báo cáo của tôi" },
  ] : [
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
    <div className={`app-frame authenticated-frame${pathname === "/profile" ? " profile-shell" : ""}${messagingRoute ? " messaging-shell" : ""}${hideSidebar || hideTutorSidebar ? " sidebar-hidden-shell" : ""}${tutorWorkspace ? " tutor-workspace-shell" : ""}${learningProfileRoute ? " learning-profile-shell" : ""}`}>
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
            {user.role !== "admin" ? <>
              <Link className="public-header-messages" href="/messages" aria-label="Tin nhắn" title="Tin nhắn">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l1.8-3.3A7.5 7.5 0 1 1 20 11.5Z" /><path d="M8.5 11.5h7m-7 3h4" /></svg>
                {unreadMessages > 0 ? <span className="public-favorite-count" aria-label={`${unreadMessages} tin nhắn chưa đọc`}>{unreadMessages > 50 ? "50+" : unreadMessages}</span> : null}
              </Link>
              <Link className="public-header-favorite" href="/favorites" aria-label={`Yêu thích${favoriteCount ? `, ${favoriteCount} hồ sơ đã lưu` : ""}`} title="Yêu thích">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.8 8.7c0 5.1-8.8 10.2-8.8 10.2S3.2 13.8 3.2 8.7A4.7 4.7 0 0 1 12 6.4a4.7 4.7 0 0 1 8.8 2.3Z" /></svg>
                {favoriteCount > 0 ? <span className="public-favorite-count">{favoriteCount > 99 ? "99+" : favoriteCount}</span> : null}
              </Link>
            </> : null}
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
                aria-current={(item.href === "/admin" ? pathname === "/admin" : isActive(pathname, item.href)) ? "page" : undefined}
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
            {pathname !== "/profile" && pathname !== "/learning-profile" && !messagingRoute && !tutorWorkspace && title ? <div className="eyebrow">{activeRole ? `${roleLabels[activeRole]} · ` : ""}{title}</div> : null}
            {protectedPage ? accessContent : children}
          </div>
        </main>
      </div>
    </div>
  );
}
