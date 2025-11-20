<?php
// ajax/media_api.php
// Media management AJAX endpoints: toggle privacy, delete media, enqueue HLS/thumbnail jobs,
// add/remove to playlist, regenerate assets, and simple metadata edits.
//
// Expected requests:
// - POST action=toggle_privacy media_id=... _csrf=...
// - POST action=delete media_id=... _csrf=...
// - POST action=enqueue_hls media_id=... _csrf=...
// - POST action=enqueue_thumbnail media_id=... _csrf=...
// - POST action=edit_meta media_id=... title=... description=... tags=... _csrf=...
// - POST action=add_to_playlist media_id=... playlist_id=... _csrf=...
// - POST action=remove_from_playlist media_id=... playlist_id=... _csrf=...
//
// Responses: JSON { ok:1, ... } or { ok:0, error: '...' }
// This file relies on _includes/init.php providing km_db(), km_current_user(), km_csrf_check()

require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = km_db();
$user = km_current_user();
$action = $_REQUEST['action'] ?? null;

if (!$action) { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

function enqueue_job($pdo, $type, $payload) {
    // insert job record
    $stmt = $pdo->prepare("INSERT INTO encoding_jobs (job_type, payload, status, attempts, created_at, updated_at) VALUES (:t,:p,'queued',0,:ts,:ts)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute([':t'=>$type, ':p'=>json_encode($payload), ':ts'=>$now]);
    $jobId = (int)$pdo->lastInsertId();

    // push to Redis queue for workers (best-effort)
    if (extension_loaded('redis')) {
        try {
            $r = new Redis();
            $r->connect('127.0.0.1', 6379, 0.5);
            $r->rPush('kidsmaster:jobs', json_encode(['job_id'=>$jobId, 'type'=>$type, 'payload'=>$payload]));
        } catch (Exception $e) {
            // fallback: do nothing
        }
    }
    return $jobId;
}

/* Actions */
if ($action === 'toggle_privacy') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }
    // verify ownership (via channels)
    $stmt = $pdo->prepare("SELECT m.id FROM media m JOIN channels c ON m.channel_id = c.id WHERE m.id = :mid AND c.owner_id = :uid LIMIT 1");
    $stmt->execute([':mid'=>$media_id, ':uid'=>$user['id']]);
    if (!$stmt->fetch()) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    // toggle privacy column (assume privacy column exists: 'privacy' values public/private)
    $stmt = $pdo->prepare("UPDATE media SET privacy = CASE WHEN COALESCE(privacy,'public') = 'public' THEN 'private' ELSE 'public' END WHERE id = :id");
    $stmt->execute([':id'=>$media_id]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'delete') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }
    // owner or moderator can delete (soft delete recommended but here we soft-delete)
    $stmt = $pdo->prepare("SELECT m.id, c.owner_id FROM media m JOIN channels c ON m.channel_id = c.id WHERE m.id = :mid LIMIT 1");
    $stmt->execute([':mid'=>$media_id]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    $is_mod = (int)($user['is_moderator'] ?? 0);
    if ($row['owner_id'] != $user['id'] && !$is_mod) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }
    // soft delete
    $pdo->prepare("UPDATE media SET is_archived = 1 WHERE id = :id")->execute([':id'=>$media_id]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'enqueue_hls') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }
    // verify existence and ownership/moderator
    $stmt = $pdo->prepare("SELECT m.id, m.file_url, c.owner_id FROM media m LEFT JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
    $stmt->execute([':id'=>$media_id]);
    $m = $stmt->fetch();
    if (!$m) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    $is_mod = (int)($user['is_moderator'] ?? 0);
    if ($m['owner_id'] != $user['id'] && !$is_mod) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }
    // Enqueue HLS job. payload includes media_id and file path
    $payload = ['media_id'=>$media_id, 'file_url'=>$m['file_url']];
    $jobId = enqueue_job($pdo, 'hls', $payload);
    echo json_encode(['ok'=>1,'job_id'=>$jobId]); exit;
}

if ($action === 'enqueue_thumbnail') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }
    $stmt = $pdo->prepare("SELECT m.id, m.file_url, c.owner_id FROM media m LEFT JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
    $stmt->execute([':id'=>$media_id]);
    $m = $stmt->fetch();
    if (!$m) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    $is_mod = (int)($user['is_moderator'] ?? 0);
    if ($m['owner_id'] != $user['id'] && !$is_mod) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }
    $payload = ['media_id'=>$media_id,'file_url'=>$m['file_url']];
    $jobId = enqueue_job($pdo,'thumbnail',$payload);
    echo json_encode(['ok'=>1,'job_id'=>$jobId]); exit;
}

if ($action === 'edit_meta') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }
    $stmt = $pdo->prepare("SELECT m.id, c.owner_id FROM media m LEFT JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
    $stmt->execute([':id'=>$media_id]);
    $m = $stmt->fetch();
    if (!$m) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    if ($m['owner_id'] != $user['id'] && !(int)($user['is_moderator'] ?? 0)) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $fields = []; $params = [':id'=>$media_id];
    if ($title !== '') { $fields[] = "title = :title"; $params[':title'] = $title; }
    if ($desc !== '') { $fields[] = "description = :desc"; $params[':desc'] = $desc; }
    if ($tags !== '') { $fields[] = "tags = :tags"; $params[':tags'] = $tags; }
    if ($fields) {
        $sql = "UPDATE media SET ".implode(',', $fields)." WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
    }
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'add_to_playlist') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $playlist_id = (int)($_POST['playlist_id'] ?? 0);
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$playlist_id || !$media_id) { echo json_encode(['ok'=>0,'error'=>'missing']); exit; }
    // ensure playlist belongs to user
    $stmt = $pdo->prepare("SELECT id FROM playlists WHERE id = :id AND owner_id = :uid LIMIT 1");
    $stmt->execute([':id'=>$playlist_id,':uid'=>$user['id']]);
    if (!$stmt->fetch()) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    $pdo->prepare("INSERT IGNORE INTO playlist_items (playlist_id, media_id, created_at) VALUES (:p,:m,:ts)")
        ->execute([':p'=>$playlist_id,':m'=>$media_id,':ts'=>date('Y-m-d H:i:s')]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'remove_from_playlist') {
    km_csrf_check();
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    $playlist_id = (int)($_POST['playlist_id'] ?? 0);
    $media_id = (int)($_POST['media_id'] ?? 0);
    if (!$playlist_id || !$media_id) { echo json_encode(['ok'=>0,'error'=>'missing']); exit; }
    $stmt = $pdo->prepare("SELECT id FROM playlists WHERE id = :id AND owner_id = :uid LIMIT 1");
    $stmt->execute([':id'=>$playlist_id,':uid'=>$user['id']]);
    if (!$stmt->fetch()) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    $pdo->prepare("DELETE FROM playlist_items WHERE playlist_id = :p AND media_id = :m")->execute([':p'=>$playlist_id,':m'=>$media_id]);
    echo json_encode(['ok'=>1]); exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);