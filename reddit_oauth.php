<?php
// reddit_oauth.php (stub)
// This file demonstrates the OAuth start/finish flow for Reddit integration.
// For production, store client_id/secret in env and implement proper state & token persistence.

session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/api_ext.php';
require_login();
$user = current_user();

$client_id = getenv('REDDIT_CLIENT_ID') ?: 'REDDIT_CLIENT_ID';
$client_secret = getenv('REDDIT_CLIENT_SECRET') ?: 'REDDIT_CLIENT_SECRET';
$redirect_uri = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/reddit_oauth.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'start') {
    $channel_id = (int)($_GET['channel_id'] ?? 0);
    if (!$channel_id) { echo "Missing channel_id"; exit; }
    $state = bin2hex(random_bytes(8));
    $_SESSION['reddit_state'] = $state;
    $_SESSION['reddit_channel_id'] = $channel_id;
    $scope = 'read,submit';
    $url = "https://www.reddit.com/api/v1/authorize?client_id=".urlencode($client_id)."&response_type=code&state=".urlencode($state)."&redirect_uri=".urlencode($redirect_uri)."&duration=permanent&scope=".urlencode($scope);
    header('Location: ' . $url);
    exit;
} elseif (isset($_GET['code'])) {
    // callback
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    if ($state !== ($_SESSION['reddit_state'] ?? '')) {
        echo "Invalid state"; exit;
    }
    // Exchange token (example, without error handling)
    $ch = curl_init('https://www.reddit.com/api/v1/access_token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ]);
    $res = curl_exec($ch);
    $json = json_decode($res,true);
    // NOTE: in production persist $json['access_token'] and use to create thread or post.
    // For now create a reddit_integrations record with a placeholder subreddit
    $channel_id = (int)($_SESSION['reddit_channel_id'] ?? 0);
    if ($channel_id) {
        api_link_reddit_subreddit($channel_id, 'kidsmaster_stub', null);
    }
    echo "Reddit integration stubbed. Token received (not persisted in this demo).";
    exit;
} else {
    // show simple form to start OAuth
    $channel_id = (int)($_GET['channel_id'] ?? 0);
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Link Reddit</title></head><body>
    <h1>Link Reddit (stub)</h1>
    <p>This is a demo of Reddit OAuth. Click start to begin.</p>
    <a href="/reddit_oauth.php?action=start&channel_id=<?=htmlspecialchars($channel_id)?>">Start Reddit OAuth</a>
    </body></html>
    <?php
}