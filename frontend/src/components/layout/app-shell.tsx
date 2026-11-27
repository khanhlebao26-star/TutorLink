"use client";

import { FavoritesLoadingState } from "@/components/favorites/favorites-loading-state";
import { PublicFooter } from "@/components/layout/public-footer";
import { useSessionHint } from "@/components/layout/session-hint";
import { Button, StatePanel } from "@/components/shared/ui";
import { ApiClientError, describeApiError, request, setAuthHint } from "@/lib/api/client";
import { favoriteUpdateEvent, getFavoriteTutors } from "@/lib/data/favorites";
import { getCurrentUser } from "@/lib/data/services";
import type { ApiEnvelope, Role, User } from "@/lib/data/types";
import { useUnreadMessages } from "@/lib/hooks/use-unread-messages";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";

import { AccountMenu } from "@/components/layout/account-menu";
import { BrandMark, LocaleDisplay, MenuIcon } from "@/components/layout/header-branding";
import { BellIcon } from "@phosphor-icons/react/dist/ssr/Bell";
import { ChatCircleDotsIcon } from "@phosphor-icons/react/dist/ssr/ChatCircleDots";
import { HeartIcon } from "@phosphor-icons/react/dist/ssr/Heart";
import { MagnifyingGlassIcon } from "@phosphor-icons/react/dist/ssr/MagnifyingGlass";
import { BookOpenTextIcon } from "@phosphor-icons/react/dist/ssr/BookOpenText";
import { CalendarDotsIcon } from "@phosphor-icons/react/dist/ssr/CalendarDots";
import { ChalkboardTeacherIcon } from "@phosphor-icons/react/dist/ssr/ChalkboardTeacher";
import { ChartBarIcon } from "@phosphor-icons/react/dist/ssr/ChartBar";
import { EnvelopeSimpleIcon } from "@phosphor-icons/react/dist/ssr/EnvelopeSimple";
import { GearSixIcon } from "@phosphor-icons/react/dist/ssr/GearSix";
import { QuestionIcon } from "@phosphor-icons/react/dist/ssr/Question";
import { UserCircleIcon } from "@phosphor-icons/react/dist/ssr/UserCircle";
import { FileTextIcon } from "@phosphor-icons/react/dist/ssr/FileText";
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
  const [session, setSession] = useState<Session>(() => cachedSession ?? { status: "loading" });
  const [notifications, setNotifications] = useState<{ userId: number; count: number } | null>(null);
  const [favoritesCount, setFavoritesCount] = useState<{ userId: number; count: number } | null>(null);
  const [menuOpen, setMenuOpen] = useState(false);
  const [retry, setRetry] = useState(0);
  const freshSessionRequest = pathname.startsWith("/me/") || pathname.startsWith("/settings");

  useEffect(() => {
    let current = true;
    if (authFrame) return;

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
  }, [freshSessionRequest, authFrame, retry]);

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
  const user = !authFrame && session.status === "authenticated" ? session.user : null;
  const userId = user?.id;
  const favoritesUserId = user?.role === "admin" ? undefined : userId;
  const unreadNotifications = notifications && notifications.userId === userId ? notifications.count : 0;
  const favoriteCount = favoritesCount && favoritesCount.userId === favoritesUserId ? favoritesCount.count : 0;
  const unreadMessages = useUnreadMessages(user?.role === "customer" || user?.role === "tutor" ? user.id : null);
  useEffect(() => {
    let current = true;
    if (userId === undefined) return;
    request<ApiEnvelope<{ count: number }>>({ method: "GET", url: "/notifications/unread-count" })
      .then((response) => {
        if (current) setNotifications({ userId, count: response.data.data.count });
      })
      .catch(() => {
        if (current) setNotifications({ userId, count: 0 });
      });
    return () => { current = false; };
  }, [userId, pathname]);
  useEffect(() => {
    let current = true;
    if (favoritesUserId === undefined) return;
    const loadFavorites = () => {
      getFavoriteTutors()
        .then((favorites) => { if (current) setFavoritesCount({ userId: favoritesUserId, count: favorites.length }); })
        .catch(() => { if (current) setFavoritesCount({ userId: favoritesUserId, count: 0 }); });
    };
    loadFavorites();
    window.addEventListener(favoriteUpdateEvent, loadFavorites);
    return () => {
      current = false;
      window.removeEventListener(favoriteUpdateEvent, loadFavorites);
    };
  }, [favoritesUserId, pathname]);
  const roleMismatch = Boolean(user && requiredRole && user.role !== requiredRole);
  const protectedPage = Boolean(requiredRole) || isActive(pathname, "/settings") || ["/programs", "/invitations", "/messages", "/reports", "/notifications", "/favorites", "/learning-profile"].some(prefix => isActive(pathname, prefix));
  const invitationDetailRoute = /^\/invitations\/\d+$/.test(pathname);
  const needsLogin = protectedPage && session.status === "guest";
  const needsSessionError = protectedPage && session.status === "error";
  const needsRole = Boolean(protectedPage && user && roleMismatch);
  const needsLoading = protectedPage && session.status === "loading";
  const activeRole = user?.role ?? requiredRole;
  const primaryNavItems = activeRole === "admin"
    ? [
      { href: "/admin/dashboard", label: "Dashboard" },
      { href: "/admin", label: "Duyệt hồ sơ" },
      { href: "/admin/reports", label: "Báo cáo" },
      { href: "/monitoring", label: "Giám sát" },
    ]
    : activeRole === "tutor"
      ? [
        { href: "/profile", label: "Workspace gia sư" },
        { href: "/marketplace", label: "Khám phá" },
        { href: "/support", label: "Hỗ trợ" },
      ]
      : activeRole === "customer"
        ? [
          { href: "/marketplace", label: "Khám phá dịch vụ" },
          { href: "/learning-profile", label: "Workspace học viên" },
          { href: "/support", label: "Hỗ trợ" },
        ]
        : null;
  // Protected routes must keep their account shell while /me is loading.
  // Rendering the public shell for this intermediate state causes a full
  // header/sidebar replacement as soon as the session request resolves.
  const showPublicFrame = publicMode || (!user && !protectedPage && !sessionHint);
  const showGuestActions = !user && (authFrame || session.status === "guest" ||
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
      <div className={`app-frame public-frame min-h-screen${authFrame ? " auth-frame" : ""}`}>
        <header className="topbar public-topbar sticky top-0 z-10 flex min-h-[66px] flex-wrap items-center justify-between gap-4 border-b border-line bg-white px-4 py-2.5 sm:px-5 md:gap-4 lg:min-h-[76px] lg:flex-nowrap lg:gap-[clamp(12px,2vw,30px)] lg:px-[clamp(18px,5vw,80px)] lg:py-3">
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
          <nav id="public-navigation" className={`public-nav hidden min-w-0 flex-1 flex-col items-stretch justify-center gap-0 lg:flex lg:flex-row lg:items-center lg:gap-[clamp(12px,1.45vw,23px)] ${menuOpen ? "is-open max-lg:!flex" : ""}`} aria-label="Điều hướng chính">
            {primaryNavItems ? primaryNavItems.map((item) => <Link key={item.href} href={item.href} aria-current={isActive(pathname, item.href) ? "page" : undefined} onClick={() => setMenuOpen(false)}>{item.label}</Link>) : <>
              <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined} onClick={() => setMenuOpen(false)}>Tìm gia sư</Link>
              <ServicesNavMenu onNavigate={() => setMenuOpen(false)} />
              <Link href="/become-a-tutor" aria-current={pathname === "/become-a-tutor" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Trở thành gia sư</Link>
              <Link href="/about" aria-current={pathname === "/about" ? "page" : undefined} onClick={() => setMenuOpen(false)}>About</Link>
              <Link href="/support" aria-current={pathname === "/support" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Hỗ trợ</Link>
            </>}
          </nav>
          <div className="public-actions order-3 flex flex-[1_0_100%] flex-wrap items-center gap-3 lg:order-none lg:flex-none lg:flex-nowrap lg:gap-[clamp(12px,1.5vw,22px)]">
            {!authFrame ? <form className="public-header-search order-first flex h-[42px] min-w-full flex-[1_0_100%] items-center gap-[9px] rounded-full border border-[#d9d1ed] bg-white px-[13px] shadow-[0_2px_8px_#1a12400a] transition-[border-color,box-shadow] duration-150 focus-within:border-[#bc9beb] focus-within:shadow-[0_0_0_3px_#9a66ff18] sm:min-w-[190px] sm:flex-[0_1_300px] lg:order-none" action="/marketplace" method="get" role="search">
              <MagnifyingGlassIcon weight="regular" aria-hidden="true" />
              <label className="landing-sr-only" htmlFor="public-header-query">Tìm gia sư, môn học hoặc kỹ năng</label>
              <input className="w-full min-w-0 border-0 bg-transparent p-0 text-[.76rem] text-brand outline-none placeholder:text-[#85819c]" id="public-header-query" name="q" type="search" placeholder="Tìm gia sư, môn học hoặc kỹ năng..." />
            </form> : null}
            <LocaleDisplay />
            {user ? <>
              <Link className="public-header-messages" href="/messages" aria-label="Tin nhắn" title="Tin nhắn">
                <ChatCircleDotsIcon weight="regular" aria-hidden="true" />
                {unreadMessages > 0 ? <span className="public-favorite-count" aria-label={`${unreadMessages} tin nhắn chưa đọc`}>{unreadMessages > 50 ? "50+" : unreadMessages}</span> : null}
              </Link>
              <Link className="public-header-favorite" href="/favorites" aria-label={`Yêu thích${favoriteCount ? `, ${favoriteCount} hồ sơ đã lưu` : ""}`} title="Yêu thích">
                <HeartIcon weight="regular" aria-hidden="true" />
                {favoriteCount > 0 ? <span className="public-favorite-count">{favoriteCount > 99 ? "99+" : favoriteCount}</span> : null}
              </Link>
              <Link className="public-header-notifications" href="/notifications" aria-label="Thông báo" title="Thông báo">
                <BellIcon weight="regular" aria-hidden="true" />
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
        <main className={`page-content w-full px-[18px] pb-[52px] pt-7 sm:px-5 sm:pb-14 sm:pt-8 lg:px-[clamp(16px,2vw,32px)] ${authFrame ? "auth-page-content !p-0" : ""}`}>
          {accessContent}
        </main>
        {!authFrame ? <PublicFooter /> : null}
      </div>
    );
  }

  const messagingRoute = pathname === "/messages" || pathname.startsWith("/messages/");
  const learningProfileRoute = activeRole === "customer" && pathname === "/learning-profile";
  const tutorWorkspace = activeRole === "tutor" && (pathname === "/profile" || isActive(pathname, "/me/listings") || isActive(pathname, "/me/availability") || isActive(pathname, "/programs"));
  const tutorProfileRoute = activeRole === "tutor" && pathname === "/profile";
  const tutorSidebarRoute = activeRole === "tutor" && (tutorWorkspace || isActive(pathname, "/settings") || pathname === "/reports");
  const hideTutorSidebar = activeRole === "tutor" && !tutorSidebarRoute;
  const navItems = activeRole === "admin" ? [
    { href: "/admin/dashboard", label: "Dashboard quản trị", Icon: ChartBarIcon },
    { href: "/admin", label: "Duyệt hồ sơ Tutor", Icon: UserCircleIcon },
    { href: "/admin/reports", label: "Quản lý báo cáo", Icon: FileTextIcon },
    { href: "/monitoring", label: "Giám sát hệ thống", Icon: ChartBarIcon },
  ] : activeRole === "tutor" ? [
    { href: "/profile", label: "Hồ sơ gia sư", Icon: UserCircleIcon },
    { href: "/me/listings", label: "Dịch vụ", Icon: ChalkboardTeacherIcon },
    { href: "/programs", label: "Chương trình học", Icon: BookOpenTextIcon },
    { href: "/me/availability", label: "Lịch khả dụng", Icon: CalendarDotsIcon },
    { href: "/reports", label: "Báo cáo của tôi", Icon: ChartBarIcon },
    { href: "/support", label: "Hỗ trợ", Icon: QuestionIcon },
    { href: "/settings", label: "Cài đặt tài khoản", Icon: GearSixIcon },
  ] : activeRole === "customer" ? [
    { href: "/learning-profile", label: "Hồ sơ học tập", Icon: UserCircleIcon },
    { href: "/programs", label: "Program của tôi", Icon: BookOpenTextIcon },
    { href: "/invitations", label: "Lời mời học", Icon: EnvelopeSimpleIcon },
    { href: "/reports", label: "Báo cáo của tôi", Icon: ChartBarIcon },
    { href: "/support", label: "Hỗ trợ", Icon: QuestionIcon },
    { href: "/settings", label: "Cài đặt tài khoản", Icon: GearSixIcon },
  ] : [];
  const sidebarLabel = activeRole === "customer" ? "Workspace học viên" : activeRole === "tutor" ? "Workspace gia sư" : activeRole ? roleLabels[activeRole] : "Tài khoản";
  const singleColumnShell = hideSidebar || hideTutorSidebar || messagingRoute || pathname === "/notifications";

  return (
    <div className={`app-frame authenticated-frame${pathname === "/profile" ? " profile-shell" : ""}${messagingRoute ? " messaging-shell" : ""}${hideSidebar || hideTutorSidebar ? " sidebar-hidden-shell" : ""}${tutorWorkspace ? " tutor-workspace-shell" : ""}${learningProfileRoute ? " learning-profile-shell" : ""}`}>
      <header className="topbar public-topbar authenticated-public-topbar sticky top-0 z-10 flex min-h-[66px] flex-wrap items-center justify-between gap-4 border-b border-line bg-white px-4 py-2.5 sm:px-5 md:gap-4 lg:min-h-[76px] lg:flex-nowrap lg:gap-[clamp(12px,2vw,30px)] lg:px-[clamp(18px,5vw,80px)] lg:py-3">
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
        <nav id="authenticated-navigation" className={`public-nav hidden min-w-0 flex-1 flex-col items-stretch justify-center gap-0 lg:flex lg:flex-row lg:items-center lg:gap-[clamp(12px,1.45vw,23px)] ${menuOpen ? "is-open max-lg:!flex" : ""}`} aria-label="Điều hướng chính">
          {primaryNavItems ? primaryNavItems.map((item) => <Link key={item.href} href={item.href} aria-current={isActive(pathname, item.href) ? "page" : undefined} onClick={() => setMenuOpen(false)}>{item.label}</Link>) : <>
            <Link href="/marketplace" aria-current={isActive(pathname, "/marketplace") ? "page" : undefined} onClick={() => setMenuOpen(false)}>Tìm gia sư</Link>
            <ServicesNavMenu onNavigate={() => setMenuOpen(false)} />
            <Link href="/become-a-tutor" aria-current={pathname === "/become-a-tutor" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Trở thành gia sư</Link>
            <Link href="/about" aria-current={pathname === "/about" ? "page" : undefined} onClick={() => setMenuOpen(false)}>About</Link>
            <Link href="/support" aria-current={pathname === "/support" ? "page" : undefined} onClick={() => setMenuOpen(false)}>Hỗ trợ</Link>
          </>}
        </nav>
        <div className="public-actions order-3 flex flex-[1_0_100%] flex-wrap items-center gap-3 lg:order-none lg:flex-none lg:flex-nowrap lg:gap-[clamp(12px,1.5vw,22px)]">
          {activeRole !== "admin" ? <form className="public-header-search order-first flex h-[42px] min-w-full flex-[1_0_100%] items-center gap-[9px] rounded-full border border-[#d9d1ed] bg-white px-[13px] shadow-[0_2px_8px_#1a12400a] transition-[border-color,box-shadow] duration-150 focus-within:border-[#bc9beb] focus-within:shadow-[0_0_0_3px_#9a66ff18] sm:min-w-[190px] sm:flex-[0_1_300px] lg:order-none" action="/marketplace" method="get" role="search">
            <MagnifyingGlassIcon weight="regular" aria-hidden="true" />
            <label className="landing-sr-only" htmlFor="authenticated-header-query">Tìm gia sư, môn học hoặc kỹ năng</label>
            <input className="w-full min-w-0 border-0 bg-transparent p-0 text-[.76rem] text-brand outline-none placeholder:text-[#85819c]" id="authenticated-header-query" name="q" type="search" placeholder="Tìm gia sư, môn học hoặc kỹ năng..." />
          </form> : null}
          <LocaleDisplay />
          {user ? <>
            {user.role !== "admin" ? <>
              <Link className="public-header-messages" href="/messages" aria-label="Tin nhắn" title="Tin nhắn">
                <ChatCircleDotsIcon weight="regular" aria-hidden="true" />
                {unreadMessages > 0 ? <span className="public-favorite-count" aria-label={`${unreadMessages} tin nhắn chưa đọc`}>{unreadMessages > 50 ? "50+" : unreadMessages}</span> : null}
              </Link>
              <Link className="public-header-favorite" href="/favorites" aria-label={`Yêu thích${favoriteCount ? `, ${favoriteCount} hồ sơ đã lưu` : ""}`} title="Yêu thích">
                <HeartIcon weight="regular" aria-hidden="true" />
                {favoriteCount > 0 ? <span className="public-favorite-count">{favoriteCount > 99 ? "99+" : favoriteCount}</span> : null}
              </Link>
            </> : null}
            <Link className="public-header-notifications" href="/notifications" aria-label="Thông báo" title="Thông báo">
              <BellIcon weight="regular" aria-hidden="true" />
              {unreadNotifications > 0 ? <span className="public-notification-dot" aria-label={`${unreadNotifications} thông báo chưa đọc`} /> : null}
            </Link>
            <AccountMenu key={pathname} user={user} />
          </> : null}
        </div>
      </header>
      <div className={`app-layout ${singleColumnShell ? "block" : "grid grid-cols-1 sm:grid-cols-[180px_minmax(0,1fr)]"} min-h-[calc(100vh-61px)] w-full${messagingRoute ? " messaging-app-layout" : ""}`}>
        {!messagingRoute && !hideSidebar && !hideTutorSidebar ? <aside id="app-sidebar" className={`app-sidebar${menuOpen ? " is-open" : ""}`} aria-label={sidebarLabel}>
          <p className="sidebar-label">{sidebarLabel}</p>
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
                <item.Icon className="sidebar-nav-icon" weight="regular" aria-hidden="true" />
                <span>{item.label}</span>
              </Link>
            ))}
          </nav>
        </aside> : null}
        <main className="app-main">
          <div className="page-content">
            {tutorProfileRoute && user && !needsRole ? <TutorWorkspaceProfile user={user} /> : null}
            {pathname !== "/profile" && pathname !== "/learning-profile" && !messagingRoute && !tutorWorkspace && title ? <div className="eyebrow">{activeRole ? `${roleLabels[activeRole]} · ` : ""}{title}</div> : null}
            {protectedPage ? accessContent : children}
          </div>
        </main>
      </div>
    </div>
  );
}
