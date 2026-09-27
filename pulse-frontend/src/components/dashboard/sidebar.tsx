"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Activity,
  FolderKanban,
  History,
  LayoutDashboard,
  LogOut,
  Megaphone,
  Moon,
  Send,
  Smartphone,
  Sun,
} from "lucide-react";

import { Button } from "@/components/ui/button";
import { useAuth } from "@/contexts/auth-context";
import { useProject } from "@/contexts/project-context";
import { useTheme } from "@/contexts/theme-context";
import { cn } from "@/lib/utils";

const links = [
  { href: "/dashboard", label: "داشبورد", icon: LayoutDashboard },
  { href: "/dashboard/projects", label: "پروژه‌ها", icon: FolderKanban },
  { href: "/dashboard/devices", label: "دستگاه‌ها", icon: Smartphone },
  { href: "/dashboard/send", label: "ارسال نوتیفیکیشن", icon: Send },
  { href: "/dashboard/history", label: "تاریخچه", icon: History },
  { href: "/dashboard/campaigns", label: "کمپین‌ها", icon: Megaphone },
];

export function DashboardSidebar() {
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const { projects, projectKey, setActiveProject } = useProject();
  const { theme, toggleTheme } = useTheme();

  return (
    <aside className="flex h-full w-64 shrink-0 flex-col border-e border-sidebar-border bg-sidebar text-sidebar-foreground">
      <div className="flex items-center justify-between gap-2 border-b border-sidebar-border px-4 py-4">
        <div className="flex min-w-0 items-center gap-2.5">
          <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
            <Activity className="size-4" />
          </div>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold">Pulse Notify</p>
            <p className="truncate text-[11px] text-muted-foreground">
              مرکز ارسال پیام
            </p>
          </div>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          className="shrink-0"
          onClick={toggleTheme}
          aria-label={theme === "dark" ? "تم روشن" : "تم تاریک"}
          title={theme === "dark" ? "تم روشن" : "تم تاریک"}
        >
          {theme === "dark" ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </Button>
      </div>

      {projects.length > 0 && (
        <div className="border-b border-sidebar-border px-4 py-3">
          <label className="mb-1.5 block text-xs text-muted-foreground">
            پروژه فعال
          </label>
          <select
            className="w-full rounded-lg border border-sidebar-border bg-background px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-ring/40"
            value={projectKey ?? ""}
            onChange={(event) => {
              setActiveProject(event.target.value);
              window.location.reload();
            }}
          >
            {projects.map((project) => (
              <option key={project.key} value={project.key}>
                {project.name}
                {project.has_firebase || project.has_email ? "" : " ⚠"}
              </option>
            ))}
          </select>
        </div>
      )}

      <nav className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto p-2.5">
        {links.map((link) => {
          const Icon = link.icon;
          const active =
            pathname === link.href ||
            (link.href !== "/dashboard" && pathname.startsWith(link.href));

          return (
            <Link
              key={link.href}
              href={link.href}
              className={cn(
                "flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors",
                active
                  ? "bg-primary text-primary-foreground"
                  : "text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
              )}
            >
              <Icon className="size-4 shrink-0" />
              {link.label}
            </Link>
          );
        })}
      </nav>

      <div className="mt-auto border-t border-sidebar-border p-3">
        <div className="mb-2.5 rounded-lg bg-muted/60 px-2.5 py-2">
          <p className="truncate text-sm font-medium">{user?.name}</p>
          <p className="truncate text-xs text-muted-foreground" dir="ltr">
            {user?.email}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          className="w-full"
          onClick={() => void logout()}
        >
          <LogOut className="size-4" />
          خروج
        </Button>
      </div>
    </aside>
  );
}
