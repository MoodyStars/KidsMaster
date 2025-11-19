```markdown
# KidsMaster — Updates applied

What I implemented:
- Secure authentication scaffold (includes/auth.php).
- CSRF helpers and enforcement (includes/csrf.php).
- Chunked, resumable upload endpoint with file-type validation, storage quota checks (upload.php).
- Thumbnail generation for images (GD/Imagick) and simple ffmpeg snapshot for videos (requires ffmpeg on server).
- Backend functions for subscriptions, playlists, view statistics, channel editing and moderation (api.php updates).
- Database schema additions (db/schema-updates.sql).
- Ratchet WebSocket server scaffold for real-time chat (websockets/chat-server.php). Use composer to install dependencies.
- Frontend WebSocket client + fallback polling, emoji and flag stubs (assets/js/main.js).

How to run the WebSocket server:
1. Install composer and the Ratchet package:
   - composer require cboden/ratchet
2. Run the server:
   - php websockets/chat-server.php
3. Ensure port 8080 is open or change port in the file and client.

Redis caching:
- If you have Redis + phpredis, api.php will connect automatically. Use cache() helper to set/get items for heavy read endpoints (e.g., channel lists, featured media) to reduce DB load.

HLS / CDN:
- api_generate_hls() is a placeholder that calls ffmpeg and outputs an m3u8 + segments. You'll want to copy these outputs to your CDN or storage and serve via a domain for adaptive streaming.

Next immediate hardening steps (do these next):
- Add email verification, password reset and rate limiting to auth flows.
- Implement server-side moderator role checks before calling delete_comment and other moderation endpoints.
- Use prepared file storage isolation and a signed URL scheme for private files.
- Add input sanitization libraries for user-generated content and an XSS filter for comments.
- Replace the simple WebSocket server above with an auth-protected, room-aware implementation and a backing store for chat history (Redis or DB).

```