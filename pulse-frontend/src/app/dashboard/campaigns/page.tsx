"use client";

import { useEffect, useMemo, useState } from "react";
import { ImageIcon } from "lucide-react";

import { PageHeader } from "@/components/dashboard/page-header";
import {
  PersianDatePicker,
  PersianDateTimePicker,
  PersianMultiDatePicker,
  PersianTimeInput,
  formatGregorianDate,
  formatPersianDateLabel,
} from "@/components/notifications/persian-date-picker";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input, Label, Textarea } from "@/components/ui/input";
import { useProject } from "@/contexts/project-context";
import { notificationsApi, projectsApi } from "@/lib/api/notifications";
import type {
  CampaignScheduleType,
  NotificationCampaign,
  ProjectSubscriber,
} from "@/lib/api/types";
import { formatTehranDateTime, toOffsetIsoString } from "@/lib/datetime";
import { cn } from "@/lib/utils";

const WEEKDAYS = [
  { value: 6, label: "شنبه" },
  { value: 7, label: "یکشنبه" },
  { value: 1, label: "دوشنبه" },
  { value: 2, label: "سه‌شنبه" },
  { value: 3, label: "چهارشنبه" },
  { value: 4, label: "پنجشنبه" },
  { value: 5, label: "جمعه" },
] as const;

const SCHEDULE_MODES: {
  value: CampaignScheduleType;
  title: string;
  desc: string;
}[] = [
  {
    value: "once",
    title: "یک‌بار",
    desc: "یک تاریخ و ساعت مشخص",
  },
  {
    value: "daily",
    title: "روزانه",
    desc: "هر روز در بازه زمانی",
  },
  {
    value: "dates",
    title: "روزهای خاص",
    desc: "چند تاریخ از تقویم",
  },
  {
    value: "weekly",
    title: "هفتگی پیشرفته",
    desc: "روزهای هفته در یک بازه",
  },
];

const recurrenceLabels: Record<string, string> = {
  once: "یک‌بار",
  daily: "روزانه",
  dates: "روزهای خاص",
  weekly: "هفتگی",
};

function weekdayLabel(day: number) {
  return WEEKDAYS.find((item) => item.value === day)?.label ?? String(day);
}

