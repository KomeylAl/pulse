/**
 * PWA Push notification helper using Firebase Cloud Messaging.
 * Call `registerPwaPush()` after user login when Firebase env vars are set.
 */

import { notificationsApi } from "@/lib/api/notifications";

function getFirebaseConfig() {
  return {
    apiKey: process.env.NEXT_PUBLIC_FIREBASE_API_KEY,
    authDomain: process.env.NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN,
    projectId: process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID,
    storageBucket: process.env.NEXT_PUBLIC_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: process.env.NEXT_PUBLIC_FIREBASE_MESSAGING_SENDER_ID,
    appId: process.env.NEXT_PUBLIC_FIREBASE_APP_ID,
  };
}

export function isFirebaseConfigured(): boolean {
  const config = getFirebaseConfig();
  return Boolean(
    config.apiKey &&
      config.projectId &&
      config.appId &&
      process.env.NEXT_PUBLIC_FIREBASE_VAPID_KEY,
  );
}

export async function registerPwaPush(): Promise<string | null> {
  if (typeof window === "undefined" || !("Notification" in window)) {
    return null;
  }

  if (!isFirebaseConfigured()) {
    return null;
  }

  const permission = await Notification.requestPermission();
  if (permission !== "granted") {
    return null;
  }

  const { initializeApp, getApps } = await import("firebase/app");
  const { getMessaging, getToken, isSupported } = await import(
    "firebase/messaging"
  );

  if (!(await isSupported())) {
    return null;
  }

  const firebaseConfig = getFirebaseConfig();
  const app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig);
  const messaging = getMessaging(app);

  const registration = await navigator.serviceWorker.register(
    "/firebase-messaging-sw.js",
  );
  const token = await getToken(messaging, {
    vapidKey: process.env.NEXT_PUBLIC_FIREBASE_VAPID_KEY,
    serviceWorkerRegistration: registration,
  });

  if (token) {
    await notificationsApi.registerDeviceToken({
      token,
      platform: "pwa",
      device_name: navigator.userAgent,
    });
  }

  return token;
}
