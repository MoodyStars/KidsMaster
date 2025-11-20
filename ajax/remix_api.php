<?php
// ajax/remix_api.php
// Queue an audio remix job. Accepts media_id (source), preset, optional params.
// POST action=create media_id=... preset=... _csrf=...
// Returns job_id.

require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = km_db();
$user = km_current_user();
$action = $_REQUEST['action'] ?? null;
if ($action !== 'create') { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

km_csrf_check();
$media_id = (int)($_POST['media_id'] ?? 0);
$preset = trim($_POST['preset'] ?? 'lofi');
if (!$media_id) { echo json_encode(['ok'=>0,'error'=>'missing_media']); exit; }

$stmt = $pdo->prepare("SELECT m.id,m.file_url,c.owner_id FROM media m JOIN channels c ON m.channel_id = c.id WHERE m.id = :id LIMIT 1");
$stmt->execute([':id'=>$media_id]);
$m = $stmt->fetch();
if (!$m) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
if (($m['owner_id'] != ($user['id'] ?? 0)) && !(int)($user['is_moderator'] ?? 0)) { echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit; }

$payload = ['media_id'=>$media_id,'file_url'=>$m['file_url'],'preset'=>$preset,'requester_id'=> $user['id']];
$stmt = $pdo->prepare("INSERT INTO encoding_jobs (job_type,payload,status,attempts,created_at,updated_at) VALUES ('remix',:p,'queued',0,:ts,:ts)");
$now = date('Y-m-d H:i:s');
$stmt->execute([':p'=>json_encode($payload),':ts'=>$now]);
$jobId = (int)$pdo->lastInsertId();

if (extension_loaded('redis')) {
    try { $r = new Redis(); $r->connect('127.0.0.1',6379,0.5); $r->rPush('kidsmaster:jobs', json_encode(['job_id'=>$jobId,'type'=>'remix','payload'=>$payload])); } catch (Exception $e) {}
}

echo json_encode(['ok'=>1,'job_id'=>$jobId]);