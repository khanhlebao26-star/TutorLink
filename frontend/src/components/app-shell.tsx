import Link from "next/link";
import type { ReactNode } from "react";
import { isMock } from "@/lib/data/services";
import type { Role } from "@/lib/data/types";
import { LogoutButton } from "./logout-button";
import { Badge } from "./ui";

const roleLabels: Record<Role, string> = {
  customer: "Customer",
  tutor: "Tutor",
  admin: "Admin",
};

export function AppShell({
  children,
  role = "customer",
  title = "TutorLink",
}: {
  children: ReactNode;
  role?: Role;
  title?: string;
}) {
  const links =
    role === "admin"
      ? [
          { href: "/admin", label: "Duyệt Tutor" },
          { href: "/marketplace", label: "Marketplace" },
        ]
      : [
          { href: "/marketplace", label: "Marketplace" },
          { href: "/profile", label: "Hồ sơ" },
        ];
  return (
    <div className="app-frame">
      <header className="topbar">
        <Link className="brand" href="/">
          TutorLink
        </Link>
        <nav aria-label="Điều hướng chính" className="nav-links">
          {links.map((link) => (
            <Link key={link.href} href={link.href}>
              {link.label}
            </Link>
          ))}
          {role !== "admin" ? <Link href="/admin">Admin demo</Link> : null}
          <Link href="/auth/login">Đăng nhập</Link>
          <LogoutButton />
        </nav>
        <div className="topbar-meta">
          <Badge>{roleLabels[role]}</Badge>
          {isMock ? (
            <Badge tone="warning">Dữ liệu mẫu</Badge>
          ) : (
            <Badge tone="success">API thật</Badge>
          )}
        </div>
      </header>
      <main className="page-content">
        <div className="eyebrow">
          {roleLabels[role]} · {title}
        </div>
        {children}
      </main>
    </div>
  );
}
