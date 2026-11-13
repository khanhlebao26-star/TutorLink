import type { Role } from "@/lib/data/types";

export const roleLabels: Record<Role, string> = {
  customer: "Học viên",
  tutor: "Gia sư",
  admin: "Quản trị",
};

export function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function initials(name: string) {
  return name.trim().split(/\s+/).slice(-2).map((part) => part[0]).join("").toLocaleUpperCase();
}
