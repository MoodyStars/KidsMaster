<?php
// group.php - view a group, members, join/leave
session_start();
require_once __DIR__ . '/includes/api_cat_group.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$gid = $_GET['id'] ?? ($_GET['slug'] ?? null);
if (!$gid) { header('Location: groups.php'); exit; }
$group = api_get_group($gid);
if (!$group) { http_response_code(404); echo "Group not found"; exit; }

$currentUser = current_user();
$members = api_group_members($group['id'], 200);
$is_member = false;
if ($currentUser) {
    foreach ($members as $m) if ($m['user_id'] == $currentUser['id']) { $is_member = true; break; }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title><?=htmlspecialchars($group['name'])?> — Group — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/channel.css" />
  <script>window.KM_USER = <?=json_encode($currentUser ?? null)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/groups.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>

  <main class="container">
    <section class="panel">
      <h1><?=htmlspecialchars($group['name'])?></h1>
      <p><?=nl2br(htmlspecialchars($group['description'] ?? ''))?></p>
      <div>
        <?php if ($currentUser): ?>
          <button id="joinBtn" data-group="<?=htmlspecialchars($group['id'])?>" data-member="<?= $is_member ? '1' : '0' ?>"><?= $is_member ? 'Leave Group' : 'Join Group' ?></button>
        <?php else: ?>
          <a href="login.php" class="btn">Log in to join</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel">
      <h3>Members (<?=count($members)?>)</h3>
      <ul>
        <?php foreach ($members as $m): ?>
          <li><?=htmlspecialchars($m['username'])?> <?php if ($m['role']!=='member') echo '('.htmlspecialchars($m['role']).')'; ?></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <section class="panel">
      <h3>Group Activity</h3>
      <p>Recent posts and media by members will appear here (future enhancement).</p>
    </section>
  </main>
</body>
</html>