export default function CampaignsPage() {
  const { projectKey, projects } = useProject();
  const project = projects.find((item) => item.key === projectKey);

  const [campaigns, setCampaigns] = useState<NotificationCampaign[]>([]);
  const [subscribers, setSubscribers] = useState<ProjectSubscriber[]>([]);
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [imageUrl, setImageUrl] = useState("");
  const [scheduleType, setScheduleType] = useState<CampaignScheduleType>("daily");
  const [times, setTimes] = useState<string[]>(["09:00"]);
  const [newTime, setNewTime] = useState("12:00");
  const [onceAt, setOnceAt] = useState<Date | null>(null);
  const [startsOn, setStartsOn] = useState<Date | null>(new Date());
  const [endsOn, setEndsOn] = useState<Date | null>(() => {
    const date = new Date();
    date.setDate(date.getDate() + 7);
    return date;
  });
  const [selectedDates, setSelectedDates] = useState<Date[]>([]);
  const [weekdays, setWeekdays] = useState<number[]>([1, 4]);
  const [target, setTarget] = useState<"all" | "users">("all");
  const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = () => {
    void notificationsApi
      .campaigns({ real_only: "1" })
      .then((response) => setCampaigns(response.data));
    if (projectKey) {
      void projectsApi.devices(projectKey).then((response) => {
        setSubscribers(response.subscribers);
      });
    }
  };

  useEffect(() => {
    load();
  }, [projectKey]);

  const addTime = () => {
    if (!/^\d{2}:\d{2}$/.test(newTime)) return;
    if (times.includes(newTime)) return;
    setTimes((current) => [...current, newTime].sort());
  };

  const removeTime = (time: string) => {
    setTimes((current) => current.filter((item) => item !== time));
  };

  const toggleWeekday = (day: number) => {
    setWeekdays((current) =>
      current.includes(day)
        ? current.filter((item) => item !== day)
        : [...current, day].sort((a, b) => a - b),
    );
  };

  const canSubmit = useMemo(() => {
    if (!title.trim() || !body.trim()) return false;
    if (scheduleType === "once") return onceAt !== null;
    if (times.length === 0) return false;
    if (scheduleType === "daily" || scheduleType === "weekly") {
      if (!startsOn || !endsOn) return false;
    }
    if (scheduleType === "dates" && selectedDates.length === 0) return false;
    if (scheduleType === "weekly" && weekdays.length === 0) return false;
    if (target === "users" && selectedUsers.length === 0) return false;
    return true;
  }, [
    title,
    body,
    scheduleType,
    onceAt,
    times,
    startsOn,
    endsOn,
    selectedDates,
    weekdays,
    target,
    selectedUsers,
  ]);

  const createCampaign = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canSubmit) return;

    setLoading(true);
    setMessage(null);
    setError(null);

    try {
      await notificationsApi.createCampaign({
        title,
        body,
        schedule_type: scheduleType,
        target,
        external_user_ids: target === "users" ? selectedUsers : undefined,
        image_url: imageUrl.trim() || undefined,
        schedule_times: scheduleType === "once" ? undefined : times,
        scheduled_at: scheduleType === "once" ? toOffsetIsoString(onceAt!) : undefined,
        starts_on:
          scheduleType === "daily" || scheduleType === "weekly"
            ? formatGregorianDate(startsOn!)
            : undefined,
        ends_on:
          scheduleType === "daily" || scheduleType === "weekly"
            ? formatGregorianDate(endsOn!)
            : undefined,
        dates:
          scheduleType === "dates"
            ? selectedDates.map(formatGregorianDate)
            : undefined,
        weekdays: scheduleType === "weekly" ? weekdays : undefined,
      });

      setMessage("کمپین با موفقیت ساخته شد.");
      setTitle("");
      setBody("");
      setImageUrl("");
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : "ایجاد کمپین ناموفق بود");
    } finally {
      setLoading(false);
    }
  };

  const cancel = async (id: number) => {
    await notificationsApi.cancelCampaign(id);
    load();
  };

  return (
    <div className="mx-auto max-w-4xl space-y-6" dir="rtl">
      <PageHeader
        title="کمپین‌ها"
        description={`پروژه فعال: ${project?.name ?? projectKey ?? "—"} — زمان‌بندی فارسی با حالت‌های مختلف`}
      />

      <section className="pulse-surface p-5">
        <div className="mb-5">
          <h2 className="text-base font-semibold">ایجاد کمپین</h2>
          <p className="text-xs text-muted-foreground">
            نوع زمان‌بندی را انتخاب کنید؛ تاریخ‌ها شمسی نمایش داده می‌شوند.
          </p>
        </div>

        <form onSubmit={(e) => void createCampaign(e)} className="space-y-5">
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {SCHEDULE_MODES.map((mode) => (
              <button
                key={mode.value}
                type="button"
                onClick={() => setScheduleType(mode.value)}
                className={cn(
                  "rounded-xl border p-3 text-right transition-colors",
                  scheduleType === mode.value
                    ? "border-primary bg-primary/5"
                    : "border-border hover:bg-muted/50",
                )}
              >
                <p className="text-sm font-medium">{mode.title}</p>
                <p className="mt-1 text-xs text-muted-foreground">{mode.desc}</p>
              </button>
            ))}
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label>عنوان</Label>
              <Input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>مخاطب</Label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  size="sm"
                  variant={target === "all" ? "default" : "outline"}
                  onClick={() => setTarget("all")}
                >
                  همه دستگاه‌ها
                </Button>
                <Button
                  type="button"
                  size="sm"
                  variant={target === "users" ? "default" : "outline"}
                  onClick={() => setTarget("users")}
                >
                  کاربران انتخابی
                </Button>
              </div>
            </div>
          </div>

          <div className="space-y-2">
            <Label>متن پیام</Label>
            <Textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              required
            />
          </div>

          <div className="space-y-2">
            <Label className="inline-flex items-center gap-1.5">
              <ImageIcon className="size-3.5" />
              آدرس تصویر آیکون نوتیف (اختیاری)
            </Label>
            <Input
              value={imageUrl}
              onChange={(e) => setImageUrl(e.target.value)}
              placeholder="https://example.com/icon.png"
              dir="ltr"
            />
            {imageUrl.trim() && (
              <div className="flex items-center gap-3 rounded-lg border border-border bg-muted/30 p-2">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                  src={imageUrl}
                  alt="پیش‌نمایش آیکون"
                  className="size-10 rounded-lg border border-border object-cover"
                  onError={(event) => {
                    event.currentTarget.style.opacity = "0.3";
                  }}
                />
                <p className="text-xs text-muted-foreground">
                  این تصویر به‌عنوان آیکون نوتیفیکیشن ارسال می‌شود.
                </p>
              </div>
            )}
          </div>

          {scheduleType === "once" && (
            <PersianDateTimePicker
              value={onceAt}
              onChange={setOnceAt}
              label="زمان ارسال"
              hint="زمان بر اساس تقویم شمسی و منطقه زمانی تهران ذخیره می‌شود."
            />
          )}

          {(scheduleType === "daily" || scheduleType === "weekly") && (
            <div className="grid gap-4 md:grid-cols-2">
              <PersianDatePicker
                value={startsOn}
                onChange={setStartsOn}
                label="از تاریخ"
              />
              <PersianDatePicker
                value={endsOn}
                onChange={setEndsOn}
                label="تا تاریخ"
              />
            </div>
          )}

          {scheduleType === "dates" && (
            <PersianMultiDatePicker
              values={selectedDates}
              onChange={setSelectedDates}
              label="روزهای ارسال"
            />
          )}

          {scheduleType === "weekly" && (
            <div className="space-y-2">
              <Label>روزهای هفته</Label>
              <div className="flex flex-wrap gap-2">
                {WEEKDAYS.map((day) => (
                  <Button
                    key={day.value}
                    type="button"
                    size="sm"
                    variant={weekdays.includes(day.value) ? "default" : "outline"}
                    onClick={() => toggleWeekday(day.value)}
                  >
                    {day.label}
                  </Button>
                ))}
              </div>
            </div>
          )}

          {scheduleType !== "once" && (
            <div className="space-y-2">
              <Label>ساعات ارسال</Label>
              <div className="flex flex-wrap gap-2">
                {times.map((time) => (
                  <button
                    key={time}
                    type="button"
                    className="rounded-lg border border-border bg-muted/50 px-2.5 py-1 font-mono text-sm transition hover:bg-muted"
                    dir="ltr"
                    onClick={() => removeTime(time)}
                    title="حذف"
                  >
                    {time} ×
                  </button>
                ))}
              </div>
              <div className="flex flex-wrap items-end gap-2">
                <PersianTimeInput
                  value={newTime}
                  onChange={setNewTime}
                  label="ساعت جدید"
                  className="w-40"
                />
                <Button type="button" variant="outline" onClick={addTime}>
                  افزودن ساعت
                </Button>
              </div>
            </div>
          )}

          {target === "users" && (
            <div className="max-h-40 space-y-2 overflow-y-auto rounded-xl border border-border bg-muted/30 p-3">
              {subscribers.length === 0 && (
                <p className="text-sm text-muted-foreground">
                  مشترکی با شناسه خارجی ثبت نشده است.
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
                    onChange={() =>
                      setSelectedUsers((current) =>
                        current.includes(subscriber.external_user_id)
                          ? current.filter(
                              (id) => id !== subscriber.external_user_id,
                            )
                          : [...current, subscriber.external_user_id],
                      )
                    }
                  />
                  <span dir="ltr">{subscriber.external_user_id}</span>
                </label>
              ))}
            </div>
          )}

          {message && (
            <p className="text-sm text-emerald-600 dark:text-emerald-400">
              {message}
            </p>
          )}
          {error && <p className="text-sm text-destructive">{error}</p>}

          <Button type="submit" disabled={loading || !canSubmit}>
            {loading ? "در حال ایجاد..." : "ایجاد کمپین"}
          </Button>
          <p className="text-xs text-muted-foreground">
            زمان‌بندی روی سرور با منطقه زمانی{" "}
            <code dir="ltr">Asia/Tehran</code> اجرا می‌شود. برای پردازش باید{" "}
            <code dir="ltr">schedule:work</code> و{" "}
            <code dir="ltr">queue:work --queue=notifications</code> روشن باشند.
          </p>
        </form>
      </section>

      <section className="pulse-surface p-5">
        <h2 className="mb-4 text-base font-semibold">لیست کمپین‌ها</h2>
        <div className="space-y-3">
          {campaigns.length === 0 && (
            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
              کمپین واقعی‌ای ثبت نشده است.
            </p>
          )}
          {campaigns.map((campaign) => {
            const total = campaign.sent_count + campaign.failed_count;
            const rate =
              total === 0
                ? null
                : Math.round((campaign.sent_count / total) * 100);
            const config = campaign.schedule_config;

            return (
              <article
                key={campaign.id}
                className="rounded-xl border border-border bg-muted/20 p-4"
              >
                <div className="mb-2 flex flex-wrap items-center gap-2">
                  {campaign.image_url && (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={campaign.image_url}
                      alt=""
                      className="size-8 rounded-md border border-border object-cover"
                    />
                  )}
                  <span className="font-medium">{campaign.title}</span>
                  <Badge variant="outline">
                    {recurrenceLabels[campaign.recurrence ?? ""] ??
                      campaign.recurrence ??
                      "کمپین"}
                  </Badge>
                  <Badge variant="outline">{campaign.target_type_label}</Badge>
                  <Badge>{campaign.status_label}</Badge>
                </div>
                <p className="text-sm text-muted-foreground">{campaign.body}</p>

                <div className="mt-2 space-y-1 text-xs text-muted-foreground">
                  {campaign.scheduled_at && (
                    <p>
                      ارسال یک‌باره: {formatTehranDateTime(campaign.scheduled_at)}
                    </p>
                  )}
                  {campaign.schedule_times &&
                    campaign.schedule_times.length > 0 && (
                      <p className="font-mono" dir="ltr">
                        ساعات: {campaign.schedule_times.join(" · ")}
                      </p>
                    )}
                  {config?.starts_on && config?.ends_on && (
                    <p>
                      بازه: {formatPersianDateLabel(config.starts_on)} تا{" "}
                      {formatPersianDateLabel(config.ends_on)}
                    </p>
                  )}
                  {config?.dates && config.dates.length > 0 && (
                    <p>
                      تاریخ‌ها:{" "}
                      {config.dates.map((date) => formatPersianDateLabel(date)).join("، ")}
                    </p>
                  )}
                  {config?.weekdays && config.weekdays.length > 0 && (
                    <p>
                      روزهای هفته:{" "}
                      {config.weekdays.map(weekdayLabel).join("، ")}
                    </p>
                  )}
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-3">
                  <div className="h-1.5 w-28 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-primary"
                      style={{ width: `${rate ?? 0}%` }}
                    />
                  </div>
                  <span className="text-xs text-muted-foreground">
                    گیرندگان:{" "}
                    {campaign.total_recipients.toLocaleString("fa-IR")} · موفق:{" "}
                    {campaign.sent_count.toLocaleString("fa-IR")} · ناموفق:{" "}
                    {campaign.failed_count.toLocaleString("fa-IR")}
                    {rate !== null && ` · ${rate.toLocaleString("fa-IR")}٪`}
                  </span>
                </div>

                {["pending", "scheduled", "active", "processing"].includes(
                  campaign.status,
                ) && (
                  <Button
                    variant="destructive"
                    size="sm"
                    className="mt-3"
                    onClick={() => void cancel(campaign.id)}
                  >
                    لغو کمپین
                  </Button>
                )}
              </article>
            );
          })}
        </div>
      </section>
    </div>
  );
}
