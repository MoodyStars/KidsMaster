<?php
// analytics.php
// Lightweight event collector for front-end actions: view, play, chat_message, subscribe.
// Emits JSON and records to stats_views / analytics_events table if present.

require_once __DIR__ . '/_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

$pdo = km_db();

try {
    if ($action === 'record_view') {
        $media_id = (int)($_POST['media_id'] ?? $_GET['media_id'] ?? 0);
        $user_id = km_current_user()['id'] ?? null;
        if ($media_id) {
            $pdo->prepare("INSERT INTO stats_views (media_id,user_id,created_at) VALUES (:m,:u,:ts)")->execute([':m'=>$media_id,':u'=>$user_id,':ts'=>date('Y-m-d H:i:s')]);
            $pdo->prepare("UPDATE media SET views = views + 1 WHERE id = :id")->execute([':id'=>$media_id]);
            echo json_encode(['ok'=>1]); exit;
        }
    } elseif ($action === 'event') {
        $type = $_POST['type'] ?? $_GET['type'] ?? 'custom';
        $meta = $_POST['meta'] ?? $_GET['meta'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO analytics_events (type, meta, created_at) VALUES (:t,:m,:ts)");
        $stmt->execute([':t'=>$type, ':m'=>$meta ? json_encode($meta): null, ':ts'=>date('Y-m-d H:i:s')]);
        echo json_encode(['ok'=>1]); exit;
    }
} catch (Exception $e) {
    error_log("Analytics error: ".$e->getMessage());
    echo json_encode(['ok'=>0,'error'=>'db_error']); exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);