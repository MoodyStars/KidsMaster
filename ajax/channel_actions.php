<?php
// ajax/channel_actions.php
// AJAX endpoints for channel operations: subscribe/unsubscribe, update pfp/banner, reddit link, archive/restore
require_once __DIR__ . '/../_includes/init.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = km_db();
$user = km_current_user();

if ($action === 'subscribe') {
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    km_csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    if (!$channel_id) { echo json_encode(['ok'=>0,'error'=>'missing_channel']); exit; }
    $pdo->prepare("INSERT IGNORE INTO subscriptions (user_id, channel_id, created_at) VALUES (:u,:c,:ts)")
        ->execute([':u'=>$user['id'],':c'=>$channel_id,':ts'=>date('Y-m-d H:i:s')]);
    $pdo->prepare("UPDATE channels SET subscribers = (SELECT COUNT(*) FROM subscriptions WHERE channel_id = channels.id) WHERE id = :id")->execute([':id'=>$channel_id]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'unsubscribe') {
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    km_csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    $pdo->prepare("DELETE FROM subscriptions WHERE user_id = :u AND channel_id = :c")->execute([':u'=>$user['id'],':c'=>$channel_id]);
    $pdo->prepare("UPDATE channels SET subscribers = (SELECT COUNT(*) FROM subscriptions WHERE channel_id = channels.id) WHERE id = :id")->execute([':id'=>$channel_id]);
    echo json_encode(['ok'=>1]); exit;
}

if ($action === 'update_meta') {
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    km_csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    // validate ownership
    $stmt = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$channel_id]);
    $row = $stmt->fetch();
    if (!$row || $row['owner_id'] != $user['id']) { echo json_encode(['ok'=>0,'error'=>'not_owner']); exit; }
    $updates = [];
    $params = [':id'=>$channel_id];
    if (isset($_POST['name'])) { $updates[] = 'name = :name'; $params[':name'] = substr($_POST['name'],0,150); }
    if (isset($_POST['description'])) { $updates[] = 'description = :d'; $params[':d'] = $_POST['description']; }
    if ($updates) {
        $sql = "UPDATE channels SET ".implode(',', $updates)." WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
    }
    echo json_encode(['ok'=>1]); exit;
}

// reddit stub link attach
if ($action === 'reddit_link') {
    if (!$user) { echo json_encode(['ok'=>0,'error'=>'login_required']); exit; }
    km_csrf_check();
    $channel_id = (int)($_POST['channel_id'] ?? 0);
    $sub = trim($_POST['subreddit'] ?? '');
    if (!$channel_id || !$sub) { echo json_encode(['ok'=>0,'error'=>'missing']); exit; }
    $pdo->prepare("INSERT INTO reddit_integrations (channel_id, reddit_subreddit, created_at) VALUES (:cid,:sub,:ts)")
        ->execute([':cid'=>$channel_id,':sub'=>$sub,':ts'=>date('Y-m-d H:i:s')]);
    echo json_encode(['ok'=>1,'embed_url'=>'https://www.reddit.com/r/'.rawurlencode($sub)]); exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);