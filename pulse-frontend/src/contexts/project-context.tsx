"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";

import { useAuth } from "@/contexts/auth-context";
import { getProjectKey, setProjectKey } from "@/lib/api/client";
import { projectsApi } from "@/lib/api/notifications";
import type { PulseProject } from "@/lib/api/types";

interface ProjectContextValue {
  projects: PulseProject[];
  projectKey: string | null;
  loading: boolean;
  refreshProjects: () => Promise<void>;
  setActiveProject: (key: string) => void;
}

const ProjectContext = createContext<ProjectContextValue | null>(null);

export function ProjectProvider({ children }: { children: React.ReactNode }) {
  const { user, loading: authLoading } = useAuth();
  const [projects, setProjects] = useState<PulseProject[]>([]);
  const [projectKey, setProjectKeyState] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const refreshProjects = useCallback(async () => {
    const list = await projectsApi.list();
    setProjects(list);
    const stored = getProjectKey();
    const next =
      (stored && list.some((p) => p.key === stored) ? stored : null) ??
      list[0]?.key ??
      null;
    setProjectKey(next);
    setProjectKeyState(next);
  }, []);

  useEffect(() => {
    if (authLoading) return;

    if (!user) {
      setProjects([]);
      setProjectKeyState(null);
      setLoading(false);
      return;
    }

    let cancelled = false;
    setLoading(true);

    void refreshProjects()
      .catch(() => {
        if (!cancelled) {
          setProjects([]);
          setProjectKeyState(null);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [user, authLoading, refreshProjects]);

  const setActiveProject = useCallback((key: string) => {
    setProjectKey(key);
    setProjectKeyState(key);
  }, []);

  const value = useMemo(
    () => ({
      projects,
      projectKey,
      loading,
      refreshProjects,
      setActiveProject,
    }),
    [projects, projectKey, loading, refreshProjects, setActiveProject],
  );

  return (
    <ProjectContext.Provider value={value}>{children}</ProjectContext.Provider>
  );
}

export function useProject() {
  const context = useContext(ProjectContext);
  if (!context) {
    throw new Error("useProject must be used within ProjectProvider");
  }
  return context;
}
