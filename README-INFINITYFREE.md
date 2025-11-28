# Deploying KidsMaster to InfinityFree — Step‑by‑Step Tutorial

This guide walks you through deploying the KidsMaster PHP + MySQL scaffold to InfinityFree (free shared PHP hosting). It highlights the constraints of InfinityFree and shows practical adjustments and workarounds so your site runs reliably there. It also covers how to replace features that cannot run on InfinityFree (workers, FFmpeg, WebSocket server) with hosted alternatives.

Important: InfinityFree is free shared hosting with limitations (no background processes, limited disk/quota, no custom ports, limited php.ini control). This guide explains how to adapt KidsMaster accordingly.

Contents
- Overview & constraints
- Prep: what to change in the KidsMaster codebase before upload
- Create MySQL DB in InfinityFree and import schema
- Upload the project files (FTP / File Manager)
- Configure KidsMaster for InfinityFree (example config)
- Replace/disable unavailable features and recommended hosted alternatives
  - Background workers & FFmpeg (external worker)
  - WebSocket chat (use Firebase or Pusher) — example Firebase client
  - Storage, quotas & large uploads (use S3 / Cloudflare R2 / Cloudinary)
  - Scheduled tasks / cron (use external cron services)
- Post‑deploy checklist & testing
- Tips, troubleshooting & recommended next steps

---

Overview & Constraints of InfinityFree
- Pros: Free PHP + MySQL hosting, easy control panel, FTP access, phpMyAdmin.
- Cons / limitations you must plan for:
  - No long-running background processes (no systemd, no worker CLI).
  - No custom TCP ports (cannot run a WebSocket server on port 8080).
  - Limited disk space and file size limits (often a few GB total and small upload/post limits).
  - php.ini settings are restricted — upload_max_filesize / post_max_size are limited by host.
  - FFmpeg and other binaries are not available on InfinityFree.
  - Composer may not run on the host; vendor files should be prepared locally.

These limitations mean you must adapt KidsMaster: run heavy jobs (FFmpeg, HLS packaging, trimming) offsite; use hosted realtime services (Firebase, Pusher) for chat; and store large media in external object storage/CDN.

---

1) Prep: adjust the KidsMaster code locally before upload
Do these edits locally (on your dev machine) before uploading to InfinityFree.

a) Provide a simple local config file loader
Create a small file to store DB credentials and service toggles. Add file (not committed to git):

```php
// config.local.php (place in project root; add to .gitignore)
<?php
// Replace with InfinityFree DB details (you will fill them from control panel)
define('KM_DB_HOST', 'sql###.epizy.com');   // host provided by InfinityFree
define('KM_DB_NAME', 'epiz_12345678_db');  // database name
define('KM_DB_USER', 'epiz_12345678');     // user
define('KM_DB_PASS', 'your_db_password');

// Feature toggles — disable features that InfinityFree can't support directly
define('KM_USE_WORKER', false);
define('KM_USE_WEBSOCKETS', false); // use fallback / 3rd party
define('KM_USE_FFMPEG_LOCAL', false); // ffmpeg not available
// Replace real-time provider if you will use Firebase
define('KM_REALTIME_PROVIDER', 'firebase'); // 'pusher' or 'firebase' or 'none'
```

Update `_includes/init.php` to require that file if present and to use defined constants:
- If your scaffold already uses getenv/KM_DB_*, ensure it falls back to constants above. Example snippet in init.php:

```php
// At the top of _includes/init.php
if (file_exists(__DIR__ . '/../config.local.php')) {
    require_once __DIR__ . '/../config.local.php';
}
$host = defined('KM_DB_HOST') ? KM_DB_HOST : getenv('KM_DB_HOST') ?: '127.0.0.1';
$db   = defined('KM_DB_NAME') ? KM_DB_NAME : getenv('KM_DB_NAME') ?: 'kidsmaster';
$user = defined('KM_DB_USER') ? KM_DB_USER : getenv('KM_DB_USER') ?: 'km_user';
$pass = defined('KM_DB_PASS') ? KM_DB_PASS : getenv('KM_DB_PASS') ?: 'km_pass';
```

b) Disable worker-dependant UI and buttons (temporarily)
- Hide or disable HLS generation / trim / remix buttons in the admin UI or make them show a notice: "Requires external worker".
- Ensure `enqueue_*` AJAX endpoints either check `KM_USE_WORKER` or return a friendly message if false.

c) Reduce default chunk size for uploads
InfinityFree may restrict POST size; choose a conservative chunk size (e.g., 1 MB):

- In `assets/js/upload_widget.js` set `CHUNK_SIZE = 1 * 1024 * 1024;`.
- On server, `upload.php` already supports chunks. Make sure the assembled file handling is tolerant.

d) Prepackage vendor files
- If your code uses composer packages (Ratchet, etc.), pre-run `composer install` locally and upload `/vendor/` to the host. On InfinityFree, composer probably cannot be run.

---

2) Create MySQL DB in InfinityFree and import schema

a) Create MySQL database
1. Log into your InfinityFree Control Panel.
2. Go to "MySQL Databases".
3. Create a new database; note:
   - Hostname (like `sql###.epizy.com`)
   - Database name
   - Username
   - Password

