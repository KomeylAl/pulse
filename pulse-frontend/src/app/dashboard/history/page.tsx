"use client";

import { useEffect, useState } from "react";
import { Filter } from "lucide-react";

import { PageHeader } from "@/components/dashboard/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { notificationsApi } from "@/lib/api/notifications";
import type { NotificationLog } from "@/lib/api/types";

function statusVariant(status: string) {
  if (status === "sent") return "success";
  if (status === "failed") return "destructive";
  if (status === "scheduled" || status === "pending") return "warning";
  return "outline";
}

function deliveryLabel(status: string) {
  const labels: Record<string, string> = {
    sent: "قبول‌شده توسط سرویس",
    scheduled: "زمان‌بندی‌شده",
    delayed: "تأخیر در تحویل",
    delivered: "به صندوق رسیده",
    opened: "باز شده",
    clicked: "کلیک شده",
    bounced: "برگشت خورده",
    complained: "اسپم",
    failed: "خطای ارسال",
    suppressed: "مسدود",
  };

  return labels[status] ?? status;
}

const filters = [
  { value: "", label: "همه" },
  { value: "sent", label: "ارسال‌شده" },
  { value: "failed", label: "ناموفق" },
  { value: "pending", label: "در صف" },
  { value: "scheduled", label: "زمان‌بندی" },
];

export default function HistoryPage() {
  const [logs, setLogs] = useState<NotificationLog[]>([]);
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    const params = status ? { status } : undefined;
    void notificationsApi
      .list(params)
      .then((response) => setLogs(response.data))
      .finally(() => setLoading(false));
  }, [status]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="تاریخچه نوتیفیکیشن‌ها"
        description="لاگ ارسال‌ها با فیلتر وضعیت"
      />

      <div className="flex flex-wrap items-center gap-2">
        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
          <Filter className="size-3.5" />
          فیلتر:
        </span>
        {filters.map((item) => (
          <Button
            key={item.value || "all"}
            size="sm"
            variant={status === item.value ? "default" : "outline"}
            onClick={() => setStatus(item.value)}
          >
            {item.label}
          </Button>
        ))}
      </div>

      <section className="pulse-surface p-5">
        {loading && (
          <p className="text-sm text-muted-foreground">در حال بارگذاری...</p>
        )}
        {!loading && logs.length === 0 && (
          <p className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
            موردی یافت نشد.
          </p>
        )}
        <div className="space-y-3">
          {logs.map((log) => (
            <article
              key={log.id}
              className="rounded-2xl border border-border/80 bg-background/70 p-4 transition hover:border-primary/25"
            >
              <div className="mb-2 flex flex-wrap items-center gap-2">
                <span className="font-semibold">{log.title}</span>
                <Badge variant={statusVariant(log.status)}>
                  {log.status_label}
                </Badge>
                <Badge variant="outline">{log.channel_label}</Badge>
                {log.delivery_status && (
                  <Badge variant="outline">{deliveryLabel(log.delivery_status)}</Badge>
                )}
              </div>
              <p className="text-sm text-muted-foreground">{log.body}</p>
              <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                <span>گیرنده: {log.recipient ?? log.user?.name ?? "-"}</span>
                <span>تلاش: {log.attempts.toLocaleString("fa-IR")}</span>
                {log.sent_at && (
                  <span>
                    ارسال: {new Date(log.sent_at).toLocaleString("fa-IR")}
                  </span>
                )}
                {log.error_message && (
                  <span className="text-destructive">{log.error_message}</span>
                )}
              </div>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
