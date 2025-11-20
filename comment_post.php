<?php
// comment_post.php
// AJAX endpoint to post threaded comments, report and moderation delete
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/api_ext.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'post');

if ($action === 'post') {
    require_login();
    csrf_check();
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $media_id = (int)($payload['media_id'] ?? 0);
    $body = trim($payload['body'] ?? '');
    $parent = isset($payload['parent_id']) ? (int)$payload['parent_id'] : null;
    if (!$media_id || $body === '') { echo json_encode(['ok'=>0,'error'=>'invalid']); exit; }
    $user = current_user();
    $commentId = api_post_comment($media_id, $user['id'], $user['username'], $body, $parent);
    echo json_encode(['ok'=>1,'id'=>$commentId,'author'=>$user['username'],'created_at'=>date('c')]);
    exit;
}

if ($action === 'report') {
    require_login();
    csrf_check();
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $comment_id = (int)($payload['comment_id'] ?? 0);
    $reason = $payload['reason'] ?? null;
    $notes = $payload['notes'] ?? null;
    if (!$comment_id) { echo json_encode(['ok'=>0,'error'=>'invalid']); exit; }
    $user = current_user();
    $r = api_report_comment($user['id'], $comment_id, $reason, $notes);
    echo json_encode($r);
    exit;
}

if ($action === 'delete') {
    require_login();
    csrf_check();
    // Only moderators or owners of the comment may delete
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $comment_id = (int)($payload['comment_id'] ?? 0);
    if (!$comment_id) { echo json_encode(['ok'=>0,'error'=>'invalid']); exit; }
    // check moderator flag or media/channel owner (simplified)
    $pdo = db();
    $stmt = $pdo->prepare("SELECT c.user_id, m.channel_id FROM comments c LEFT JOIN media m ON c.media_id = m.id WHERE c.id = :id LIMIT 1");
    $stmt->execute([':id'=>$comment_id]);
    $row = $stmt->fetch();
    $user = current_user();
    if (!$row) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    $is_mod = (int)($user['is_moderator'] ?? 0);
    $is_owner = ($row['user_id'] == $user['id']);
    // channel owner check
    $chOwnerCheck = false;
    if ($row['channel_id']) {
        $c = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :cid LIMIT 1");
        $c->execute([':cid'=>$row['channel_id']]);
        $ch = $c->fetch();
        if ($ch && $ch['owner_id'] == $user['id']) $chOwnerCheck = true;
    }
    if (!$is_mod && !$is_owner && !$chOwnerCheck) {
        echo json_encode(['ok'=>0,'error'=>'not_authorized']); exit;
    }
    $r = api_delete_comment($comment_id, $user['id']);
    echo json_encode($r);
    exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);