b) Import the DB schema via phpMyAdmin
1. In the control panel, open phpMyAdmin for your database.
2. Import schema file(s):
   - `db/schema.sql` (or a minimal schema you created)
   - Optionally, run the migrations you need (but be cautious — InfinityFree may have execution time limits).
3. For large schema files, split if necessary or run critical table creation first (users, channels, media, comments, chat_messages, storage_files).

Note: If your schema uses JSON or advanced MySQL features not supported by the hosted MySQL version, adapt SQL accordingly.

---

3) Upload project files to InfinityFree
You can use the InfinityFree File Manager or FTP (FileZilla recommended).

a) Upload location
- Upload your site files into the `htdocs` directory of the domain/subdomain provided by InfinityFree.
- Ensure `_includes`, `assets`, `ajax`, and `upload.php` are uploaded. Keep the `storage/` directory writable if provided by host (usually `htdocs/storage` will count against your disk quota).

b) File permissions
- InfinityFree typically handles file permissions. Make sure `storage/` is writable (permissions via File Manager).

c) Large files & vendor
- Upload `vendor/` directory (composer dependencies) if used.
- Upload `config.local.php` (with live DB creds) — keep it secret.

---

4) Configure KidsMaster on InfinityFree
Edit `_includes/init.php` or provide `config.local.php` (as shown earlier) with the MySQL credentials you created.

Example `config.local.php` (repeat, but fill creds from control panel):

```php
<?php
define('KM_DB_HOST', 'sql###.epizy.com');
define('KM_DB_NAME', 'epiz_12345678_db');
define('KM_DB_USER', 'epiz_12345678');
define('KM_DB_PASS', 'strong_password_here');

define('KM_USE_WORKER', false);
define('KM_REALTIME_PROVIDER', 'firebase');
```

Other adjustments:
- Ensure web root index files exist at `htdocs/index.php`.
- If InfinityFree forces a custom index (index.html), remove/rename it.

---

5) Replace or offload unavailable features (practical options)

A. Background workers & FFmpeg (required for HLS, thumbnails, trims, remixes)
InfinityFree cannot run ffmpeg or worker CLI scripts. Options:
1. Offload processing to a small VPS / cloud instance (DigitalOcean, Linode, Vultr, AWS, Railway, Render).
   - Deploy `workers/worker.php` on that host.
   - Give it DB credentials (or use a job queue like Redis on a shared host).
   - Use systemd/supervisor as in earlier instructions.
2. Use serverless encoding services or managed transcoding:
   - AWS Elastic Transcoder / AWS MediaConvert / Cloudinary / Mux.
   - Upload originals to S3/R2/Cloudinary, and use their APIs to generate HLS & thumbnails.
3. Use a third-party “function” to run ffmpeg (e.g., Cloudflare Workers + external processing is not practical) — best to use a small VM.

How to integrate:
- Keep `KM_USE_WORKER = false` on InfinityFree.
- The InfinityFree web app should send a webhook to your external worker or API (e.g., call `https://my-vps.example.com/enqueue` to create a job).
- Or, have the worker poll the DB on a remote host (your worker must be able to reach the database).

B. WebSocket chat — use Firebase or Pusher
You cannot run a Ratchet server on InfinityFree. Replace with a hosted realtime service.

Recommended: Firebase Realtime Database / Firestore (cheap and simple):

- Create a Firebase project, enable Realtime Database (or Firestore).
- In your channel page replace WebSocket usage with Firebase client pushes/pulls.
- Demo Firebase client (save as `assets/js/firebase-chat.js` and include it in pages):

```javascript
// assets/js/firebase-chat.js
// Minimal Firebase real-time chat integration (client-side)
(function(){
  // Replace these with your Firebase config
  var firebaseConfig = {
    apiKey: "YOUR_API_KEY",
    authDomain: "PROJECT.firebaseapp.com",
    databaseURL: "https://PROJECT.firebaseio.com",
    projectId: "PROJECT",
    storageBucket: "PROJECT.appspot.com",
    messagingSenderId: "SENDER_ID",
    appId: "APP_ID"
  };
  // Load Firebase SDK (include <script src="https://www.gstatic.com/firebasejs/9.x/firebase-app-compat.js"></script> and database-compat)
  if (!window.firebase) return;
  firebase.initializeApp(firebaseConfig);
  var db = firebase.database();

  function chatRoomRef(channelId) {
    return db.ref('channel_chat/' + channelId);
  }

  window.kmFirebaseChatInit = function(channelId, onNew) {
    var ref = chatRoomRef(channelId);
    ref.limitToLast(100).on('child_added', function(snapshot) {
      var data = snapshot.val();
      onNew && onNew(data);
    });
    return {
      send: function(user, avatar, country, message) {
        ref.push({ user_name: user, user_avatar: avatar, country_code: country, message: message, created_at: Date.now() });
      }
    };
  };
})();
```

- Modify channel client JS to call `kmFirebaseChatInit(channelId, callback)` and use returned `.send(...)` to publish.

