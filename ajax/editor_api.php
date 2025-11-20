<?php
// ajax/editor_api.php
// Endpoint to request video trim processing.
//
// POST action=trim media_id=... start=... end=... _csrf=...
// - Validates ownership or moderator privilege
// - Enqueues a 'trim' job with parameters (media_id, start, end)
// - Returns job_id for status polling
//
// Worker will produce a new media record or new file_url and update DB accordingly.

require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = km_db();
$user = km_current_user();
$action = $_REQUEST['action'] ?? null;
if ($action !== 'trim') { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

km_csrf_check();
$media_id = (int)($_POST['media_id'] ?? 0);
$start = floatval($_POST['start'] ?? 0);
$end = floatval($_POST['end'] ?? 0);
if (!$media_id || $end <= $start) { echo json_encode(['ok'=>0,'error'=>'invalid_params']); exit; }

// ownership check
$stmt = $pdo->prepare("SELECT m.id,m.file_url,c.owner_id FROM media m JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
$stmt->execute([':id'=>$media_id]);
$m = $stmt->fetch();
if (!$m) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
if (($m['owner_id'] != ($user['id'] ?? 0)) && !(int)($user['is_moderator'] ?? 0)) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }

// enqueue job
$payload = ['media_id'=>$media_id,'file_url'=>$m['file_url'],'start'=>$start,'end'=>$end,'requester_id'=>$user['id']];
$stmt = $pdo->prepare("INSERT INTO encoding_jobs (job_type,payload,status,attempts,created_at,updated_at) VALUES ('trim',:p,'queued',0,:ts,:ts)");
$now = date('Y-m-d H:i:s');
$stmt->execute([':p'=>json_encode($payload),':ts'=>$now]);
$jobId = (int)$pdo->lastInsertId();

// push to redis queue if available
if (extension_loaded('redis')) {
    try { $r = new Redis(); $r->connect('127.0.0.1',6379,0.5); $r->rPush('kidsmaster:jobs', json_encode(['job_id'=>$jobId,'type'=>'trim','payload'=>$payload])); } catch (Exception $e) {}
}

echo json_encode(['ok'=>1,'job_id'=>$jobId]);