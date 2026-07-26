"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import {
  Bell,
  CheckCircle2,
  Clock,
  Megaphone,
  Send,
  Smartphone,
  Users,
  XCircle,
} from "lucide-react";

import {
  CampaignSuccessRing,
  ChannelPieChart,
  StatusBarChart,
} from "@/components/dashboard/charts";
import { PageHeader } from "@/components/dashboard/page-header";
import { StatCard } from "@/components/dashboard/stat-card";
import { useProject } from "@/contexts/project-context";
import { notificationsApi, projectsApi } from "@/lib/api/notifications";
import type { NotificationStats, ProjectDevicesResponse } from "@/lib/api/types";

export default function DashboardPage() {
  const { projectKey, projects } = useProject();
  const project = projects.find((item) => item.key === projectKey);
  const [stats, setStats] = useState<NotificationStats | null>(null);
  const [devices, setDevices] = useState<ProjectDevicesResponse | null>(null);

  useEffect(() => {
    void notificationsApi.stats().then(setStats);
  }, [projectKey]);

  useEffect(() => {
    if (!projectKey) return;
    void projectsApi.devices(projectKey).then(setDevices);
  }, [projectKey]);

  const successRateTotal =
    (stats?.notifications.sent ?? 0) + (stats?.notifications.failed ?? 0);

  return (
    <div className="space-y-8">
      <PageHeader
        title="داشبورد"
        description={
          project
            ? `نمای زنده پروژه «${project.name}» — ارسال، دستگاه‌ها و کمپین‌ها`
            : "نمای کلی از وضعیت ارسال SMS و Push"
        }
        action={
          <div className="flex flex-wrap gap-2">
            <Link
              href="/dashboard/send"
              className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg bg-primary px-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/80"
            >
              <Send className="size-4" />
              ارسال سریع
            </Link>
            <Link
              href="/dashboard/devices"
              className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted"
            >
              <Smartphone className="size-4" />
              دستگاه‌ها
            </Link>
          </div>
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          title="کل نوتیفیکیشن‌ها"
          value={stats?.notifications.total ?? 0}
          icon={Bell}
          tone="primary"
          hint="مجموع همه وضعیت‌ها"
        />
        <StatCard
          title="ارسال‌شده"
          value={stats?.notifications.sent ?? 0}
          icon={CheckCircle2}
          tone="success"
        />
        <StatCard
          title="در صف / زمان‌بندی"
          value={
            (stats?.notifications.pending ?? 0) +
            (stats?.notifications.scheduled ?? 0)
          }
          icon={Clock}
          tone="warning"
        />
        <StatCard
          title="ناموفق"
          value={stats?.notifications.failed ?? 0}
          icon={XCircle}
          tone="danger"
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          title="دستگاه‌های فعال"
          value={devices?.stats.active_devices ?? 0}
          icon={Smartphone}
          tone="info"
          hint={`از ${devices?.stats.total_devices ?? 0} دستگاه`}
        />
        <StatCard
          title="مشترکین"
          value={devices?.stats.subscribers ?? 0}
          icon={Users}
          tone="primary"
        />
        <StatCard
          title="کمپین‌ها"
          value={stats?.campaigns.total ?? 0}
          icon={Megaphone}
          tone="warning"
          hint={`${stats?.campaigns.active ?? 0} فعال`}
        />
        <StatCard
          title="نرخ موفقیت"
          value={
            successRateTotal === 0
              ? "—"
              : `${Math.round(
                  ((stats?.notifications.sent ?? 0) / successRateTotal) * 100,
                ).toLocaleString("fa-IR")}٪`
          }
          icon={CheckCircle2}
          tone="success"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-5">
        <div className="pulse-surface p-5 lg:col-span-3">
          <div className="mb-4">
            <h2 className="text-base font-semibold">وضعیت ارسال‌ها</h2>
            <p className="text-xs text-muted-foreground">
              توزیع نوتیفیکیشن‌ها بر اساس وضعیت
            </p>
          </div>
          {stats ? (
            <StatusBarChart stats={stats} />
          ) : (
            <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
              در حال بارگذاری...
            </div>
          )}
        </div>

        <div className="pulse-surface p-5 lg:col-span-2">
          <div className="mb-4">
            <h2 className="text-base font-semibold">کانال‌ها</h2>
            <p className="text-xs text-muted-foreground">پوش در برابر پیامک</p>
          </div>
          {stats ? (
            <ChannelPieChart stats={stats} />
          ) : (
            <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
              در حال بارگذاری...
            </div>
          )}
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="pulse-surface p-5">
          <h2 className="mb-1 text-base font-semibold">عملکرد تحویل</h2>
          <p className="mb-2 text-xs text-muted-foreground">
            نسبت موفق به ناموفق
          </p>
          {stats ? (
            <CampaignSuccessRing
              sent={stats.notifications.sent}
              failed={stats.notifications.failed}
            />
          ) : (
            <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
              در حال بارگذاری...
            </div>
          )}
        </div>

        <div className="pulse-surface p-5 lg:col-span-2">
          <h2 className="text-base font-semibold">اقدامات سریع</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            میانبرهای پرکاربرد برای پروژه فعال
          </p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            {[
              {
                href: "/dashboard/send",
                title: "ارسال پوش",
                desc: "ارسال به کاربران یا همه دستگاه‌ها",
              },
              {
                href: "/dashboard/campaigns",
                title: "کمپین روزانه",
                desc: "زمان‌بندی چندساعته خودکار",
              },
              {
                href: "/dashboard/history",
                title: "تاریخچه",
                desc: "لاگ و خطای ارسال‌ها",
              },
              {
                href: projectKey
                  ? `/dashboard/projects/${projectKey}/settings`
                  : "/dashboard/projects",
                title: "تنظیمات Firebase",
                desc: project?.has_firebase
                  ? "پیکربندی Push آماده است"
                  : "برای ارسال Push تنظیم کنید",
              },
            ].map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className="rounded-xl border border-border bg-muted/40 p-4 transition-colors hover:bg-muted"
              >
                <p className="font-medium text-foreground">{item.title}</p>
                <p className="mt-1 text-xs text-muted-foreground">{item.desc}</p>
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
