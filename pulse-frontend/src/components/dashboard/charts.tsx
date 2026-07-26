"use client";

import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";

import type { NotificationStats } from "@/lib/api/types";

const CHART_COLORS = [
  "var(--chart-1)",
  "var(--chart-2)",
  "var(--chart-3)",
  "var(--chart-4)",
  "var(--chart-5)",
];

const channelLabels: Record<string, string> = {
  push: "پوش",
  sms: "پیامک",
};

function ChartTooltip({
  active,
  payload,
  label,
}: {
  active?: boolean;
  payload?: { value?: number; name?: string; color?: string }[];
  label?: string;
}) {
  if (!active || !payload?.length) return null;

  return (
    <div className="rounded-xl border border-border bg-card px-3 py-2 text-xs shadow-lg">
      {label && <p className="mb-1 font-medium text-foreground">{label}</p>}
      {payload.map((item) => (
        <p key={item.name} className="text-muted-foreground">
          <span
            className="ml-1 inline-block size-2 rounded-full"
            style={{ background: item.color }}
          />
          {item.name}: {(item.value ?? 0).toLocaleString("fa-IR")}
        </p>
      ))}
    </div>
  );
}

export function StatusBarChart({ stats }: { stats: NotificationStats }) {
  const data = [
    { name: "ارسال‌شده", value: stats.notifications.sent, fill: "var(--chart-2)" },
    {
      name: "در صف",
      value: stats.notifications.pending,
      fill: "var(--chart-3)",
    },
    {
      name: "زمان‌بندی",
      value: stats.notifications.scheduled,
      fill: "var(--chart-1)",
    },
    {
      name: "ناموفق",
      value: stats.notifications.failed,
      fill: "var(--chart-4)",
    },
  ];

  return (
    <div className="h-64 w-full" dir="ltr">
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" />
          <XAxis
            dataKey="name"
            tick={{ fontSize: 12, fill: "var(--muted-foreground)" }}
            axisLine={false}
            tickLine={false}
          />
          <YAxis
            allowDecimals={false}
            tick={{ fontSize: 11, fill: "var(--muted-foreground)" }}
            axisLine={false}
            tickLine={false}
            width={32}
          />
          <Tooltip content={<ChartTooltip />} cursor={{ fill: "color-mix(in oklch, var(--primary) 8%, transparent)" }} />
          <Bar dataKey="value" name="تعداد" radius={[8, 8, 4, 4]}>
            {data.map((entry) => (
              <Cell key={entry.name} fill={entry.fill} />
            ))}
          </Bar>
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

export function ChannelPieChart({ stats }: { stats: NotificationStats }) {
  const data = Object.entries(stats.channels).map(([channel, value]) => ({
    name: channelLabels[channel] ?? channel,
    value,
  }));

  if (data.length === 0 || data.every((item) => item.value === 0)) {
    return (
      <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
        هنوز داده‌ای برای کانال‌ها نیست
      </div>
    );
  }

  return (
    <div className="h-64 w-full" dir="ltr">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            nameKey="name"
            cx="50%"
            cy="50%"
            innerRadius={58}
            outerRadius={88}
            paddingAngle={4}
            strokeWidth={0}
          >
            {data.map((entry, index) => (
              <Cell
                key={entry.name}
                fill={CHART_COLORS[index % CHART_COLORS.length]}
              />
            ))}
          </Pie>
          <Tooltip content={<ChartTooltip />} />
        </PieChart>
      </ResponsiveContainer>
      <div className="mt-2 flex flex-wrap justify-center gap-3" dir="rtl">
        {data.map((entry, index) => (
          <div key={entry.name} className="flex items-center gap-2 text-xs">
            <span
              className="size-2.5 rounded-full"
              style={{ background: CHART_COLORS[index % CHART_COLORS.length] }}
            />
            <span className="text-muted-foreground">
              {entry.name}: {entry.value.toLocaleString("fa-IR")}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

export function CampaignSuccessRing({
  sent,
  failed,
}: {
  sent: number;
  failed: number;
}) {
  const total = sent + failed;
  const rate = total === 0 ? 0 : Math.round((sent / total) * 100);
  const data = [
    { name: "موفق", value: sent || (total === 0 ? 1 : 0) },
    { name: "ناموفق", value: failed },
  ];

  return (
    <div className="relative h-48 w-full" dir="ltr">
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Pie
            data={data}
            dataKey="value"
            cx="50%"
            cy="50%"
            innerRadius={52}
            outerRadius={72}
            startAngle={90}
            endAngle={-270}
            strokeWidth={0}
          >
            <Cell fill="var(--chart-2)" />
            <Cell fill="var(--chart-4)" />
          </Pie>
        </PieChart>
      </ResponsiveContainer>
      <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center" dir="rtl">
        <span className="text-3xl font-semibold text-foreground">
          {rate.toLocaleString("fa-IR")}٪
        </span>
        <span className="text-xs text-muted-foreground">نرخ موفقیت</span>
      </div>
    </div>
  );
}
