"use client";

import { useEffect, useState } from "react";
import { Smartphone, Users } from "lucide-react";

import { PageHeader } from "@/components/dashboard/page-header";
import { StatCard } from "@/components/dashboard/stat-card";
import { Badge } from "@/components/ui/badge";
import { useProject } from "@/contexts/project-context";
import { projectsApi } from "@/lib/api/notifications";
import type { ProjectDevicesResponse } from "@/lib/api/types";

export default function DevicesPage() {
  const { projectKey, projects } = useProject();
  const project = projects.find((item) => item.key === projectKey);
  const [data, setData] = useState<ProjectDevicesResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!projectKey) {
      setLoading(false);
      return;
    }
    setLoading(true);
    void projectsApi
      .devices(projectKey)
      .then(setData)
      .finally(() => setLoading(false));
  }, [projectKey]);

  return (
    <div className="space-y-6" dir="rtl">
      <PageHeader
        title="دستگاه‌ها و مشترکین"
        description={
          project
            ? `دستگاه‌های ثبت‌شده در پروژه «${project.name}»`
            : "ابتدا یک پروژه فعال انتخاب کنید"
        }
      />

      <div className="grid gap-4 sm:grid-cols-3">
        <StatCard
          title="کل دستگاه‌ها"
          value={data?.stats.total_devices ?? 0}
          icon={Smartphone}
          tone="primary"
        />
        <StatCard
          title="فعال"
          value={data?.stats.active_devices ?? 0}
          icon={Smartphone}
          tone="success"
        />
        <StatCard
          title="مشترکین"
          value={data?.stats.subscribers ?? 0}
          icon={Users}
          tone="info"
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <section className="pulse-surface p-5">
          <h2 className="mb-4 text-base font-semibold">لیست دستگاه‌ها</h2>
          {loading && (
            <p className="text-sm text-muted-foreground">در حال بارگذاری...</p>
          )}
          {!loading && (!data || data.devices.length === 0) && (
            <p className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
              هنوز دستگاهی ثبت نشده است.
            </p>
          )}
          <div className="space-y-3">
            {data?.devices.map((device) => (
              <div
                key={device.id}
                className="rounded-xl border border-border/80 bg-background/60 p-4 transition hover:border-primary/30"
              >
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-medium">
                    {device.device_name ?? "دستگاه بدون نام"}
                  </span>
                  <Badge variant="outline">{device.platform_label}</Badge>
                  {device.is_active ? (
                    <Badge variant="success">فعال</Badge>
                  ) : (
                    <Badge variant="destructive">غیرفعال</Badge>
                  )}
                </div>
                <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                  <p dir="ltr" className="font-mono">
                    {device.token_preview}
                  </p>
                  {device.external_user_id && (
                    <p>
                      کاربر خارجی:{" "}
                      <span dir="ltr">{device.external_user_id}</span>
                    </p>
                  )}
                  {device.last_used_at && (
                    <p>
                      آخرین استفاده:{" "}
                      {new Date(device.last_used_at).toLocaleString("fa-IR")}
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="pulse-surface p-5">
          <h2 className="mb-4 text-base font-semibold">مشترکین</h2>
          {!loading && (!data || data.subscribers.length === 0) && (
            <p className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
              مشترکی با external_user_id ثبت نشده است.
            </p>
          )}
          <div className="space-y-3">
            {data?.subscribers.map((subscriber) => (
              <div
                key={subscriber.external_user_id}
                className="flex items-center justify-between gap-3 rounded-xl border border-border/80 bg-background/60 p-4"
              >
                <div>
                  <p className="font-mono text-sm font-medium" dir="ltr">
                    {subscriber.external_user_id}
                  </p>
                  <p className="mt-1 text-xs text-muted-foreground">
                    {subscriber.devices_count.toLocaleString("fa-IR")} دستگاه
                    {subscriber.last_used_at &&
                      ` · آخرین: ${new Date(subscriber.last_used_at).toLocaleString("fa-IR")}`}
                  </p>
                </div>
                <Badge variant="outline">مشترک</Badge>
              </div>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}
