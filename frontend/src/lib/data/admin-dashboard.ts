import { request } from "@/lib/api/client";
import type { ApiEnvelope } from "@/lib/data/types";

export type DashboardCounts = Record<string, number>;
export type DashboardQueues = { mine?: DashboardCounts; unassigned?: DashboardCounts };
export interface AdminDashboard {
  resources: {
    tutor_profiles?: DashboardQueues;
    reports?: DashboardQueues;
    reviews?: DashboardCounts;
    notification_outbox?: { pending: number; failed: number };
  };
  permissions: string[];
}

export async function getAdminDashboard() {
  const response = await request<ApiEnvelope<AdminDashboard>>({ url: "/admin/dashboard" });
  return { ...response.data.data, updatedAt: new Date().toISOString() };
}
