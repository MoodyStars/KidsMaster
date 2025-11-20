<?php
// ajax/admin_jobs_api.php
// Admin AJAX actions to manage encoding_jobs: view, retry, requeue, cancel, delete.
// Requires moderator privileges.

require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

km_require_login();
$user = km_current_user();
if (empty($user['is_moderator']) && $user['id'] != 1) {
    echo json_encode(['ok'=>0,'error'=>'forbidden']); exit;
}

$pdo = km_db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? null;
$jobId = (int)($_REQUEST['job_id'] ?? 0);
if (!$action || !$jobId) { echo json_encode(['ok'=>0,'error'=>'missing']); exit; }

function push_redis($payload) {
    if (!extension_loaded('redis')) return false;
    try {
        $r = new Redis();
        $r->connect('127.0.0.1', 6379, 0.5);
        $r->rPush('kidsmaster:jobs', json_encode($payload));
        return true;
    } catch (Exception $e) { return false; }
}

if ($action === 'view') {
    $stmt = $pdo->prepare("SELECT * FROM encoding_jobs WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$jobId]);
    $row = $stmt->fetch();
    if (!$row) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    echo json_encode(['ok'=>1,'job'=>$row]); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>0,'error'=>'post_required']); exit; }
km_csrf_check();

if ($action === 'retry' || $action === 'requeue') {
    // Reset status to queued and attempts to 0, clear last_error
    $pdo->prepare("UPDATE encoding_jobs SET status='queued', attempts=0, last_error=NULL, updated_at = :ts WHERE id = :id")->execute([':ts'=>date('Y-m-d H:i:s'),':id'=>$jobId]);
    // push to redis
    $stmt = $pdo->prepare("SELECT id, job_type, payload FROM encoding_jobs WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$jobId]);
    $row = $stmt->fetch();
    if ($row) {
        $payload = ['job_id'=>$row['id'],'type'=>$row['job_type'],'payload'=>json_decode($row['payload'], true)];
        @push_redis($payload);
    }
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'cancel') {
    // mark failed and set last_error to canceled
    $pdo->prepare("UPDATE encoding_jobs SET status='failed', last_error = :err, updated_at = :ts WHERE id = :id")->execute([':err'=>'canceled_by_admin',':ts'=>date('Y-m-d H:i:s'),':id'=>$jobId]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM encoding_jobs WHERE id = :id")->execute([':id'=>$jobId]);
    echo json_encode(['ok'=>1]); exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);