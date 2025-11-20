<?php
// ajax/channel_api.php (updated)
// Handles subscribes/unsubscribes, channel chat posts with persistence and optional Redis pub/sub,
// archive/restore and reddit stub. Writes to DB and publishes to Redis so WebSocket servers can broadcast.
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../api.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if (!$action) { echo json_encode(['ok'=>0,'error'=>'missing_action']); exit; }

function redis_pub($channel, $message) {
    if (!extension_loaded('redis')) return false;
    try {
        $r = new Redis();
        $r->connect('127.0.0.1', 6379, 0.5);
        $r->publish($channel, $message);
        return true;
    } catch (Exception $e) { return false; }
}

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

// channel chat fallback: store in chat_messages with channel_id and metadata and publish to redis
if ($action === 'chat_send') {
    require_login();
    // Accept JSON body or form
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $channel_id = (int)($payload['channel_id'] ?? ($_POST['channel_id'] ?? 0));
    $message = trim($payload['message'] ?? ($_POST['message'] ?? ''));
    $country = trim($payload['country'] ?? ($_POST['country'] ?? ''));
    $avatar = trim($payload['avatar'] ?? ($_POST['avatar'] ?? ''));
    if (!$channel_id || $message === '') { echo json_encode(['ok'=>0,'error'=>'invalid']); exit; }

    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO chat_messages (channel_id, media_id, user_id, user_name, user_avatar, country_code, message, created_at) VALUES (:cid, NULL, :uid, :un, :ua, :cc, :msg, :ts)");
    $stmt->execute([
        ':cid' => $channel_id,
        ':uid' => $_SESSION['user']['id'],
        ':un'  => $_SESSION['user']['username'],
        ':ua'  => $avatar ?: ($_SESSION['user']['avatar'] ?? null),
        ':cc'  => $country ?: null,
        ':msg' => substr($message,0,1000),
        ':ts'  => date('Y-m-d H:i:s')
    ]);
    $insertId = (int)$pdo->lastInsertId();

    // Build payload for broadcast
    $out = json_encode([
        'id' => $insertId,
        'channel_id' => $channel_id,
        'user_id' => $_SESSION['user']['id'],
        'user_name' => $_SESSION['user']['username'],
        'user_avatar' => $avatar ?: ($_SESSION['user']['avatar'] ?? null),
        'country_code' => $country ?: null,
        'message' => $message,
        'created_at' => date('c')
    ]);

    // publish to redis so ws servers can broadcast
    @redis_pub('kidsmaster:channel_chat', $out);

    echo json_encode(['ok'=>1, 'id' => $insertId]);
    exit;
}

// Archive / Restore channel (owner only)
if ($action === 'archive_channel') {
    require_login();
    csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    if (!$channel_id) { echo json_encode(['ok'=>0,'error'=>'missing_channel']); exit; }
    $user = current_user();
    $pdo = db();
    $stmt = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$channel_id]);
    $ch = $stmt->fetch();
    if (!$ch) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    if ($ch['owner_id'] != $user['id']) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    $pdo->prepare("UPDATE channels SET archived = 1 WHERE id = :id")->execute([':id'=>$channel_id]);
    echo json_encode(['ok'=>1]);
    exit;
}

if ($action === 'restore_channel') {
    require_login();
    csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    if (!$channel_id) { echo json_encode(['ok'=>0,'error'=>'missing_channel']); exit; }
    $user = current_user();
    $pdo = db();
    $stmt = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$channel_id]);
    $ch = $stmt->fetch();
    if (!$ch) { echo json_encode(['ok'=>0,'error'=>'not_found']); exit; }
    if ($ch['owner_id'] != $user['id']) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    $pdo->prepare("UPDATE channels SET archived = 0 WHERE id = :id")->execute([':id'=>$channel_id]);
    echo json_encode(['ok'=>1]);
    exit;
}

// Reddit-chat stub: return a URL or instructions for embedding (real integration requires OAuth)
if ($action === 'reddit_stub') {
    $channel = (int)($_GET['channel_id'] ?? ($_POST['channel_id'] ?? 0));
    $subreddit = 'r/kidsmaster_' . ($channel ?: 'global');
    echo json_encode(['ok'=>1,'embed_url'=>"https://www.reddit.com/$subreddit"]);
    exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);