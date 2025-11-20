<?php
// groups.php - list groups, create new (if logged in)
session_start();
require_once __DIR__ . '/includes/api_cat_group.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$currentUser = current_user();
$groups = api_list_groups(48, 1);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Groups — KidsMaster</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/channel.css" />
  <script>window.KM_USER = <?=json_encode($currentUser ?? null)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/groups.js"></script>
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>

  <main class="container">
    <h1>Groups</h1>

    <?php if ($currentUser): ?>
      <section class="panel">
        <h3>Create a Group</h3>
        <form id="createGroupForm" method="post" action="/ajax/groups_api.php?action=create">
          <?= csrf_field_html() ?>
          <input type="text" name="name" placeholder="Group name" required>
          <br><textarea name="description" placeholder="Describe your group"></textarea>
          <br><label><input type="checkbox" name="is_public" checked> Public group</label>
          <br><button class="btn" type="submit">Create Group</button>
        </form>
      </section>
    <?php else: ?>
      <section class="panel"> <p><a href="login.php">Log in</a> to create groups and join the community.</p></section>
    <?php endif; ?>

    <section class="panel">
      <h3>Recent Groups</h3>
      <div class="thumb-grid large">
        <?php foreach ($groups as $g): ?>
          <article class="thumb">
            <div style="padding:8px;">
              <h4><a href="group.php?id=<?=htmlspecialchars($g['id'])?>"><?=htmlspecialchars($g['name'])?></a></h4>
              <small>by <?=htmlspecialchars($g['owner_name'] ?? 'unknown')?> • <?=htmlspecialchars($g['created_at'] ?? '')?></small>
              <p style="margin-top:8px;"><?=htmlspecialchars(substr($g['description'] ?? '',0,140))?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>