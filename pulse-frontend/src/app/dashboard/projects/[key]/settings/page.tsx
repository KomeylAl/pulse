"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { projectsApi } from "@/lib/api/notifications";
import type { IntegrationGuide, ProjectDevice, ProjectSubscriber, PulseProject } from "@/lib/api/types";

function CodeBlock({ value }: { value: string }) {
  return (
    <pre
      className="max-h-80 overflow-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100"
      dir="ltr"
    >
      <code>{value}</code>
    </pre>
  );
}

export default function ProjectSettingsPage() {
  const params = useParams<{ key: string }>();
  const key = params.key;

  const [project, setProject] = useState<PulseProject | null>(null);
  const [guide, setGuide] = useState<IntegrationGuide | null>(null);
  const [devices, setDevices] = useState<ProjectDevice[]>([]);
  const [subscribers, setSubscribers] = useState<ProjectSubscriber[]>([]);
  const [deviceStats, setDeviceStats] = useState({
    total_devices: 0,
    active_devices: 0,
    subscribers: 0,
  });
  const [credentials, setCredentials] = useState("");
  const [vapidKey, setVapidKey] = useState("");
  const [webConfig, setWebConfig] = useState({
    apiKey: "",
    authDomain: "",
    projectId: "",
    storageBucket: "",
    messagingSenderId: "",
    appId: "",
  });
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [proj, integration, devicePayload] = await Promise.all([
        projectsApi.get(key),
        projectsApi.integrationGuide(key),
        projectsApi.devices(key),
      ]);
      setProject(proj);
      setGuide(integration);
      setDevices(devicePayload.devices);
      setSubscribers(devicePayload.subscribers);
      setDeviceStats(devicePayload.stats);
      setVapidKey(proj.vapid_key ?? "");
      setWebConfig({
        apiKey: proj.firebase_web_config?.apiKey ?? "",
        authDomain: proj.firebase_web_config?.authDomain ?? "",
        projectId: proj.firebase_web_config?.projectId ?? "",
        storageBucket: proj.firebase_web_config?.storageBucket ?? "",
        messagingSenderId: proj.firebase_web_config?.messagingSenderId ?? "",
        appId: proj.firebase_web_config?.appId ?? "",
      });
    } catch (err) {
      setError(err instanceof Error ? err.message : "بارگذاری ناموفق بود");
    } finally {
      setLoading(false);
    }
  }, [key]);

  useEffect(() => {
    void load();
  }, [load]);

  const savePushSettings = async () => {
    setSaving(true);
    setMessage(null);
    setError(null);
    try {
      const updated = await projectsApi.updatePushSettings(key, {
        firebase_credentials: credentials.trim() || undefined,
        firebase_web_config: webConfig,
        vapid_key: vapidKey || null,
      });
      setProject(updated);
      setCredentials("");
      setMessage("تنظیمات Push ذخیره شد.");
      const integration = await projectsApi.integrationGuide(key);
      setGuide(integration);
    } catch (err) {
      setError(err instanceof Error ? err.message : "ذخیره ناموفق بود");
    } finally {
      setSaving(false);
    }
  };

  const regenerateKey = async () => {
    if (!confirm("API Key جدید جایگزین کلید فعلی می‌شود. ادامه؟")) return;
    try {
      const response = await projectsApi.regenerateApiKey(key);
      setProject(response.project);
      setMessage("API Key جدید ساخته شد.");
      const integration = await projectsApi.integrationGuide(key);
      setGuide(integration);
    } catch (err) {
      setError(err instanceof Error ? err.message : "عملیات ناموفق بود");
    }
  };

  if (loading) {
    return <p className="text-sm text-muted-foreground">در حال بارگذاری...</p>;
  }

  if (!project) {
    return (
      <p className="text-sm text-destructive">{error ?? "پروژه پیدا نشد"}</p>
    );
  }

  return (
    <div className="mx-auto max-w-4xl space-y-6" dir="rtl">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link
            href="/dashboard/projects"
            className="text-sm text-muted-foreground hover:text-foreground"
          >
            ← بازگشت به پروژه‌ها
          </Link>
          <h1 className="mt-2 text-2xl font-semibold">{project.name}</h1>
          <p className="font-mono text-xs text-muted-foreground" dir="ltr">
            {project.key}
          </p>
        </div>
        <div className="flex gap-2">
          {project.has_firebase ? (
            <Badge variant="success">Firebase آماده</Badge>
          ) : (
            <Badge variant="warning">Firebase ناقص</Badge>
          )}
          <Badge variant="outline">{project.type_label ?? project.type}</Badge>
        </div>
      </div>

      {(message || error) && (
        <p className={`text-sm ${error ? "text-destructive" : "text-emerald-600"}`}>
          {error ?? message}
        </p>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">کاربران و دستگاه‌های ثبت‌شده</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-3 text-sm text-muted-foreground">
            <span>کل دستگاه: {deviceStats.total_devices}</span>
            <span>فعال: {deviceStats.active_devices}</span>
            <span>کاربر (external_user_id): {deviceStats.subscribers}</span>
            <Link href="/dashboard/send" className="text-primary underline">
              ارسال تستی
            </Link>
          </div>

          {subscribers.length > 0 && (
            <div>
              <p className="mb-2 text-sm font-medium">کاربران</p>
              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                      <th className="p-2 text-right">external_user_id</th>
                      <th className="p-2 text-right">تعداد دستگاه</th>
                      <th className="p-2 text-right">آخرین استفاده</th>
                    </tr>
                  </thead>
                  <tbody>
                    {subscribers.map((subscriber) => (
                      <tr key={subscriber.external_user_id} className="border-t">
                        <td className="p-2 font-mono" dir="ltr">
                          {subscriber.external_user_id}
                        </td>
                        <td className="p-2">{subscriber.devices_count}</td>
                        <td className="p-2 text-xs" dir="ltr">
                          {subscriber.last_used_at ?? "—"}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          <div>
            <p className="mb-2 text-sm font-medium">دستگاه‌ها / توکن‌ها</p>
            {devices.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                هنوز دستگاهی ثبت نشده. از اپ PWA با API Key این پروژه توکن ثبت کنید.
              </p>
            ) : (
              <div className="overflow-x-auto rounded-lg border">
                <table className="w-full min-w-[640px] text-sm">
                  <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                      <th className="p-2 text-right">ID</th>
                      <th className="p-2 text-right">کاربر</th>
                      <th className="p-2 text-right">پلتفرم</th>
                      <th className="p-2 text-right">توکن</th>
                      <th className="p-2 text-right">وضعیت</th>
                    </tr>
                  </thead>
                  <tbody>
                    {devices.map((device) => (
                      <tr key={device.id} className="border-t align-top">
                        <td className="p-2">{device.id}</td>
                        <td className="p-2 font-mono text-xs" dir="ltr">
                          {device.external_user_id ?? "—"}
                        </td>
                        <td className="p-2">{device.platform_label}</td>
                        <td className="p-2 font-mono text-xs break-all" dir="ltr">
                          {device.token_preview}
                        </td>
                        <td className="p-2">
                          {device.is_active ? (
                            <Badge variant="success">فعال</Badge>
                          ) : (
                            <Badge variant="outline">غیرفعال</Badge>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">API Key پروژه</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <p className="text-sm text-muted-foreground">
            این کلید را در اپلیکیشن خود با هدر{" "}
            <code dir="ltr">X-Pulse-Api-Key</code> بفرستید.
          </p>
          <Input value={project.api_key ?? ""} readOnly dir="ltr" />
          <Button variant="outline" size="sm" onClick={() => void regenerateKey()}>
            تولید مجدد API Key
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">راهنمای راه‌اندازی Firebase</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {guide?.steps.map((step, index) => (
            <div key={step.title} className="rounded-lg border p-3">
              <p className="font-medium">
                {index + 1}. {step.title}
              </p>
              <p className="mt-1 text-sm text-muted-foreground">{step.body}</p>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Push Notification — تنظیمات</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label>Service Account JSON (سرور)</Label>
            <textarea
              className="min-h-36 w-full rounded-md border border-input bg-transparent p-3 font-mono text-xs"
              dir="ltr"
              placeholder={
                project.has_firebase
                  ? "برای جایگزینی، JSON جدید را paste کنید (خالی بگذارید تا قبلی بماند)"
                  : '{"type":"service_account", ...}'
              }
              value={credentials}
              onChange={(e) => setCredentials(e.target.value)}
            />
          </div>

          <div className="grid gap-3 md:grid-cols-2">
            {(
              [
                ["apiKey", "apiKey"],
                ["authDomain", "authDomain"],
                ["projectId", "projectId"],
                ["storageBucket", "storageBucket"],
                ["messagingSenderId", "messagingSenderId"],
                ["appId", "appId"],
              ] as const
            ).map(([field, label]) => (
              <div key={field} className="space-y-2">
                <Label>{label}</Label>
                <Input
                  dir="ltr"
                  value={webConfig[field]}
                  onChange={(e) =>
                    setWebConfig((prev) => ({ ...prev, [field]: e.target.value }))
                  }
                />
              </div>
            ))}
          </div>

          <div className="space-y-2">
            <Label>VAPID Key (Web Push)</Label>
            <Input
              dir="ltr"
              value={vapidKey}
              onChange={(e) => setVapidKey(e.target.value)}
            />
          </div>

          <Button onClick={() => void savePushSettings()} disabled={saving}>
            {saving ? "در حال ذخیره..." : "ذخیره تنظیمات Push"}
          </Button>
        </CardContent>
      </Card>

      {guide && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">نمونه کد یکپارچه‌سازی</CardTitle>
          </CardHeader>
          <CardContent className="space-y-5">
            <div>
              <p className="mb-2 text-sm font-medium">ثبت توکن (curl)</p>
              <CodeBlock value={guide.samples.register_device_curl} />
            </div>
            <div>
              <p className="mb-2 text-sm font-medium">ارسال Push (curl)</p>
              <CodeBlock value={guide.samples.send_push_curl} />
            </div>
            <div>
              <p className="mb-2 text-sm font-medium">PWA / JavaScript</p>
              <CodeBlock value={guide.samples.pwa_js} />
            </div>
            <div>
              <p className="mb-2 text-sm font-medium">Android (Kotlin)</p>
              <CodeBlock value={guide.samples.android_kotlin} />
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
