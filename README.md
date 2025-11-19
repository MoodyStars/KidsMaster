# KidsMaster — Share your Memes & Nostalgia

KidsMaster is a lightweight PHP + MySQL scaffold for a nostalgic, family-friendly media sharing site (2011 layout vibe with a 2025 reveal). What I built so far is a multi-page prototype (Homepage, Browse, Watch, Channels scaffolds) plus a small API, a chunked upload handler, CSRF & authentication scaffolds, subscription/playlist/statistics endpoints, moderation hooks, and a Ratchet WebSocket scaffold for real-time chat. The intent was to create a practical, extendable starting platform you can harden and scale.

This README documents what is included, how to run it locally, and practical next steps to take this prototype toward production.

Table of contents
- Overview
- Key features implemented
- Repo layout (important files)
- Requirements
- Quick start (local)
- Database
- Running the WebSocket chat server
- Uploads, thumbnails & HLS notes
- Redis cache (optional)
- Security & production hardening notes
- Next steps (practical priorities)
- Contributing
- License

Overview
--------
KidsMaster is a proof-of-concept platform for hosting videos, audio, images, software and small game artifacts, with:
- Multi-page UI: index (home), browse, watch, channel scaffolds.
- Lightweight API functions in PHP for listing media, comments, channels, and admin actions.
- Secure-ish auth skeleton (registration/login), CSRF helpers and session-based user handling.
- Chunked/resumable upload endpoint with server-side file type checks and per-user storage quota (example quota set to 512 GB).
- Thumbnail generation (GD/Imagick); rudimentary ffmpeg snapshot for video thumbnails and an HLS-generation placeholder.
- Subscriptions + playlists + stats collection.
- Moderation endpoints for reports and comment removal.
- Real-time chat scaffold via Ratchet (WebSocket) and client-side WebSocket integration with polling fallback.
- Stubs for emoji picker and country-flag UI (integration points are provided).

Key features implemented
------------------------
- Pages: index.php, browse.php, watch.php (UI + PHP templates)
- API & helpers: api.php (extended), includes/auth.php, includes/csrf.php
- Uploads: upload.php implements chunked upload, file validation, per-user quota checking and thumbnail generation
- Chat: websockets/chat-server.php (Ratchet scaffold) + client updates in assets/js/main.js
- DB schema: db/schema.sql (initial) + db/schema-updates.sql (subscriptions, playlists, reports, stats, storage index)
- Frontend assets: assets/css/style.css, assets/js/main.js
- Dev README: README-updates.md (notes on running workers, ffmpeg, etc.)

Repo layout (high-level)
------------------------
- index.php — homepage scaffold
- browse.php — browse UI
- watch.php — watch page with chat & comments
- upload.php — chunked upload handler
- api.php — central DB helpers + AJAX endpoints (listing, chat polling, etc.)
- includes/
  - auth.php — simple auth helpers (register/login)
  - csrf.php — CSRF helpers
- websockets/
  - chat-server.php — Ratchet WebSocket server scaffold
- assets/
  - css/style.css
  - js/main.js
- db/
  - schema.sql
  - schema-updates.sql
- storage/ — local storage area for uploaded files (created at runtime)
- README.md — (this file)

Requirements
------------
- PHP 8.x (or 7.4+, but 8.x recommended)
- MySQL / MariaDB
- Composer (for Ratchet/websocket server)
- ffmpeg (optional but required for video thumbnail and HLS generation)
- Optional: Redis + phpredis extension (optional caching)
- Web server (Apache/Nginx) or PHP built-in server for development

Quick start (local)
-------------------
1. Clone the project into your PHP webroot.

2. Adjust DB credentials
   - Edit api.php -> db() and set host, dbname, user and password to match your environment.

3. Create database and tables
   - Import schema files:
     - mysql -u root -p < db/schema.sql
     - mysql -u root -p < db/schema-updates.sql

4. Install composer dependencies for WebSockets (optional)
   - composer require cboden/ratchet

5. Run dev server (simple):
   - php -S 127.0.0.1:8000
   - Open http://127.0.0.1:8000 in your browser.

6. Seed some data
   - Manually insert a user and a channel, or use the registration endpoint to create accounts.
   - Add a few media rows (thumbnail/file_url) to test browse/watch pages.

