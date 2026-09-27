export type NotificationChannel = "sms" | "push" | "email";
export type NotificationStatus =
  | "pending"
  | "processing"
  | "sent"
  | "failed"
  | "cancelled"
  | "scheduled"
  | "active";
export type NotificationPriority = "normal" | "high";
export type DevicePlatform = "android" | "pwa";
export type ProjectType = "pwa" | "android" | "both";

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  created_at: string | null;
}

export interface FirebaseWebConfig {
  apiKey?: string;
  authDomain?: string;
  projectId?: string;
  storageBucket?: string;
  messagingSenderId?: string;
  appId?: string;
}

export interface PulseProject {
  id: number;
  key: string;
  name: string;
  type: ProjectType;
  type_label?: string;
  api_key?: string;
  has_firebase: boolean;
  has_web_config: boolean;
  has_vapid_key: boolean;
  has_email: boolean;
  has_email_webhook?: boolean;
  email_from_address?: string | null;
  email_from_name?: string | null;
  email_reply_to?: string | null;
  firebase_web_config?: FirebaseWebConfig | null;
  vapid_key?: string | null;
  fcm_web_icon: string;
  fcm_default_link: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface IntegrationGuide {
  project: PulseProject;
  steps: { title: string; body: string }[];
  samples: {
    register_device_curl: string;
    send_push_curl: string;
    pwa_js: string;
    android_kotlin: string;
  };
  email?: EmailGuide;
}

export interface EmailGuide {
  webhook_url: string;
  steps: { title: string; body: string }[];
  samples: {
    register_contact_curl: string;
    send_email_curl: string;
  };
}

export interface EmailContact {
  id: number;
  project_key: string;
  email: string;
  name: string | null;
  external_user_id: string | null;
  is_active: boolean;
  unsubscribed_at: string | null;
  last_used_at: string | null;
  created_at: string | null;
}

export interface NotificationLog {
  id: number;
  project_key?: string;
  campaign_id: number | null;
  user_id: number | null;
  external_user_id?: string | null;
  user?: User;
  channel: NotificationChannel;
  channel_label: string;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  status: NotificationStatus;
  status_label: string;
  priority: NotificationPriority;
  recipient: string | null;
  provider_message_id?: string | null;
  delivery_status?: string | null;
  error_message: string | null;
  attempts: number;
  scheduled_at: string | null;
  sent_at: string | null;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationCampaign {
  id: number;
  project_key?: string;
  title: string;
  body: string;
  image_url?: string | null;
  channels: NotificationChannel[];
  target_type: "user" | "users" | "broadcast" | "project_devices" | "external_users";
  target_type_label: string;
  target_user_ids: number[] | null;
  target_external_user_ids?: string[] | null;
  data: Record<string, unknown> | null;
  status: NotificationStatus;
  status_label: string;
  priority: NotificationPriority;
  scheduled_at: string | null;
  is_recurring?: boolean;
  recurrence?: "once" | "daily" | "dates" | "weekly" | string | null;
  schedule_times?: string[] | null;
  schedule_config?: {
    starts_on?: string;
    ends_on?: string;
    dates?: string[];
    weekdays?: number[];
  } | null;
  processed_at: string | null;
  total_recipients: number;
  sent_count: number;
  failed_count: number;
  error_message: string | null;
  created_at: string | null;
}

export interface ProjectDevice {
  id: number;
  project_key: string;
  token: string;
  token_preview: string;
  platform: DevicePlatform;
  platform_label: string;
  external_user_id: string | null;
  device_name: string | null;
  device_id: string | null;
  is_active: boolean;
  last_used_at: string | null;
  created_at: string | null;
}

export interface ProjectSubscriber {
  external_user_id: string;
  devices_count: number;
  last_used_at: string | null;
  registered_at: string | null;
}

export interface ProjectDevicesResponse {
  devices: ProjectDevice[];
  subscribers: ProjectSubscriber[];
  stats: {
    total_devices: number;
    active_devices: number;
    subscribers: number;
  };
}

export interface ProjectPushPayload {
  title: string;
  body: string;
  target: "all" | "users" | "devices";
  external_user_ids?: string[];
  device_ids?: number[];
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
  scheduled_at?: string;
}

export interface RecurringCampaignPayload {
  title: string;
  body: string;
  schedule_times: string[];
  target: "all" | "users";
  external_user_ids?: string[];
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
  image_url?: string;
  starts_on?: string;
  ends_on?: string;
}

export type CampaignScheduleType = "once" | "daily" | "dates" | "weekly";

export interface CreateCampaignPayload {
  title: string;
  body: string;
  schedule_type: CampaignScheduleType;
  target: "all" | "users";
  external_user_ids?: string[];
  schedule_times?: string[];
  scheduled_at?: string;
  starts_on?: string;
  ends_on?: string;
  dates?: string[];
  weekdays?: number[];
  image_url?: string;
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  links?: Record<string, string | null>;
}

export interface NotificationStats {
  notifications: {
    total: number;
    sent: number;
    failed: number;
    pending: number;
    scheduled: number;
  };
  campaigns: {
    total: number;
    active: number;
  };
  channels: Record<string, number>;
}

export interface SendNotificationPayload {
  user_id?: number;
  phone?: string;
  title: string;
  body: string;
  channels: NotificationChannel[];
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
  scheduled_at?: string;
}

export interface SendBulkPayload {
  user_ids: number[];
  title: string;
  body: string;
  channels: NotificationChannel[];
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
  scheduled_at?: string;
}

export interface BroadcastPayload {
  title: string;
  body: string;
  channels: NotificationChannel[];
  data?: Record<string, unknown>;
  priority?: NotificationPriority;
  scheduled_at?: string;
}
