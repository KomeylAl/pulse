"use client";

import { useEffect, useState } from "react";
import Link from "next/link";

import { Button } from "@/components/ui/button";
import { Input, Label, Textarea } from "@/components/ui/input";
import { useProject } from "@/contexts/project-context";
import { notificationsApi, projectsApi } from "@/lib/api/notifications";
import type { ProjectDevice, ProjectSubscriber } from "@/lib/api/types";

export function ProjectPushTestForm() {
  const { projectKey, projects } = useProject();
  const project = projects.find((item) => item.key === projectKey);

  const [devices, setDevices] = useState<ProjectDevice[]>([]);
  const [subscribers, setSubscribers] = useState<ProjectSubscriber[]>([]);
  const [target, setTarget] = useState<"all" | "users" | "devices">("all");
  const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
  const [selectedDevices, setSelectedDevices] = useState<number[]>([]);
  const [title, setTitle] = useState("تست Pulse");
  const [body, setBody] = useState("این یک نوتیفیکیشن تستی است.");
  const [deepLink, setDeepLink] = useState("");
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const loadDevices = async () => {
    if (!projectKey) return;
    try {
      const response = await projectsApi.devices(projectKey);
      setDevices(response.devices.filter((d) => d.is_active));
      setSubscribers(response.subscribers);
    } catch {
      setDevices([]);
      setSubscribers([]);
    }
  };

  useEffect(() => {
    void loadDevices();
  }, [projectKey]);

  const toggleUser = (id: string) => {
    setSelectedUsers((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    );
  };

  const toggleDevice = (id: number) => {
    setSelectedDevices((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    );
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!projectKey) return;

    setLoading(true);
    setMessage(null);
    setError(null);

    try {
      await notificationsApi.projectPush({
        title,
        body,
        target,
        external_user_ids: target === "users" ? selectedUsers : undefined,
        device_ids: target === "devices" ? selectedDevices : undefined,
        data: deepLink ? { url: deepLink } : undefined,
        priority: "high",
      });
      setMessage("نوتیفیکیشن تستی در صف ارسال قرار گرفت.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "ارسال ناموفق بود");
    } finally {
      setLoading(false);
    }
  };

  if (!projectKey) {
    return (
      <div className="pulse-surface p-6 text-sm text-muted-foreground">
        ابتدا یک پروژه بسازید یا انتخاب کنید.{" "}
        <Link href="/dashboard/projects" className="font-medium text-primary underline-offset-4 hover:underline">
          رفتن به پروژه‌ها
        </Link>
      </div>
    );
  }

  return (
    <section className="pulse-surface p-5">
      <div className="mb-5">
        <h2 className="text-base font-semibold">ارسال تستی Push</h2>
        <p className="mt-1 text-sm text-muted-foreground">
          پروژه فعال: <strong>{project?.name ?? projectKey}</strong> — ارسال
          لحظه‌ای به دستگاه‌های ثبت‌شده همین پروژه
        </p>
      </div>
      <form onSubmit={(e) => void handleSubmit(e)} className="space-y-5">
        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <span className="rounded-md border border-border bg-muted/40 px-2 py-1">
            دستگاه فعال: {devices.length.toLocaleString("fa-IR")}
          </span>
          <span className="rounded-md border border-border bg-muted/40 px-2 py-1">
            کاربر خارجی: {subscribers.length.toLocaleString("fa-IR")}
          </span>
          <Button type="button" variant="outline" size="sm" onClick={() => void loadDevices()}>
            بروزرسانی لیست
          </Button>
        </div>

        <div className="grid gap-2 sm:grid-cols-3">
          {(
            [
              ["all", "همه دستگاه‌ها"],
              ["users", "کاربران انتخابی"],
              ["devices", "دستگاه‌های انتخابی"],
            ] as const
          ).map(([value, label]) => (
            <Button
              key={value}
              type="button"
              variant={target === value ? "default" : "outline"}
              onClick={() => setTarget(value)}
            >
              {label}
            </Button>
          ))}
        </div>

        {target === "users" && (
          <div className="max-h-48 space-y-2 overflow-y-auto rounded-xl border border-border bg-background/60 p-3">
            {subscribers.length === 0 && (
              <p className="text-sm text-muted-foreground">
                هنوز کاربری با external_user_id ثبت نشده.
              </p>
            )}
            {subscribers.map((subscriber) => (
              <label
                key={subscriber.external_user_id}
                className="flex items-center gap-2 text-sm"
              >
                <input
                  type="checkbox"
                  checked={selectedUsers.includes(subscriber.external_user_id)}
                  onChange={() => toggleUser(subscriber.external_user_id)}
                />
                <span dir="ltr">{subscriber.external_user_id}</span>
                <span className="text-xs text-muted-foreground">
                  ({subscriber.devices_count.toLocaleString("fa-IR")} دستگاه)
                </span>
              </label>
            ))}
          </div>
        )}

        {target === "devices" && (
          <div className="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-border bg-background/60 p-3">
            {devices.length === 0 && (
              <p className="text-sm text-muted-foreground">
                دستگاه فعالی برای این پروژه نیست. از اپ PWA توکن ثبت کنید.
              </p>
            )}
            {devices.map((device) => (
              <label key={device.id} className="flex items-start gap-2 text-sm">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={selectedDevices.includes(device.id)}
                  onChange={() => toggleDevice(device.id)}
                />
                <span>
                  <span className="font-medium">#{device.id}</span>{" "}
                  {device.platform_label}
                  {device.external_user_id && (
                    <span className="text-muted-foreground" dir="ltr">
                      {" "}
                      · {device.external_user_id}
                    </span>
                  )}
                  <br />
                  <span className="font-mono text-xs text-muted-foreground" dir="ltr">
                    {device.token_preview}
                  </span>
                </span>
              </label>
            ))}
          </div>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label>عنوان</Label>
            <Input value={title} onChange={(e) => setTitle(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label>لینک (اختیاری)</Label>
            <Input
              value={deepLink}
              onChange={(e) => setDeepLink(e.target.value)}
              placeholder="https://..."
              dir="ltr"
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label>متن</Label>
          <Textarea value={body} onChange={(e) => setBody(e.target.value)} required />
        </div>

        {message && <p className="text-sm text-emerald-600">{message}</p>}
        {error && <p className="text-sm text-destructive">{error}</p>}

        <Button type="submit" disabled={loading || devices.length === 0}>
          {loading ? "در حال ارسال..." : "ارسال تستی الان"}
        </Button>
      </form>
    </section>
  );
}
