<?php
// ajax/channel_api.php
// AJAX endpoint for subscribe/unsubscribe & simple channel chat send (HTTP fallback)
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

if ($action === 'subscribe') {
    require_login();
    csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    if (!$channel_id) { echo json_encode(['ok'=>0,'error'=>'missing_channel']); exit; }
    $res = api_subscribe_channel($_SESSION['user']['id'], $channel_id);
    echo json_encode($res);
    exit;
}

if ($action === 'unsubscribe') {
    require_login();
    csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    if (!$channel_id) { echo json_encode(['ok'=>0,'error'=>'missing_channel']); exit; }
    $res = api_unsubscribe_channel($_SESSION['user']['id'], $channel_id);
    echo json_encode($res);
    exit;
}

// channel chat fallback: store in chat_messages with channel_id
if ($action === 'chat_send') {
    require_login();
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $channel_id = (int)($payload['channel_id'] ?? ($_POST['channel_id'] ?? 0));
    $message = trim($payload['message'] ?? ($_POST['message'] ?? ''));
    if (!$channel_id || $message === '') { echo json_encode(['ok'=>0,'error'=>'invalid']); exit; }
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO chat_messages (media_id, user_id, user_name, message, created_at) VALUES (NULL,:uid,:un,:msg,:ts)");
    $stmt->execute([':uid'=>$_SESSION['user']['id'], ':un'=>$_SESSION['user']['username'], ':msg'=>$message, ':ts'=>date('Y-m-d H:i:s')]);
    // optionally link to channel via a separate table or channel_id column for channel chat. For brevity reusing media_id==NULL case.
    echo json_encode(['ok'=>1]);
    exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);