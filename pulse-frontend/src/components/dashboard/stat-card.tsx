import type { LucideIcon } from "lucide-react";

import { cn } from "@/lib/utils";

const tones = {
  primary: {
    icon: "bg-primary/10 text-primary",
    value: "text-foreground",
  },
  success: {
    icon: "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400",
    value: "text-emerald-700 dark:text-emerald-400",
  },
  warning: {
    icon: "bg-amber-500/10 text-amber-600 dark:text-amber-400",
    value: "text-amber-700 dark:text-amber-400",
  },
  danger: {
    icon: "bg-rose-500/10 text-rose-600 dark:text-rose-400",
    value: "text-rose-700 dark:text-rose-400",
  },
  info: {
    icon: "bg-sky-500/10 text-sky-600 dark:text-sky-400",
    value: "text-sky-700 dark:text-sky-400",
  },
} as const;

export function StatCard({
  title,
  value,
  hint,
  icon: Icon,
  tone = "primary",
  className,
}: {
  title: string;
  value: string | number;
  hint?: string;
  icon: LucideIcon;
  tone?: keyof typeof tones;
  className?: string;
}) {
  const colors = tones[tone];

  return (
    <div className={cn("pulse-surface p-5", className)}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm text-muted-foreground">{title}</p>
          <p className={cn("mt-2 text-3xl font-semibold tabular-nums tracking-tight", colors.value)}>
            {typeof value === "number" ? value.toLocaleString("fa-IR") : value}
          </p>
          {hint && (
            <p className="mt-1.5 text-xs text-muted-foreground">{hint}</p>
          )}
        </div>
        <div
          className={cn(
            "flex size-10 shrink-0 items-center justify-center rounded-lg",
            colors.icon,
          )}
        >
          <Icon className="size-4" />
        </div>
      </div>
    </div>
  );
}
