<?php
// ajax/stats_api.php - exposes simple JSON stats for client dashboards (storage, most viewed, recent uploads)
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../api.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
$user = current_user();
$pdo = db();

if (!isset($_GET['op'])) {
    echo json_encode(['ok'=>0,'error'=>'missing_op']); exit;
}

$op = $_GET['op'];

if ($op === 'storage') {
    $usage = api_get_user_storage_usage($user['id']);
    echo json_encode(['ok'=>1,'usage'=>$usage,'quota'=>549755813888]); exit;
}

if ($op === 'most_viewed') {
    $stmt = $pdo->prepare("SELECT m.id,m.title,m.views,m.thumbnail FROM media m JOIN channels c ON m.channel_id = c.id WHERE c.owner_id = :uid ORDER BY m.views DESC LIMIT 12");
    $stmt->execute([':uid'=>$user['id']]);
    echo json_encode(['ok'=>1,'items'=>$stmt->fetchAll()]); exit;
}

if ($op === 'recent_uploads') {
    $stmt = $pdo->prepare("SELECT m.id,m.title,m.created_at,m.thumbnail FROM media m JOIN channels c ON m.channel_id = c.id WHERE c.owner_id = :uid ORDER BY m.created_at DESC LIMIT 12");
    $stmt->execute([':uid'=>$user['id']]);
    echo json_encode(['ok'=>1,'items'=>$stmt->fetchAll()]); exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_op']);