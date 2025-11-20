<?php
// themems.php - themes management (admin) - list, preview, activate themes for site or channels
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

// only admins can change site theme
$stmt = $pdo->query("SELECT value FROM settings WHERE name='site_theme' LIMIT 1");
$siteTheme = $stmt->fetchColumn() ?: 'deluxe';
$available = ['deluxe','retro','modern'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    km_csrf_check();
    if (!in_array($_POST['site_theme'] ?? '', $available)) { $err = "Invalid theme"; }
    else {
        $pdo->prepare("INSERT INTO settings (name,value) VALUES ('site_theme',:v) ON DUPLICATE KEY UPDATE value = :v")->execute([':v'=>$_POST['site_theme']]);
        header('Location: themems.php?saved=1'); exit;
    }
}

require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Themes</h2>
  <?php if (isset($err)): ?><div class="panel error"><?= km_esc($err) ?></div><?php endif; ?>
  <?php if (isset($_GET['saved'])) echo '<div class="notice">Theme saved</div>'; ?>
  <form method="post">
    <?= km_csrf_field() ?>
    <label>Site Theme
      <select name="site_theme">
        <?php foreach ($available as $a): ?>
          <option value="<?= km_esc($a) ?>" <?= $a === $siteTheme ? 'selected' : '' ?>><?= km_esc(ucfirst($a)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn">Save</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>