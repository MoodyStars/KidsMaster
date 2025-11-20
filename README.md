# KidsMaster

KidsMaster is a nostalgic, extensible media-sharing platform inspired by classic portals (Wenoo, VidLii, ZippCast, KidsTube) with a modern "2025 reveal" option. This repository contains a full PHP + MySQL scaffold implementing a Channels 1.5 Deluxe feature set: multi-type media (video/audio/images/software/games/storage), channels with PFP/GIF banner/background/theme choices, SMS-style channel chat with emoji & country flags (WebSocket + fallback), Reddit integration stubs, live stream management (RTMP/HLS worker integration), threaded comments, uploads with resumable chunking, background encoding workers (FFmpeg), and admin tooling including an encoding jobs dashboard.

This README documents what the project includes, how to get it running locally, the main endpoints and tools, how to run the worker, and recommendations for production hardening.

Table of contents
- Features
- Repo layout
- Requirements
- Quick start (development)
- Database migrations & schema
- Uploads and media processing (workers)
- WebSocket chat & Redis pub/sub
- Admin & moderation
- API endpoints (quick reference)
- CI & testing
- Deployment notes (systemd / supervisor)
- Security & production hardening checklist
- Contributing
- License

Features
- Channels 1.5 Deluxe: per-channel profile picture (PFP), GIF banner support, background images, theme choice (deluxe/retro/modern), channel versioning and owner controls.
- SMS-style channel chat: emoji, country-flag metadata, avatar support. Real-time via Ratchet WebSocket server with Redis pub/sub; HTTP fallback persists to DB.
- Upload system: client-side resumable chunking, server chunk assembly, file-type detection, thumbnail generation (GD/Imagick), quota checks (example 512GB).
- Media processing pipeline: encoding_jobs table + worker (PHP CLI) that runs FFmpeg to generate thumbnails, HLS manifests, trims, and remixes.
- Admin jobs dashboard: view, retry, requeue, cancel and delete encoding jobs.
- Threaded comments, reports, moderation endpoints, and an admin moderation dashboard scaffold.
- Community features: categories, groups, group membership, contact form, curated/special videos pages, people directory.
- Retro 2011 theme with a 2025 reveal toggle (assets/css/retro2011.css + JS).
- Legacy (IE7–11) compatibility layer (compat.js) and fallback assets (legacy.css) for broad support.
- CI checks & tests: FFmpeg availability script and worker smoke test; GitHub Actions workflow example.
- Useful tools: setup wizard, test master diagnostic page, batch upload UI, editor/remix stubs, and many AJAX endpoints.

Repo layout (high-level)
- _includes/ — bootstrap, header/footer helpers, core init
- assets/
  - css/ — styles (style.css, retro2011, legacy, channel_1_5_deluxe)
  - js/ — client-side scripts (compat, retro, channel actions, upload widget, legacy fallbacks)
- ajax/ — AJAX endpoints (media_api.php, editor_api.php, remix_api.php, admin APIs...)
- admin/ — admin UI (jobs dashboard)
- workers/ — worker CLI script processing encoding_jobs
- websockets/ — Ratchet WebSocket server scaffold
- db/ — schema and migrations
- storage/ — generated/served assets (uploads, hls, thumbs)
- pages: index.php, channels.php, watch.php, videos.php, audio.php, images.php, software.php, games.php, storage.php, archive.php, community.php, etc.
- docs/ — API reference + Postman collection
- tests/ — CI/test scripts (ffmpeg check, worker smoke)
- README.md — (this file)

Requirements
- PHP 8.x (recommended)
- MySQL / MariaDB (8+ recommended)
- Composer (to install Ratchet and other libraries)
- FFmpeg installed on PATH (worker jobs)
- Optional: Redis + phpredis extension (for job pub/sub and cache)
- Webserver: Nginx/Apache or PHP built-in server for development

Quick start (development)
1. Clone the project:
   git clone <repo> kidsmaster
   cd kidsmaster

2. Install composer dependencies (for WebSocket server):
   composer install

3. Configure environment variables (or edit `_includes/init.php` DB credentials):
   export KM_DB_HOST=127.0.0.1
   export KM_DB_NAME=kidsmaster
   export KM_DB_USER=km_user
   export KM_DB_PASS=km_pass

4. Create DB and import schema:
   - Create database and a user.
   - Import base schema and migrations:
     mysql -u root -p kidsmaster < db/schema.sql
     mysql -u root -p kidsmaster < db/migrations/20251120_jobs_and_processing.sql
     (also import other migration files in db/migrations as needed)

5. Start the PHP built-in server for quick dev:
   php -S 127.0.0.1:8000

6. Visit http://127.0.0.1:8000/setup.php and create an admin user with the setup wizard. This will create the default channel and seed categories.

Database migrations & schema
- Main schema file: db/schema.sql (base tables users, channels, media, comments, chat_messages, storage_files, playlists, etc.)
- Jobs and processing migration: db/migrations/20251120_jobs_and_processing.sql (encoding_jobs table, hls_url/duration fields)
- Channel & chat metadata migrations: db/migrations/20251119_add_channel_chat_and_archive.sql and db/migrations/20251120_channel_chat_pubsub.sql provide channels.archived, chat_messages.channel_id, country_code, user_avatar.
- Run migrations in sequence. Back up your DB before applying changes.

Uploads and media processing
- Chunked upload endpoint: upload.php — accepts chunked uploads, assembles file, basic MIME validation and thumbnail generation.
- Finalize endpoint: ajax/upload_finalize.php — creates media DB record referencing uploaded file.
- Background worker: workers/worker.php — consumes Redis queue `kidsmaster:jobs` (BRPOP) or polls encoding_jobs table and runs FFmpeg for:
  - HLS packaging (hls job)
  - Thumbnail generation (thumbnail job)
  - Trim operation (trim job)
  - Remix (remix job)
