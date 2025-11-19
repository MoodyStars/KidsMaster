<?php
// api.php (UPDATED)
// Adds subscription, playlists, stats, moderation, storage tracking and Redis cache optionality.
date_default_timezone_set('UTC');

function db() {
    static $pdo;
    if ($pdo) return $pdo;
    $host = '127.0.0.1';
    $db   = 'kidsmaster';
    $user = 'km_user';
    $pass = 'km_pass';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $pdo = new PDO($dsn, $user, $pass, $opts);
    return $pdo;
}

// Optional Redis cache helper (if phpredis installed and running)
function cache() {
    static $redis = null;
    if ($redis !== null) return $redis;
    if (extension_loaded('redis')) {
        try {
            $r = new Redis();
            $r->connect('127.0.0.1', 6379, 0.5);
            $redis = $r;
        } catch (Exception $e) {
            $redis = false;
        }
    } else $redis = false;
    return $redis;
}

/* Existing media listing functions (unchanged) - assume previous implementation here */
require_once __DIR__ . '/db/api_core_media.php'; // (extract long media functions to keep file readable) - placeholder

/* Storage usage helpers */
function api_get_user_storage_usage($user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(size),0) as total FROM storage_files WHERE owner_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $row = $stmt->fetch();
    return (int)$row['total'];
}

function api_add_user_storage_usage($user_id, $bytes) {
    // This function expects the file to be also recorded in storage_files table in your upload flow.
    // As a simple counter we insert a storage_files record with size; if not using, consider alternate tracking.
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO storage_files (owner_id, file_name, path, size, created_at) VALUES (:o,:n,:p,:s,:ts)");
    $stmt->execute([':o'=>$user_id, ':n'=>'uploaded_file', ':p'=>'', ':s'=>$bytes, ':ts'=>date('Y-m-d H:i:s')]);
}

/* Subscriptions */
function api_subscribe_channel($user_id, $channel_id) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT IGNORE INTO subscriptions (user_id, channel_id, created_at) VALUES (:u,:c,:ts)");
    $stmt->execute([':u'=>$user_id,':c'=>$channel_id,':ts'=>date('Y-m-d H:i:s')]);
    // update count
    $pdo->prepare("UPDATE channels SET subscribers = (SELECT COUNT(*) FROM subscriptions WHERE channel_id = channels.id) WHERE id = :id")->execute([':id'=>$channel_id]);
    return ['ok'=>1];
}

function api_unsubscribe_channel($user_id, $channel_id) {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE user_id = :u AND channel_id = :c");
    $stmt->execute([':u'=>$user_id,':c'=>$channel_id]);
    $pdo->prepare("UPDATE channels SET subscribers = (SELECT COUNT(*) FROM subscriptions WHERE channel_id = channels.id) WHERE id = :id")->execute([':id'=>$channel_id]);
    return ['ok'=>1];
}

/* Playlists */
function api_create_playlist($user_id, $name, $public = 1) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO playlists (owner_id, name, is_public, created_at) VALUES (:o,:n,:p,:ts)");
    $stmt->execute([':o'=>$user_id,':n'=>$name,':p'=>$public?1:0,':ts'=>date('Y-m-d H:i:s')]);
    return $pdo->lastInsertId();
}

function api_add_to_playlist($playlist_id, $media_id) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT IGNORE INTO playlist_items (playlist_id, media_id, created_at) VALUES (:p,:m,:ts)");
    $stmt->execute([':p'=>$playlist_id,':m'=>$media_id,':ts'=>date('Y-m-d H:i:s')]);
    return ['ok'=>1];
}

/* Stats */
function api_record_view($media_id, $user_id = null) {
    $pdo = db();
    $pdo->prepare("UPDATE media SET views = views + 1 WHERE id = :id")->execute([':id'=>$media_id]);
    $pdo->prepare("INSERT INTO stats_views (media_id, user_id, created_at) VALUES (:m,:u,:ts)")->execute([':m'=>$media_id,':u'=>$user_id,':ts'=>date('Y-m-d H:i:s')]);
}

/* Channel editing (profile pic, banner, gif banner) */
function api_edit_channel($channel_id, $owner_id, $data = []) {
    $pdo = db();
    // Basic authorization: only owner may edit
    $stmt = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$channel_id]);
    $c = $stmt->fetch();
    if (!$c || $c['owner_id'] != $owner_id) return ['ok'=>0,'error'=>'not_owner'];

    $fields = [];
    $params = [':id'=>$channel_id];
    if (isset($data['name'])) { $fields[] = "name = :name"; $params[':name'] = substr($data['name'],0,150); }
    if (isset($data['description'])) { $fields[] = "description = :desc"; $params[':desc'] = $data['description']; }
    if (isset($data['banner'])) { $fields[] = "banner = :banner"; $params[':banner'] = $data['banner']; }
    if (isset($data['profile_pic'])) { $fields[] = "profile_pic = :pp"; $params[':pp'] = $data['profile_pic']; }
    if (isset($data['gif_banner'])) { $fields[] = "gif_banner = :gb"; $params[':gb'] = $data['gif_banner']; }

    if (empty($fields)) return ['ok'=>0,'error'=>'nothing_to_update'];

    $sql = "UPDATE channels SET " . implode(',', $fields) . " WHERE id = :id";
    $pdo->prepare($sql)->execute($params);
    return ['ok'=>1];
}

/* Moderation: reports and deletions */
function api_report_media($reporter_id, $media_id, $reason, $notes = null) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, media_id, reason, notes, created_at) VALUES (:r,:m,:reason,:notes,:ts)");
    $stmt->execute([':r'=>$reporter_id,':m'=>$media_id,':reason'=>$reason,':notes'=>$notes,':ts'=>date('Y-m-d H:i:s')]);
    return ['ok'=>1];
}

function api_delete_comment($comment_id, $moderator_user_id) {
    // Should check moderator permissions - here we assume caller has been authorized
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM comments WHERE id = :id");
    $stmt->execute([':id'=>$comment_id]);
    return ['ok'=>1];
}

/* HLS placeholder: generate HLS manifest and segments via ffmpeg command - production should push to CDN */
function api_generate_hls($filepath, $outdir) {
    // prepare directories and call ffmpeg
    @mkdir($outdir, 0755, true);
    $cmd = "ffmpeg -i " . escapeshellarg($filepath) . " -preset fast -g 48 -sc_threshold 0 -map 0 -f hls -hls_time 6 -hls_list_size 0 -hls_segment_filename " . escapeshellarg($outdir . '/seg%03d.ts') . " " . escapeshellarg($outdir . '/index.m3u8') . " 2>&1";
    exec($cmd, $out, $code);
    return $code === 0;
}