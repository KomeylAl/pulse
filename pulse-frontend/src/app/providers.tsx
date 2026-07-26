"use client";

import { AuthProvider } from "@/contexts/auth-context";
import { ProjectProvider } from "@/contexts/project-context";
import { ThemeProvider } from "@/contexts/theme-context";

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <ThemeProvider>
      <AuthProvider>
        <ProjectProvider>{children}</ProjectProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}
