"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { useAuth } from "@/contexts/auth-context";
import { useProject } from "@/contexts/project-context";

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const { loading: projectLoading, projectKey, projects } = useProject();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) {
      router.replace("/login");
    }
  }, [loading, user, router]);

  useEffect(() => {
    if (!loading && user && !projectLoading && projects.length === 0) {
      router.replace("/dashboard/projects");
    }
  }, [loading, user, projectLoading, projects.length, router]);

  if (loading || (user && projectLoading)) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        در حال بارگذاری...
      </div>
    );
  }

  if (!user) return null;

  // Allow projects page without an active project.
  if (!projectKey && projects.length > 0) {
    return (
      <div className="flex min-h-screen items-center justify-center text-sm text-muted-foreground">
        در حال آماده‌سازی پروژه...
      </div>
    );
  }

  return children;
}
