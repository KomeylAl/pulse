"use client";

import Link from "next/link";
import { useState } from "react";
import { FolderPlus, Settings } from "lucide-react";

import { PageHeader } from "@/components/dashboard/page-header";
import { Button } from "@/components/ui/button";
import { Input, Label } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useProject } from "@/contexts/project-context";
import { projectsApi } from "@/lib/api/notifications";
import type { ProjectType } from "@/lib/api/types";

export default function ProjectsPage() {
  const { projects, refreshProjects, setActiveProject, projectKey } =
    useProject();
  const [name, setName] = useState("");
  const [type, setType] = useState<ProjectType>("both");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const handleCreate = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await projectsApi.create({ name, type });
      await refreshProjects();
      setActiveProject(response.project.key);
      setName("");
    } catch (err) {
      setError(err instanceof Error ? err.message : "ایجاد پروژه ناموفق بود");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-4xl space-y-6" dir="rtl">
      <PageHeader
        title="پروژه‌ها"
        description="هر پروژه می‌تواند پوش، ایمیل یا هر دو را داشته باشد. هر کانال تنظیمات جدا دارد."
      />

      <section className="pulse-surface p-5">
        <div className="mb-4 flex items-center gap-2">
          <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <FolderPlus className="size-4" />
          </div>
          <div>
            <h2 className="text-base font-semibold">ایجاد پروژه جدید</h2>
            <p className="text-xs text-muted-foreground">
              نام و نوع پلتفرم را مشخص کنید
            </p>
          </div>
        </div>
        <form
          onSubmit={(e) => void handleCreate(e)}
          className="grid gap-4 md:grid-cols-3"
        >
          <div className="space-y-2 md:col-span-1">
            <Label>نام پروژه</Label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="مثلاً IoT Alarms"
              required
            />
          </div>
          <div className="space-y-2">
            <Label>نوع</Label>
            <select
              className="flex h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
              value={type}
              onChange={(e) => setType(e.target.value as ProjectType)}
            >
              <option value="pwa">PWA / Web</option>
              <option value="android">Android</option>
              <option value="both">PWA + Android</option>
            </select>
          </div>
          <div className="flex items-end">
            <Button type="submit" disabled={loading} className="w-full">
              {loading ? "در حال ایجاد..." : "ایجاد پروژه"}
            </Button>
          </div>
        </form>
        {error && <p className="mt-3 text-sm text-destructive">{error}</p>}
      </section>

      <div className="space-y-3">
        {projects.length === 0 && (
          <p className="rounded-xl border border-dashed border-border bg-card p-10 text-center text-sm text-muted-foreground">
            هنوز پروژه‌ای ندارید. اولین پروژه را بسازید تا بتوانید نوتیفیکیشن ارسال
            کنید.
          </p>
        )}

        {projects.map((project) => (
          <article
            key={project.key}
            className="pulse-surface flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <p className="font-medium">{project.name}</p>
                {projectKey === project.key && <Badge>فعال</Badge>}
                <Badge variant="outline">
                  {project.type_label ?? project.type}
                </Badge>
                {project.has_firebase ? (
                  <Badge variant="success">پوش آماده</Badge>
                ) : (
                  <Badge variant="warning">پوش ناقص</Badge>
                )}
                {project.has_email ? (
                  <Badge variant="success">ایمیل آماده</Badge>
                ) : (
                  <Badge variant="warning">ایمیل ناقص</Badge>
                )}
              </div>
              <p
                className="mt-1.5 font-mono text-xs text-muted-foreground"
                dir="ltr"
              >
                {project.key}
              </p>
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setActiveProject(project.key);
                  window.location.href = "/dashboard";
                }}
              >
                انتخاب
              </Button>
              <Link
                href={`/dashboard/projects/${project.key}/settings`}
                className="inline-flex h-7 items-center gap-1 rounded-lg bg-primary px-2.5 text-[0.8rem] font-medium text-primary-foreground hover:bg-primary/80"
              >
                <Settings className="size-3.5" />
                تنظیمات
              </Link>
            </div>
          </article>
        ))}
      </div>
    </div>
  );
}
