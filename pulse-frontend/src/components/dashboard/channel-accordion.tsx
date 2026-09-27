"use client";

import { ChevronDown } from "lucide-react";
import { useState } from "react";

import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

export function ChannelAccordion({
  title,
  description,
  ready,
  readyLabel,
  pendingLabel,
  defaultOpen = false,
  children,
}: {
  title: string;
  description: string;
  ready: boolean;
  readyLabel: string;
  pendingLabel: string;
  defaultOpen?: boolean;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(defaultOpen);

  return (
    <section className="pulse-surface overflow-hidden">
      <button
        type="button"
        className="flex w-full items-center justify-between gap-3 p-4 text-right"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-base font-semibold">{title}</h2>
            <Badge variant={ready ? "success" : "warning"}>
              {ready ? readyLabel : pendingLabel}
            </Badge>
          </div>
          <p className="mt-1 text-xs text-muted-foreground">{description}</p>
        </div>
        <ChevronDown
          className={cn(
            "size-4 shrink-0 text-muted-foreground transition-transform",
            open && "rotate-180",
          )}
        />
      </button>
      {open && <div className="space-y-4 border-t border-border p-4">{children}</div>}
    </section>
  );
}
