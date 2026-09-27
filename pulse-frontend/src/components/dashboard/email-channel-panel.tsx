"use client";

import { useCallback, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input, Label, Textarea } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { ApiError } from "@/lib/api/client";
import { projectsApi } from "@/lib/api/notifications";
import type { EmailContact, EmailGuide, PulseProject } from "@/lib/api/types";

function errorText(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    const first = error.errors ? Object.values(error.errors)[0]?.[0] : undefined;
    return first ?? error.message;
  }

  return error instanceof Error ? error.message : fallback;
}

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

export function EmailChannelPanel({
  projectKey,
  project,
  guide,
  onProjectUpdated,
}: {
  projectKey: string;
  project: PulseProject;
  guide: EmailGuide | null;
  onProjectUpdated: (project: PulseProject) => void;
}) {
  const [apiKey, setApiKey] = useState("");
  const [fromName, setFromName] = useState(project.email_from_name ?? "");
  const [fromAddress, setFromAddress] = useState(project.email_from_address ?? "");
  const [replyTo, setReplyTo] = useState(project.email_reply_to ?? "");
  const [webhookSecret, setWebhookSecret] = useState("");
  const [contacts, setContacts] = useState<EmailContact[]>([]);
  const [contactStats, setContactStats] = useState({ total: 0, active: 0 });
  const [testTo, setTestTo] = useState("delivered@resend.dev");
  const [testSubject, setTestSubject] = useState("پیام آزمایشی Pulse");
  const [testBody, setTestBody] = useState(
    "اگر این نامه را می‌بینید، کانال ایمیل این پروژه آماده است.",
  );
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [copied, setCopied] = useState(false);

  const loadContacts = useCallback(async () => {
    const payload = await projectsApi.emailContacts(projectKey);
    setContacts(payload.contacts);
    setContactStats(payload.stats);
  }, [projectKey]);

  useEffect(() => {
    void loadContacts().catch(() => {
      setContacts([]);
    });
  }, [loadContacts]);

  useEffect(() => {
    setFromName(project.email_from_name ?? "");
    setFromAddress(project.email_from_address ?? "");
    setReplyTo(project.email_reply_to ?? "");
  }, [project.email_from_address, project.email_from_name, project.email_reply_to]);

  const save = async () => {
    setSaving(true);
    setMessage(null);
    setError(null);
    try {
      const updated = await projectsApi.updateEmailSettings(projectKey, {
        resend_api_key: apiKey.trim() || undefined,
        email_from_name: fromName.trim() || null,
        email_from_address: fromAddress.trim(),
        email_reply_to: replyTo.trim() || null,
        resend_webhook_secret: webhookSecret.trim() || undefined,
      });
      onProjectUpdated(updated);
      setApiKey("");
      setWebhookSecret("");
      setMessage("تنظیمات ایمیل ذخیره شد. کلید API دوباره نمایش داده نمی‌شود.");
    } catch (err) {
      setError(errorText(err, "ذخیره ناموفق بود"));
    } finally {
      setSaving(false);
    }
  };

  const sendTest = async () => {
    setTesting(true);
    setMessage(null);
    setError(null);
    try {
      const response = await projectsApi.sendTestEmail(projectKey, {
        to: testTo.trim(),
        subject: testSubject.trim() || undefined,
        body: testBody.trim() || undefined,
      });
      setMessage(response.message);
      await loadContacts();
    } catch (err) {
      setError(errorText(err, "ارسال آزمایشی ناموفق بود"));
    } finally {
      setTesting(false);
    }
  };

  const copyWebhook = async () => {
    if (!guide?.webhook_url) return;
    await navigator.clipboard.writeText(guide.webhook_url);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="space-y-5">
      <div className="space-y-3">
        <p className="text-sm font-medium">راهنمای راه‌اندازی Resend</p>
        {(guide?.steps ?? []).map((step, index) => (
          <div key={step.title} className="rounded-lg border p-3">
            <p className="font-medium">
              {index + 1}. {step.title}
            </p>
            <p className="mt-1 text-sm leading-6 text-muted-foreground">{step.body}</p>
          </div>
        ))}
      </div>

      <div className="space-y-3">
        <p className="text-sm font-medium">تنظیمات ارسال</p>
        <div className="space-y-2">
          <Label>Resend API Key</Label>
          <Input
            dir="ltr"
            type="password"
            autoComplete="off"
            value={apiKey}
            placeholder={
              project.has_email
                ? "برای جایگزینی، کلید جدید را paste کنید"
                : "re_xxxxxxxxx"
            }
            onChange={(event) => setApiKey(event.target.value)}
          />
          <p className="text-xs text-muted-foreground">
            دسترسی Sending access، محدود به دامنهٔ وریفای‌شده. کلید بعد از ذخیره مخفی می‌ماند.
          </p>
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          <div className="space-y-2">
            <Label>نام فرستنده</Label>
            <Input
              value={fromName}
              placeholder="مثلاً آکمه"
              onChange={(event) => setFromName(event.target.value)}
            />
          </div>
          <div className="space-y-2">
            <Label>آدرس From</Label>
            <Input
              dir="ltr"
              value={fromAddress}
              placeholder="hello@notifications.example.com"
              onChange={(event) => setFromAddress(event.target.value)}
            />
          </div>
        </div>
        <div className="space-y-2">
          <Label>Reply-To (اختیاری)</Label>
          <Input
            dir="ltr"
            value={replyTo}
            placeholder="support@example.com"
            onChange={(event) => setReplyTo(event.target.value)}
          />
        </div>
        <div className="space-y-2">
          <Label>Webhook signing secret (اختیاری)</Label>
          {guide?.webhook_url && (
            <div className="flex gap-2">
              <Input value={guide.webhook_url} readOnly dir="ltr" />
              <Button type="button" variant="outline" onClick={() => void copyWebhook()}>
                {copied ? "کپی شد" : "کپی"}
              </Button>
            </div>
          )}
          <Input
            dir="ltr"
            type="password"
            autoComplete="off"
            value={webhookSecret}
            placeholder={
              project.has_email_webhook ? "secret ذخیره شده است" : "whsec_xxxxxxxxx"
            }
            onChange={(event) => setWebhookSecret(event.target.value)}
          />
        </div>
        <Button type="button" onClick={() => void save()} disabled={saving}>
          {saving ? "در حال ذخیره..." : "ذخیره تنظیمات ایمیل"}
        </Button>
      </div>

      <div className="space-y-3 rounded-lg border p-3">
        <p className="text-sm font-medium">ارسال آزمایشی</p>
        <p className="text-xs text-muted-foreground">
          برای تست امن از delivered@resend.dev استفاده کنید. این ارسال به سهمیهٔ اکانت Resend حساب می‌شود.
        </p>
        <div className="grid gap-3 md:grid-cols-2">
          <div className="space-y-2">
            <Label>گیرنده</Label>
            <Input dir="ltr" value={testTo} onChange={(event) => setTestTo(event.target.value)} />
          </div>
          <div className="space-y-2">
            <Label>موضوع</Label>
            <Input value={testSubject} onChange={(event) => setTestSubject(event.target.value)} />
          </div>
        </div>
        <div className="space-y-2">
          <Label>متن</Label>
          <Textarea value={testBody} onChange={(event) => setTestBody(event.target.value)} />
        </div>
        <Button
          type="button"
          variant="outline"
          onClick={() => void sendTest()}
          disabled={testing || !project.has_email}
        >
          {testing ? "در حال ارسال..." : "ارسال ایمیل آزمایشی"}
        </Button>
      </div>

      <div className="space-y-2">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="font-medium">مخاطبین ایمیل</span>
          <Badge variant="outline">کل {contactStats.total}</Badge>
          <Badge variant="outline">فعال {contactStats.active}</Badge>
        </div>
        {contacts.length === 0 ? (
          <p className="text-sm text-muted-foreground">
            هنوز مخاطبی ثبت نشده. اپ شما با API Key پروژه، ایمیل را در{" "}
            <span dir="ltr">/email/contacts</span> ثبت می‌کند.
          </p>
        ) : (
          <div className="overflow-x-auto rounded-lg border">
            <table className="w-full min-w-140 text-sm">
              <thead className="bg-muted/50 text-muted-foreground">
                <tr>
                  <th className="p-2 text-right">ایمیل</th>
                  <th className="p-2 text-right">کاربر</th>
                  <th className="p-2 text-right">وضعیت</th>
                </tr>
              </thead>
              <tbody>
                {contacts.map((contact) => (
                  <tr key={contact.id} className="border-t">
                    <td className="p-2 font-mono text-xs" dir="ltr">
                      {contact.email}
                    </td>
                    <td className="p-2 font-mono text-xs" dir="ltr">
                      {contact.external_user_id ?? "—"}
                    </td>
                    <td className="p-2">
                      {contact.is_active ? (
                        <Badge variant="success">فعال</Badge>
                      ) : (
                        <Badge variant="warning">غیرفعال</Badge>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {guide && (
        <div className="space-y-4">
          <p className="text-sm font-medium">نمونه کد</p>
          <div>
            <p className="mb-2 text-sm text-muted-foreground">ثبت مخاطب</p>
            <CodeBlock value={guide.samples.register_contact_curl} />
          </div>
          <div>
            <p className="mb-2 text-sm text-muted-foreground">ارسال ایمیل</p>
            <CodeBlock value={guide.samples.send_email_curl} />
          </div>
        </div>
      )}

      {(message || error) && (
        <p className={`text-sm ${error ? "text-destructive" : "text-emerald-600"}`}>
          {error ?? message}
        </p>
      )}
    </div>
  );
}
