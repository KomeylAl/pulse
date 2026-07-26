import { apiFetch } from "@/lib/api/client";
import type {
  BroadcastPayload,
  CreateCampaignPayload,
  IntegrationGuide,
  NotificationCampaign,
  NotificationLog,
  NotificationStats,
  PaginatedResponse,
  PulseProject,
  ProjectType,
  ProjectDevicesResponse,
  ProjectPushPayload,
  RecurringCampaignPayload,
  SendBulkPayload,
  SendNotificationPayload,
  User,
  FirebaseWebConfig,
} from "@/lib/api/types";

export const authApi = {
  register: (payload: {
    name: string;
    email: string;
    phone?: string;
    password: string;
    password_confirmation: string;
  }) =>
    apiFetch<{ token: string; user: User }>("/auth/register", {
      method: "POST",
      body: JSON.stringify({ ...payload, device_name: "dashboard" }),
    }),
  login: (email: string, password: string) =>
    apiFetch<{ token: string; user: User }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password, device_name: "dashboard" }),
    }),
  logout: () =>
    apiFetch<{ message: string }>("/auth/logout", { method: "POST" }),
  me: () => apiFetch<User>("/auth/me"),
};

export const projectsApi = {
  list: () => apiFetch<PulseProject[]>("/projects"),
  create: (payload: {
    name: string;
    type: ProjectType;
    fcm_default_link?: string;
  }) =>
    apiFetch<{ message: string; project: PulseProject }>("/projects", {
      method: "POST",
      body: JSON.stringify(payload),
    }),
  get: (key: string) => apiFetch<PulseProject>(`/projects/${key}`),
  update: (
    key: string,
    payload: Partial<{
      name: string;
      type: ProjectType;
      fcm_web_icon: string;
      fcm_default_link: string;
    }>,
  ) =>
    apiFetch<PulseProject>(`/projects/${key}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    }),
  remove: (key: string) =>
    apiFetch<{ message: string }>(`/projects/${key}`, { method: "DELETE" }),
  regenerateApiKey: (key: string) =>
    apiFetch<{ message: string; project: PulseProject }>(
      `/projects/${key}/regenerate-api-key`,
      { method: "POST" },
    ),
  updatePushSettings: (
    key: string,
    payload: {
      firebase_credentials?: string | null;
      firebase_web_config?: FirebaseWebConfig | null;
      vapid_key?: string | null;
      fcm_web_icon?: string;
      fcm_default_link?: string;
    },
  ) =>
    apiFetch<PulseProject>(`/projects/${key}/push-settings`, {
      method: "PUT",
      body: JSON.stringify(payload),
    }),
  integrationGuide: (key: string) =>
    apiFetch<IntegrationGuide>(`/projects/${key}/integration-guide`),
  devices: (key: string) =>
    apiFetch<ProjectDevicesResponse>(`/projects/${key}/devices`),
};

export const usersApi = {
  list: () => apiFetch<PaginatedResponse<User>>("/users"),
};

export const notificationsApi = {
  stats: () => apiFetch<NotificationStats>("/notifications/stats"),
  list: (params?: Record<string, string>) => {
    const query = params ? `?${new URLSearchParams(params)}` : "";
    return apiFetch<PaginatedResponse<NotificationLog>>(`/notifications${query}`);
  },
  send: (payload: SendNotificationPayload) =>
    apiFetch<{ message: string; notifications: NotificationLog[] }>(
      "/notifications/send",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  sendBulk: (payload: SendBulkPayload) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      "/notifications/send-bulk",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  broadcast: (payload: BroadcastPayload) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      "/notifications/broadcast",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  projectPush: (payload: ProjectPushPayload) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      "/notifications/project-push",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  createRecurring: (payload: RecurringCampaignPayload) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      "/campaigns/recurring",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  createCampaign: (payload: CreateCampaignPayload) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      "/campaigns",
      { method: "POST", body: JSON.stringify(payload) },
    ),
  campaigns: (params?: Record<string, string>) => {
    const query = params ? `?${new URLSearchParams(params)}` : "";
    return apiFetch<PaginatedResponse<NotificationCampaign>>(`/campaigns${query}`);
  },
  cancelCampaign: (id: number) =>
    apiFetch<{ message: string; campaign: NotificationCampaign }>(
      `/campaigns/${id}/cancel`,
      { method: "POST" },
    ),
  registerDeviceToken: (payload: {
    token: string;
    platform: "android" | "pwa";
    device_name?: string;
  }) =>
    apiFetch("/device-tokens", {
      method: "POST",
      body: JSON.stringify(payload),
    }),
};
