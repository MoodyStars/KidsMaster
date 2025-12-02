# KidsMaster — XAMPP Setup & Update Guide (Windows)

This guide explains how to set up and run KidsMaster on XAMPP (Windows). It covers:

- copying the code into XAMPP
- configuring Apache virtual host (optional)
- creating the database and importing schema
- configuring PHP (extensions, ffmpeg)
- installing Composer dependencies (WebSocket/Ratchet)
- running the background worker on Windows (Task Scheduler / service wrapper)
- optional Redis advice (Windows alternatives)
- troubleshooting tips

This is intended for local development or small demo environments. For production, use Linux servers and the production hardening checklist in README.md.

Prerequisites
- XAMPP for Windows (with Apache, PHP and MySQL) installed: https://www.apachefriends.org/
- Composer for Windows: https://getcomposer.org/
- FFmpeg binary for Windows added to PATH: https://ffmpeg.org/download.html
- (Optional) Redis: use WSL, Docker, or Windows port like Memurai / Redis on WSL.

Overview
1. Place the KidsMaster repository under XAMPP's htdocs (e.g. C:\xampp\htdocs\kidsmaster).
2. Configure the DB and import schema.
3. Edit _includes/init.php or create .env to set DB credentials.
4. Install composer dependencies: Ratchet (for WebSocket).
5. Configure ffmpeg in PATH and optional phpredis (Windows limitations).
6. Start the worker with a Windows scheduled task or service wrapper.
7. Start the Ratchet WebSocket server (via CLI) if you want real-time chat.

Step-by-step

1) Copy files into XAMPP
- Extract or clone your repository into: C:\xampp\htdocs\kidsmaster
- Ensure directories like `storage/` are writable by the webserver. On Windows with XAMPP that's typically the local user, so write access should be ok.

2) Start XAMPP services
- Launch XAMPP Control Panel; start Apache and MySQL.
- Open phpMyAdmin at http://localhost/phpmyadmin

3) Create the database and import schema
- Create a database `kidsmaster` (or pick another name).
- Import the schema files (can use phpMyAdmin import or CLI):
  - phpMyAdmin: Import `db/schema.sql` and then migration SQL files under `db/migrations/*.sql`.
  - CLI (if you have mysql on PATH):
    mysql -u root -p kidsmaster < db/schema.sql
    mysql -u root -p kidsmaster < db/migrations/20251120_jobs_and_processing.sql
    mysql -u root -p kidsmaster < db/migrations/20251119_add_channel_chat_and_archive.sql
    etc.

4) Configure DB credentials
- By default the scaffold reads environment variables. For XAMPP, most devs use `root` user (no password). Edit `_includes/init.php` or set environment variables.
- Example edit in `_includes/init.php` (recommended during dev only):
  - set `$host = '127.0.0.1'; $db = 'kidsmaster'; $user = 'root'; $pass = '';`

5) Enable/verify required PHP extensions
- Open XAMPP Control Panel -> PHP.ini (Config button) -> open php.ini
- Ensure extensions enabled:
  - extension=gd (for image functions) — often enabled by default
  - extension=openssl
  - extension=pdo_mysql
  - extension=curl
  - extension=mbstring
  - If you want Redis on Windows, `php_redis` is tricky; prefer using Redis in WSL/Docker. For local demo, the worker will fallback to DB polling if Redis is missing.
- Restart Apache after editing php.ini.

6) Install FFmpeg and add to PATH
- Download FFmpeg static build for Windows and add the folder with `ffmpeg.exe` to your system PATH.
- Test in a command prompt:
  ffmpeg -version

7) Install Composer dependencies (for Ratchet etc.)
- Open a command prompt at the project root (C:\xampp\htdocs\kidsmaster)
- Run:
  composer install
- If composer is not installed, install Composer for Windows and ensure composer.exe is on PATH.

8) WebSocket server (Ratchet)
- Start the WebSocket server in a separate CLI:
  cd C:\xampp\htdocs\kidsmaster\websockets
  php chat-server.php
- If you want to run the WebSocket server at startup, create a Windows service wrapper (nssm) or scheduled task that runs this command.

9) Worker (FFmpeg job processing) on Windows
Option A — run manually (quick demo)
- Open a command prompt and run:
  cd C:\xampp\htdocs\kidsmaster\workers
  php worker.php

Option B — run as a scheduled task at startup (recommended)
- Create a Scheduled Task:
  - Action: Start a program
  - Program/script: C:\xampp\php\php.exe
  - Add arguments: C:\xampp\htdocs\kidsmaster\workers\worker.php
  - Configure to run whether user is logged on or not; set to run at system startup.
- Or use NSSM (Non-Sucking Service Manager) to wrap php worker as a Windows service.

10) Enable ffmpeg worker & jobs
- Ensure `encoding_jobs` table exists (see migrations). You can enqueue from the admin UI or run tests/ci_worker_smoke.sh equivalent to insert a job.

11) Adjust file paths & URLs
- For Windows development, many paths in code assume forward slashes and web-root-relative URLs (e.g., `/storage/uploads/...`). Those are fine with Apache in XAMPP.
- If you change the document root or configure vhosts, update `base_url` references and the `storage` folder accessibility.

12) (Optional) Apache Virtual Host
- To use a friendly local domain (e.g., kidsmaster.local), add a virtual host.
- Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf` — add the vhost snippet below (see file vhosts-kidsmaster.conf)
- Update `C:\Windows\System32\drivers\etc\hosts` and add:
  127.0.0.1 kidsmaster.local
- Restart Apache.

13) Optional — Redis on Windows
- Redis for Windows is not officially supported; recommended options:
  - Run Redis in WSL (Windows Subsystem for Linux).
  - Run Redis in Docker (Windows Docker Desktop).
  - Use Memurai (commercial, Redis-compatible) for Windows.
- If Redis isn't available, workers and chat will fallback to DB polling and HTTP fallback. This is slower but works for dev/demo.

14) Post-setup — run the setup wizard
- Open http://localhost/kidsmaster/setup.php and create the admin user.
- Use the admin UI to seed content, create channels, and test upload/worker flows.

15) Troubleshooting
- If file uploads fail: check `storage/` folder permissions and Apache/PHP upload_max_filesize/post_max_size in php.ini.
- If ffmpeg jobs fail: run ffmpeg command shown in worker logs manually to inspect errors.
- If WebSocket fails: check the port (8080) is not blocked by Windows Firewall — allow inbound/outbound for php.exe or the port.
- If Composer packages fail: ensure the PHP CLI used by Composer matches XAMPP PHP version (path to php.exe in PATH).

Best practices for XAMPP development
- Use XAMPP only for local development. For production choose a Linux server.
- Keep your local secrets (DB passwords) out of version control; edit `_includes/init.php` or use a simple `.env` and a small loader.
- Use Redis in a dev container if you need to test pub/sub; otherwise rely on DB fallback.

Files included in this guide
- `apache/vhosts-kidsmaster.conf` — Apache vhost snippet (see below)
- `scripts/start_worker_xampp.bat` — batch wrapper to run worker under XAMPP PHP
- `scripts/register_worker_task.ps1` — PowerShell script to register a Scheduled Task (example)
- `.env.xampp.example` — example environment file for XAMPP (edit and rename to `.env.local` if you load env)

---

Below are a few helper files to speed setup on XAMPP. Put them into your project if helpful: