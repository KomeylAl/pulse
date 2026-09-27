"use client";

import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input, Label, Select, Textarea } from "@/components/ui/input";
import { PersianDateTimePicker } from "@/components/notifications/persian-date-picker";
import { notificationsApi, usersApi } from "@/lib/api/notifications";
import type {
  NotificationChannel,
  NotificationPriority,
  User,
} from "@/lib/api/types";

type SendMode = "single" | "bulk" | "broadcast" | "sms";

export function SendNotificationForm() {
  const [mode, setMode] = useState<SendMode>("single");
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [userId, setUserId] = useState("");
  const [phone, setPhone] = useState("");
  const [selectedUsers, setSelectedUsers] = useState<number[]>([]);
  const [channels, setChannels] = useState<NotificationChannel[]>(["push"]);
  const [priority, setPriority] = useState<NotificationPriority>("normal");
  const [scheduledAt, setScheduledAt] = useState<Date | null>(null);
  const [deepLink, setDeepLink] = useState("");

  useEffect(() => {
    void usersApi.list().then((response) => setUsers(response.data));
  }, []);

  const toggleChannel = (channel: NotificationChannel) => {
    setChannels((current) =>
      current.includes(channel)
        ? current.filter((item) => item !== channel)
        : [...current, channel],
    );
  };

  const toggleUser = (id: number) => {
    setSelectedUsers((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    );
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setMessage(null);
    setError(null);

    const payloadData = deepLink ? { url: deepLink } : undefined;
    const scheduledIso = scheduledAt?.toISOString();

    try {
      if (mode === "single") {
        await notificationsApi.send({
          user_id: Number(userId),
          title,
          body,
          channels,
          priority,
          data: payloadData,
          scheduled_at: scheduledIso,
        });
      } else if (mode === "sms") {
        await notificationsApi.send({
          phone,
          title,
          body,
          channels: ["sms"],
          priority,
          data: payloadData,
          scheduled_at: scheduledIso,
        });
      } else if (mode === "bulk") {
        await notificationsApi.sendBulk({
          user_ids: selectedUsers,
          title,
          body,
          channels,
          priority,
          data: payloadData,
          scheduled_at: scheduledIso,
        });
      } else {
        await notificationsApi.broadcast({
          title,
          body,
          channels,
          priority,
          data: payloadData,
          scheduled_at: scheduledIso,
        });
      }

      setMessage("نوتیفیکیشن با موفقیت در صف قرار گرفت.");
      setTitle("");
      setBody("");
      setDeepLink("");
      setScheduledAt(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : "خطا در ارسال");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>ارسال نوتیفیکیشن</CardTitle>
        <CardDescription>
          ارسال تکی، گروهی، Broadcast، ایمیل یا SMS با زمان‌بندی
        </CardDescription>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="grid gap-3 sm:grid-cols-4">
            {(
              [
                ["single", "تکی"],
                ["bulk", "گروهی"],
                ["broadcast", "همه کاربران"],
                ["sms", "SMS مستقیم"],
              ] as const
            ).map(([value, label]) => (
              <Button
                key={value}
                type="button"
                variant={mode === value ? "default" : "outline"}
                onClick={() => setMode(value)}
              >
                {label}
              </Button>
            ))}
          </div>

          {mode === "single" && (
            <div className="space-y-2">
              <Label>کاربر</Label>
              <Select value={userId} onChange={(e) => setUserId(e.target.value)} required>
                <option value="">انتخاب کاربر</option>
                {users.map((user) => (
                  <option key={user.id} value={user.id}>
                    {user.name} ({user.email})
                  </option>
                ))}
              </Select>
            </div>
          )}

          {mode === "bulk" && (
            <div className="space-y-2">
              <Label>کاربران</Label>
              <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-border p-3">
                {users.map((user) => (
                  <label key={user.id} className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={selectedUsers.includes(user.id)}
                      onChange={() => toggleUser(user.id)}
                    />
                    {user.name} - {user.phone ?? "بدون موبایل"}
                  </label>
                ))}
              </div>
            </div>
          )}

          {mode === "sms" && (
            <div className="space-y-2">
              <Label>شماره موبایل</Label>
              <Input
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="0912xxxxxxx"
                required
              />
            </div>
          )}

          {mode !== "sms" && (
            <div className="space-y-2">
              <Label>کانال‌های ارسال</Label>
              <div className="flex gap-4">
                {(["push", "email", "sms"] as NotificationChannel[]).map((channel) => (
                  <label key={channel} className="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={channels.includes(channel)}
                      onChange={() => toggleChannel(channel)}
                    />
                    {channel === "push" ? "Push (FCM)" : channel === "email" ? "ایمیل" : "SMS"}
                  </label>
                ))}
              </div>
            </div>
          )}

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label>عنوان</Label>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} required />
            </div>
            <div className="space-y-2">
              <Label>اولویت</Label>
              <Select
                value={priority}
                onChange={(e) => setPriority(e.target.value as NotificationPriority)}
              >
                <option value="normal">عادی</option>
                <option value="high">بالا</option>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label>متن پیام</Label>
            <Textarea value={body} onChange={(e) => setBody(e.target.value)} required />
          </div>

          <div className="space-y-2">
            <Label>لینک (Deep Link - اختیاری)</Label>
            <Input
              value={deepLink}
              onChange={(e) => setDeepLink(e.target.value)}
              placeholder="/orders/123"
              dir="ltr"
            />
          </div>

          <PersianDateTimePicker value={scheduledAt} onChange={setScheduledAt} />

          {message && <p className="text-sm text-emerald-600">{message}</p>}
          {error && <p className="text-sm text-destructive">{error}</p>}

          <Button type="submit" disabled={loading}>
            {loading ? "در حال ارسال..." : "ارسال"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
