<?php
// channels.php - updated channel listing and archive filter, search and category support
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';

$pdo = km_db();
$q = trim($_GET['q'] ?? '');
$archived = isset($_GET['archived']) ? 1 : 0;
$params = [];
$where = 'WHERE 1=1';
if ($q) { $where .= ' AND (name LIKE :q OR description LIKE :q)'; $params[':q'] = '%'.$q.'%'; }
$where .= $archived ? ' AND archived = 1' : ' AND archived = 0';

$stmt = $pdo->prepare("SELECT * FROM channels $where ORDER BY subscribers DESC LIMIT 200");
$stmt->execute($params);
$channels = $stmt->fetchAll();
?>
<section class="panel">
  <h2>Channels <?= $archived ? '(Archived)' : '' ?></h2>
  <form method="get" style="margin-bottom:12px;">
    <input name="q" value="<?= km_esc($q) ?>" placeholder="Search channels">
    <label><input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Show archived</label>
    <button class="btn">Search</button>
  </form>

  <?php if (!$channels) echo '<p>No channels found.</p>'; ?>
  <div class="channel-list">
    <?php foreach ($channels as $c): ?>
      <div class="channel-item panel">
        <div style="display:flex;align-items:center;gap:12px;">
          <?php if (!empty($c['profile_pic'])): ?><img src="<?= km_esc($c['profile_pic'])?>" width="88" class="channel-avatar"><?php else: ?><div class="channel-avatar" style="width:88px;height:88px;background:#eee;border-radius:50%;"></div><?php endif; ?>
          <div>
            <h3><a href="/channel.php?id=<?= (int)$c['id'] ?>"><?= km_esc($c['name']) ?></a></h3>
            <div class="km-retro-small"><?= km_esc($c['description'] ?? '') ?></div>
            <div><small><?= (int)$c['subscribers'] ?> subscribers</small></div>
          </div>
          <div style="margin-left:auto;">
            <a class="btn" href="/channel.php?id=<?= (int)$c['id'] ?>">View</a>
            <?php if ($c['owner_id'] == (km_current_user()['id'] ?? 0)): ?>
              <a class="btn ghost" href="/channel_edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>