import { ProjectPushTestForm } from "@/components/notifications/project-push-test-form";
import { PageHeader } from "@/components/dashboard/page-header";

export default function SendPage() {
  return (
    <div className="mx-auto max-w-3xl space-y-6" dir="rtl">
      <PageHeader
        title="ارسال نوتیفیکیشن"
        description="ارسال لحظه‌ای یا زمان‌بندی‌شده به کاربران و دستگاه‌های پروژه فعال"
      />
      <ProjectPushTestForm />
    </div>
  );
}