Database notes
--------------
- The provided schema includes tables for users, channels, media, comments, chat_messages, storage_files.
- schema-updates.sql adds subscriptions, playlists, playlist_items, reports, stats_views, and is_moderator flag for users.
- The upload flow writes records to storage_files and media tables; ensure the storage/uploads path is writable by the web server.

Running the WebSocket chat server
--------------------------------
The Ratchet server is a separate PHP CLI process and is optional (we also keep a polling fallback).
1. Install Ratchet:
   - composer require cboden/ratchet
2. Run the server:
   - php websockets/chat-server.php
3. Default port is 8080. Adjust the port in the file if required and update the client code accordingly.

Note: The scaffolded chat server is minimal and broadcasts all messages to all connected clients. For production,
implement room/channel scoping, authentication and optionally persist chat messages in Redis or the DB.

Uploads, thumbnails & HLS
-------------------------
- upload.php supports chunked uploads. Client should send chunk parts named `chunk` with parameters upload_id, chunk_index, total_chunks, filename.
- Server detects basic MIME type and classifies file as video/audio/image/software/storage.
- Images: thumbnails generated via GD or Imagick.
- Video: script uses ffmpeg to snapshot a frame (if ffmpeg present).
- HLS: api_generate_hls() is a placeholder that runs ffmpeg to create an m3u8 + segments; you should run heavy ffmpeg jobs in a background worker system (queue) rather than in the web request thread.
- Storage quotas: example quota is 512GB per user (549,755,813,888 bytes). Adjust or implement plans/limits as needed.

Redis cache (optional)
----------------------
- api.php contains a cache() helper that attempts to connect to Redis (phpredis extension).
- If Redis is available, use it for frequently-read endpoints (featured lists, channel lists) to reduce DB load.
- Remember to add cache invalidation when content changes.

Security & production hardening (short checklist)
------------------------------------------------
- Use HTTPS and set secure session cookie flags (Secure, HttpOnly, SameSite).
- Harden sessions (session cookie lifetime, session_regenerate_id on login).
- Implement email verification, password reset, rate limiting and login throttling.
- Validate and sanitize all user input server-side; use proper escaping for HTML output.
- Serve uploaded media from a dedicated domain or signed URLs rather than directly from the webroot if you need access control.
- Queue heavy work (thumbnail generation, ffmpeg/HLS transcoding) into a worker process (RabbitMQ/Redis + worker).
- Implement moderator role checks before deletion/report actions.
- Run ffmpeg and other system tools under a limited user and in a rate-limited/queued context.

Next steps — practical priorities
---------------------------------
I already added the scaffolds for auth, CSRF, chunked uploads, subscriptions/playlists, moderation hooks and a WebSocket server. The most valuable next steps to make this production-ready are:
1. Implement full authentication flows (signup forms, email verification, password reset) and rate-limiting.
2. Move media processing to background workers (thumbnail + HLS + encoding) and add a job queue.
3. Harden access to upload endpoints and store files behind signed URLs / CDN.
4. Replace the broadcast-only WebSocket server with a room-aware, authenticated server and persist chat history in Redis or DB.
5. Add moderation UI and policies (age gating for NSFW categories, content takedown flow).
If you'd like, I can generate the full signup/login pages (with CSRF integration), a resumable JS upload widget and a worker example next.

Contributing
------------
Contributions are welcome. Open an issue describing your change or a PR targeting a feature branch. Major changes (auth/security, storage architecture, streaming infra) should be proposed with an implementation plan so we can discuss deployment considerations.

License
-------
This scaffold is provided as-is for prototyping and educational purposes. No license file is included by default — add a LICENSE (MIT or other) if you want to open source this project.

Acknowledgements
----------------
This scaffold draws on common open-source tools: PHP, PDO/MySQL, Ratchet (WebSockets), ffmpeg, GD/Imagick, and optional Redis. It also follows the spirit of retro video-sharing sites while adding modern, extensible building blocks.

---

If you want, I will now:
- Generate the signup/login pages with CSRF fields and server-side validation, plus unit-testable endpoints; or
- Produce a resumable upload JavaScript widget (Dropzone-like) wired to upload.php with progress UI; or
- Replace the WebSocket scaffold with an authenticated, room-aware server using Redis pub/sub.

Tell me which you prefer and I’ll generate the files and commands next.  