Pusher / Ably / Socket.IO (managed) are other choices — all remove the need for a local WebSocket server.

C. Storage & large uploads — use S3 / Cloudflare R2 / Cloudinary
- InfinityFree has limited storage and serves files from your shared account (not ideal for lots of video).
- Recommended approach:
  1. Upload original files directly to S3/R2/Cloudinary from the browser (presigned uploads). Use InfinityFree only for metadata (DB) and UI.
  2. Store public URLs returned by those services in `media.file_url`.
  3. Use the external transcoder (e.g., AWS Lambda + ElasticTranscoder or Mux) to create HLS.

If you cannot implement direct-to-S3 uploads yet:
- Accept uploads to InfinityFree (if small) and then have your external worker fetch them by URL (but watch disk quota).

D. Cron / Scheduled tasks — external cron service
- InfinityFree usually blocks cron. Use services like `cron-job.org`, `uptimerobot`, or GitHub Actions scheduler to call endpoints for periodic tasks (e.g., `https://your-site.example.com/admin/cron-run.php`) to do low-traffic admin jobs (but heavy FFmpeg tasks still need external worker).

---

6) Post‑deploy checklist & testing

1. Verify DB connection
   - Visit `yourdomain.com/testmaster.php` to run diagnostics (DB connection, directories writable).
2. Run setup wizard
   - `yourdomain.com/setup.php` — create an admin account and seed categories.
3. Upload a small media file using Upload UI
   - Ensure chunks complete and a media row is created.
   - If the upload is large and `post_max_size` is too small, you will need direct-to-S3 or lower chunk size.
4. Test chat using the chosen provider (Firebase/Pusher)
5. Test admin jobs UI — enqueue thumbnail request; note: with worker offsite the job must be delivered to that worker.
6. Inspect errors/logs in InfinityFree file manager / control panel and in your external worker logs.

---

7) Troubleshooting & tips

- Upload size errors: check `phpinfo()` or InfinityFree documentation for `upload_max_filesize` and `post_max_size`. If too small, use smaller chunk size or direct-to-cloud uploads.
- Permission issues: InfinityFree will generally allow writes in your `htdocs` but check storage limits and delete unneeded files.
- Missing binaries (ffmpeg): confirm with `testmaster.php` or ask host — likely unavailable. Use remote worker.
- WebSocket fallback: ensure `KM_USE_WEBSOCKETS` is false and that your code uses your realtime provider fallback (Firebase).
- Cron tasks: use external cron service to poll endpoints on your site.
- SSL: enable Free SSL (AutoSSL) provided by InfinityFree or use Cloudflare in front for HTTPS.

---

8) Recommended minimal configuration for InfinityFree deployment

- config.local.php — DB creds + feature toggles (as above)
- CHUNK_SIZE = 1 MB in `assets/js/upload_widget.js`
- KM_USE_WORKER = false
- KM_REALTIME_PROVIDER = 'firebase' (or 'pusher')

Example minimal `config.local.php` (repeat):

```php
<?php
define('KM_DB_HOST','sql###.epizy.com');
define('KM_DB_NAME','epiz_12345678_db');
define('KM_DB_USER','epiz_12345678');
define('KM_DB_PASS','password');

define('KM_USE_WORKER', false);
define('KM_USE_WEBSOCKETS', false);
define('KM_REALTIME_PROVIDER', 'firebase');
```

---

9) Next steps (if you want features restored)

To restore full functionality (HLS, FFmpeg, WebSocket scaling, background jobs) do one of the following:
- Deploy workers & FFmpeg on a small VPS and connect your InfinityFree site to it (recommended). The web UI is still hosted on InfinityFree; heavy processing happens on the VPS.
- Or migrate the entire site to a provider that supports background processes (DigitalOcean, Linode, AWS EC2, Render, Railway, Fly.io). This yields a simpler unified deployment.
- Use managed services:
  - Cloudinary / Mux for video encoding
  - Firebase / Pusher for realtime chat
  - S3 / Cloudflare R2 for storage and CDN

---

10) Example: Quick path to working system (recommended)
1. Host the web UI on InfinityFree (cheap, free).
2. Use Cloudinary or S3 for storage and direct upload from browser.
3. Use Cloudinary/Mux to transcode into HLS/thumbnail automatically.
4. Use Firebase for live chat (real-time).
5. Use a small VPS for worker tasks only if you need custom ffmpeg flows (trim/remix). Worker connects to the same MySQL DB (carefully expose DB + secure it) or accept job webhooks.

---

Support & resources
- InfinityFree support pages & community.
- Firebase docs (Realtime Database / Firestore).
- Cloudinary / Mux for media processing.
- Small VPS providers: DigitalOcean, Linode, Vultr, or platform services (Render, Railway).

---

If you'd like, I can:
- Provide a ready-to-drop `config.local.php` template and a short patch to replace WebSocket chat with a Firebase chat implementation (client JS + minimal server hook).
- Generate a step-by-step checklist with screenshots for InfinityFree Control Panel actions (DB creation, file manager).
- Produce a simplified worker webhook endpoint you can host on a small VPS so InfinityFree simply calls a webhook to request processing.

Which would you like next?