- Use `enqueue_hls` and `enqueue_thumbnail` via ajax/media_api.php to queue work.
- Outputs are stored under `/storage/` (hls, thumbs, trims, remix) and media rows are updated with `hls_url`, `thumbnail`, `processed` flag.

WebSocket chat & Redis pub/sub
- WebSocket server: websockets/chat-server.php (Ratchet) — room-aware (channel_id).
- It broadcasts messages with metadata (user_name, user_avatar, country_code, message).
- Production: run the WebSocket server as a supervised process and use Redis pub/sub to share messages across multiple WS instances.
- Clients connect via KMWebSocket (compat.js) with fallback polling to `/api.php?rest=chat_poll`.

Admin & moderation
- Admin Jobs Dashboard: admin/jobs.php + ajax/admin_jobs_api.php — view encoding_jobs, retry, requeue, cancel, delete.
- Moderation flows: comment reports, archive/restore channels, delete comments; moderation checks are performed server-side.
- Developer utilities: testmaster.php (diagnostics), user_fix.php (recalc storage).

API endpoints (quick reference)
- ajax/media_api.php — media actions: toggle_privacy, delete, enqueue_hls, enqueue_thumbnail, edit_meta, playlist actions.
- ajax/editor_api.php — enqueue 'trim' jobs.
- ajax/remix_api.php — enqueue 'remix' jobs.
- ajax/admin_jobs_api.php — admin job management.
- ajax/channel_actions.php — subscribe/unsubscribe, chat_send fallback, archive/restore channel, reddit stub.
- comment_post.php — comments posting, reporting, deletion.
- analytics.php — record_view and generic events.
- api.php — listing, search and small AJAX actions for channels/search.
- See docs/API-endpoints.md and docs/postman_collection.json for detailed examples.

CI & testing
- tests/ci_check_ffmpeg.sh — verifies FFmpeg presence.
- tests/ci_worker_smoke.sh — inserts a test encoding job to validate DB insertion.
- Example GitHub Actions workflow: .github/workflows/ci.yml runs FFmpeg check and worker smoke test (using a MySQL service).
- Recommended: extend CI to lint PHP, run static analysis, and run integration tests with a seeded DB and Redis.

Deployment notes (systemd / supervisor)
- Two recommended ways to supervise the worker:
  - systemd unit: systemd/kidsmaster-worker.service — install to `/etc/systemd/system/` and enable:
    sudo systemctl daemon-reload
    sudo systemctl enable --now kidsmaster-worker.service
  - Supervisor config: supervisor/kidsmaster-worker.conf — add to `/etc/supervisor/conf.d/`, then reload supervisor.
- Ensure worker runs as the same user that has access to storage files (e.g., `www-data`).
- If using WebSocket server, supervise websockets/chat-server.php similarly.

Security & production hardening checklist
- Use HTTPS for all web traffic; set session cookie flags: Secure, HttpOnly, SameSite.
- Implement rate limiting on login, signup, upload and comment endpoints.
- Add email verification and password reset flows for authentication (scaffold exists; complete before production).
- Scan uploaded files (virus scanning) and validate MIME types strictly. Consider processing uploads in a sandboxed worker (not in the web worker).
- Serve media via a CDN or signed URLs; avoid serving raw uploads from webroot for private content.
- Move heavy jobs (FFmpeg processing) to dedicated worker nodes; keep web servers stateless.
- Use Redis for caching and pub/sub across multiple web/worker nodes.
- Add moderation dashboards and roles; log moderation actions.

Unit tests & CI
- Included scripts: tests/ci_check_ffmpeg.sh and tests/ci_worker_smoke.sh.
- Recommended tests to add:
  - Unit tests for API endpoints (PHP unit tests with a test DB).
  - Integration test for upload flow (chunked upload + finalize).
  - Worker unit tests (mock FFmpeg by using small sample files or a stub wrapper).
- Example GitHub Actions workflow is provided (.github/workflows/ci.yml).

Postman & API docs
- docs/postman_collection.json includes a few sample requests (toggle privacy, enqueue hls, trim).
- docs/API-endpoints.md summarizes AJAX endpoints and expected payloads/response formats.
- Consider exporting a full OpenAPI/Swagger doc for machine-readable API docs.

Developer tools & notes
- Use `testmaster.php` for environment diagnostics and quick SQL for dev.
- `setup.php` is a one-time setup wizard to create an admin account and seed categories. It creates a storage/.setup_done lock to prevent rerun.
- The retro theme (assets/css/retro2011.css + assets/js/retro2011.js) is optional; you can toggle per channel or site-wide.

Contributing
- Contributions are welcome. Please open issues for feature requests and bugs.
- For code changes: fork, create a topic branch, and open a pull request. Provide migrations for schema changes and tests for new features.

License
- No license file is included by default. Add a LICENSE (for example MIT) if you intend to open source.

Acknowledgements
- This scaffold reuses common open-source tools: PHP, PDO, Ratchet (WebSocket), FFmpeg, Redis, GD/Imagick.

---

I packaged a complete README that documents the Channels 1.5 Deluxe features, installs, the worker/encoding architecture, admin workflows, and CI checks. Next I can produce either (A) more comprehensive API OpenAPI/Swagger docs, (B) a full Postman collection with auth flows included, or (C) automated database migration scripts (combined and idempotent) for production. Which do you want next?