```markdown
# KidsMaster AJAX API - Endpoints (summary)

This document lists the AJAX endpoints created in the recent update and the JSON shapes expected/returned.

Base: /ajax/*.php and /analytics.php

1) POST /ajax/media_api.php
- Actions:
  - toggle_privacy
    - POST: { action: 'toggle_privacy', media_id, _csrf }
    - Response: { ok:1 } or { ok:0, error }
  - delete
    - POST: { action: 'delete', media_id, _csrf }
    - Response: { ok:1 }
  - enqueue_hls
    - POST: { action: 'enqueue_hls', media_id, _csrf }
    - Response: { ok:1, job_id }
  - enqueue_thumbnail
    - POST: { action: 'enqueue_thumbnail', media_id, _csrf }
    - Response: { ok:1, job_id }
  - edit_meta
    - POST: { action: 'edit_meta', media_id, title?, description?, tags?, _csrf }
    - Response: { ok:1 }
  - add_to_playlist
    - POST: { action: 'add_to_playlist', playlist_id, media_id, _csrf }
    - Response: { ok:1 }
  - remove_from_playlist
    - POST: { action: 'remove_from_playlist', playlist_id, media_id, _csrf }
    - Response: { ok:1 }

2) POST /ajax/editor_api.php
- Trimming video
  - POST: { action: 'trim', media_id, start, end, _csrf }
  - Response: { ok:1, job_id }

3) POST /ajax/remix_api.php
- Submit audio remix job
  - POST: { action: 'create', media_id, preset, _csrf }
  - Response: { ok:1, job_id }

4) Worker queue
- Redis queue key: kidsmaster:jobs
- Jobs push format: JSON { job_id, type, payload }
- encoding_jobs table stores persistent job records.

5) Analytics
- POST /analytics.php?action=record_view
  - body: media_id
  - Response: { ok:1 }

Notes
- All POST endpoints expect CSRF token via POST _csrf or X-CSRF-Token header.
- The worker (workers/worker.php) must run as a long-lived CLI process with access to ffmpeg.
- Outputs (HLS/index.m3u8, thumbnails) are written under storage/ and stored in media.hls_url and media.thumbnail.

```