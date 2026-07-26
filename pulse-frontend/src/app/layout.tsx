import type { Metadata } from "next";
import { Vazirmatn } from "next/font/google";
import { Providers } from "./providers";
import "./globals.css";

const vazirmatn = Vazirmatn({
  subsets: ["arabic"],
  variable: "--font-sans",
});

export const metadata: Metadata = {
  title: "Pulse Notify Dashboard",
  description: "سیستم مدیریت نوتیفیکیشن Pulse",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fa" dir="rtl" className={`${vazirmatn.variable} h-full antialiased`} suppressHydrationWarning>
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: `(function(){try{var t=localStorage.getItem('pulse-theme');if(t!=='light'&&t!=='dark'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}var r=document.documentElement;r.classList.toggle('dark',t==='dark');r.style.colorScheme=t}catch(e){}})();`,
          }}
        />
      </head>
      <body className="flex min-h-full flex-col font-sans">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
