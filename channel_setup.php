<?php
// channel_setup.php
// UI for channel owners to enable channel-specific features & versioning
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

$channel_id = (int)($_GET['id'] ?? 0);
if (!$channel_id) { header('Location: /my_channels.php'); exit; }

// verify owner
$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $channel_id]);
$ch = $stmt->fetch();
if (!$ch) { http_response_code(404); echo "Channel not found"; exit; }
if ($ch['owner_id'] != $user['id']) { http_response_code(403); echo "Forbidden"; exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    km_csrf_check();
    $enable_chat = isset($_POST['enable_chat']) ? 1 : 0;
    $enable_live = isset($_POST['enable_live']) ? 1 : 0;
    $allow_reddit = isset($_POST['allow_reddit']) ? 1 : 0;
    $version = trim($_POST['channel_version'] ?? '1.5.0');

    $pdo->prepare("UPDATE channels SET enable_chat = :ec, enable_live = :el, allow_reddit = :ar, channel_version = :cv WHERE id = :id")
        ->execute([':ec'=>$enable_chat,':el'=>$enable_live,':ar'=>$allow_reddit,':cv'=>$version,':id'=>$channel_id]);

    header('Location: channel_setup.php?id=' . $channel_id . '&saved=1');
    exit;
}

$page_title = "Channel Setup - " . $ch['name'];
require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Channel Setup — <?= km_esc($ch['name']) ?></h2>
  <?php if (isset($_GET['saved'])): ?><div class="notice">Settings saved.</div><?php endif; ?>
  <form method="post">
    <?= km_csrf_field() ?>
    <label><input type="checkbox" name="enable_chat" <?= $ch['enable_chat'] ? 'checked' : '' ?>> Enable Channel Chat (SMS-style)</label><br>
    <label><input type="checkbox" name="enable_live" <?= $ch['enable_live'] ? 'checked' : '' ?>> Enable Live Streams</label><br>
    <label><input type="checkbox" name="allow_reddit" <?= $ch['allow_reddit'] ? 'checked' : '' ?>> Allow Reddit Integration</label><br>
    <label>Channel Version<br><input name="channel_version" value="<?= km_esc($ch['channel_version'] ?? '1.5.0') ?>"></label><br>
    <button class="btn" type="submit">Save</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>