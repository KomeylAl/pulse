import { AuthGuard } from "@/components/auth/auth-guard";
import { DashboardSidebar } from "@/components/dashboard/sidebar";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <AuthGuard>
      <div className="flex h-svh overflow-hidden bg-background">
        <DashboardSidebar />
        <main className="min-h-0 flex-1 overflow-y-auto">
          <div className="mx-auto max-w-7xl p-5 md:p-8">{children}</div>
        </main>
      </div>
    </AuthGuard>
  );
}
