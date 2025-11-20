<?php
// ajax/editor_status.php
// Poll job status for a given job_id.
// GET job_id=...
// response: { ok:1, job: { id, status, attempts, last_error, updated_at } }

require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$jobId = (int)($_GET['job_id'] ?? 0);
if (!$jobId) { echo json_encode(['ok'=>0,'error'=>'missing_job']); exit; }
$pdo = km_db();
$stmt = $pdo->prepare("SELECT id, job_type, status, attempts, last_error, created_at, updated_at FROM encoding_jobs WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$jobId]);
$row = $stmt->fetch();
if (!$row) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
echo json_encode(['ok'=>1,'job'=>$row]);