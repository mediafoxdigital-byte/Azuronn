# Supabase Setup

## What this implementation stores in Supabase
- Full site content state via `app_state.key = site_content`
- Admin login lockout state via `app_state.key = admin_login_attempts`
- Persistent carts via `cart_sessions`
- Media metadata via `media_assets`

Actual uploaded files stay on hosting disk under the configured uploads path. Supabase stores only URLs and metadata for those files.

## Required server configuration
- `SUPABASE_URL`
- `SUPABASE_PUBLISHABLE_KEY`
- `SUPABASE_SERVICE_ROLE_KEY`

Optional:
- `AZURONN_UPLOADS_ROOT`
- `AZURONN_UPLOADS_PUBLIC_BASE_URL`

You can provide these either:
- as real server environment variables, or
- in `data/runtime-config.php`

Use [data/runtime-config.example.php](/home/hamid/Downloads/azuronn_2/data/runtime-config.example.php) as the template and create `data/runtime-config.php` on the server. That file is under the protected `data/` directory and is easier on shared hosting when env vars are inconvenient.

The code can fall back to the publishable key for requests, but for a real production cutover of private data you should set `SUPABASE_SERVICE_ROLE_KEY`. Without it, private table reads/writes are expected to fail unless you open permissive policies in Supabase.

## Initial setup
1. Open Supabase SQL Editor.
2. Run `supabase/schema.sql`.
3. Add the required server configuration on hosting.
   Preferred shared-hosting method: create `data/runtime-config.php`.
4. Run the health check:

```bash
php scripts/supabase-health-check.php
```

5. Run the seed script:

```bash
php scripts/supabase-sync.php
```

6. Log in to the admin panel and save a small change to verify write access.

## Storage model
- Images and videos upload to hosting storage.
- Database rows store:
  - public URL
  - local file path
  - mime type
  - file size
  - media type

This avoids filling the free Supabase plan with large media files.

## Notes
- This implementation keeps the current PHP auth/session model.
- If Supabase is unavailable, the code falls back to the local JSON/file store so the site does not hard-fail during rollout.
- For a stricter final cutover, remove the fallback after Supabase credentials and policies are fully verified in production.
- A passing health check requires `SUPABASE_SERVICE_ROLE_KEY`. With only the publishable key, the app can report readiness but cannot guarantee secure private writes.
