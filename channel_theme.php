<?php
// channel_theme.php
// Allows owners to pick a theme (retro/modern) and change banner/profile/background.
// Handles uploads, basic validation and persists paths.

require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

$channel_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$channel_id]);
$ch = $stmt->fetch();
if (!$ch) { http_response_code(404); echo "Channel not found"; exit; }
if ($ch['owner_id'] != $user['id']) { http_response_code(403); echo "Forbidden"; exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    km_csrf_check();
    $updates = [];
    // profile pic
    if (!empty($_FILES['profile_pic']['tmp_name'])) {
        $f = $_FILES['profile_pic'];
        if ($f['size'] > 6*1024*1024) $errors[] = "Profile picture too large";
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/channel_pp_'.$channel_id.'_'.time().'.'.$ext;
            @mkdir(__DIR__.'/storage',0755,true);
            if (move_uploaded_file($f['tmp_name'], __DIR__.$safe)) $updates['profile_pic'] = $safe;
            else $errors[] = "Failed to upload profile picture";
        }
    }
    // banner/gif
    if (!empty($_FILES['banner']['tmp_name'])) {
        $f = $_FILES['banner'];
        if ($f['size'] > 40*1024*1024) $errors[] = "Banner too large";
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/channel_banner_'.$channel_id.'_'.time().'.'.$ext;
            if (move_uploaded_file($f['tmp_name'], __DIR__.$safe)) {
                if ($ext === 'gif') $updates['gif_banner'] = $safe;
                else $updates['banner'] = $safe;
            } else $errors[] = "Failed to upload banner";
        }
    }
    // background
    if (!empty($_FILES['background']['tmp_name'])) {
        $f = $_FILES['background'];
        if ($f['size'] > 20*1024*1024) $errors[] = "Background too large";
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/channel_bg_'.$channel_id.'_'.time().'.'.$ext;
            if (move_uploaded_file($f['tmp_name'], __DIR__.$safe)) $updates['background'] = $safe;
            else $errors[] = "Failed to upload background";
        }
    }
    if (isset($_POST['theme_choice'])) $updates['theme_choice'] = $_POST['theme_choice'];

    if (empty($errors) && !empty($updates)) {
        $fields = [];
        $params = [':id'=>$channel_id];
        foreach ($updates as $k=>$v) { $fields[] = "$k = :$k"; $params[":$k"] = $v; }
        $sql = "UPDATE channels SET " . implode(',', $fields) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        header('Location: channel_theme.php?id='.$channel_id.'&ok=1'); exit;
    }
}

$page_title = "Channel Theme - " . $ch['name'];
require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Channel Theme & Media — <?= km_esc($ch['name']) ?></h2>
  <?php if ($errors): ?><div class="panel error"><ul><?php foreach ($errors as $e) echo '<li>'.km_esc($e).'</li>'; ?></ul></div><?php endif; ?>
  <?php if (isset($_GET['ok'])) echo '<div class="notice">Updated.</div>'; ?>
  <form method="post" enctype="multipart/form-data">
    <?= km_csrf_field() ?>
    <label>Theme choice
      <select name="theme_choice">
        <option value="deluxe" <?= ($ch['theme_choice'] ?? '') === 'deluxe' ? 'selected' : '' ?>>Deluxe</option>
        <option value="retro" <?= ($ch['theme_choice'] ?? '') === 'retro' ? 'selected' : '' ?>>Retro (2011)</option>
        <option value="modern" <?= ($ch['theme_choice'] ?? '') === 'modern' ? 'selected' : '' ?>>Modern</option>
      </select>
    </label><br>
    <label>Profile Picture (6MB max)<br><input type="file" name="profile_pic" accept="image/*"></label><br>
    <label>Banner or GIF (40MB max)<br><input type="file" name="banner" accept="image/*,image/gif"></label><br>
    <label>Background (20MB max)<br><input type="file" name="background" accept="image/*"></label><br>
    <button class="btn">Save</button>
  </form>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>