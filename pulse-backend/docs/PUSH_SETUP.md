# Pulse Push Setup (DB-Driven Projects)

Projects live in the database. Users register in the dashboard, create projects, and paste Firebase credentials in project settings — no per-project `.env` vars.

## Operator setup (you)

```bash
cp .env.example .env
php artisan key:generate   # required — credentials are encrypted at rest
php artisan migrate --seed
php artisan queue:work --queue=notifications
```

Default admin (seeder): `admin@pulse.local` / `password` with a `demo` project.

## End-user flow

1. Register / login in the Pulse dashboard
2. Create a project (PWA, Android, or both) — e.g. “Suicide Prevention” or “IoT Alarms”
3. Open **Project settings → Push Notification**
4. Follow the in-app Firebase Console guide:
   - Create Firebase project
   - Download service account JSON → paste into Pulse
   - (PWA) Copy web config + VAPID key
5. Copy the generated sample code / API key into your app
6. Register device tokens with `X-Pulse-Api-Key`
7. Send pushes via API or dashboard

## Client API (from your apps)

```bash
# Register token
curl -X POST http://localhost:8000/api/v1/push/device-tokens \
  -H "Content-Type: application/json" \
  -H "X-Pulse-Api-Key: pk_..." \
  -d '{"token":"FCM_TOKEN","platform":"pwa","external_user_id":"user-42"}'

# Send
curl -X POST http://localhost:8000/api/v1/push/send \
  -H "Content-Type: application/json" \
  -H "X-Pulse-Api-Key: pk_..." \
  -d '{"title":"Hello","body":"Push","external_user_id":"user-42"}'
```

Dashboard requests use Sanctum Bearer token + `X-Pulse-Project: your-project-key`.

## Security notes

- Service account JSON and VAPID keys are stored with Laravel `encrypted` casts
- API keys are unique per project and can be regenerated from settings
- Users only see/manage their own projects
