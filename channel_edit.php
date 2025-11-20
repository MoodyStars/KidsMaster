<?php
// channel_edit.php (updated to allow change background and offer GIF banner preview)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/api_ext.php';
require_login();
$user = current_user();

$channel_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$channel_id) { header('Location: index.php'); exit; }

$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM channels WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$channel_id]);
$channel = $stmt->fetch();
if (!$channel) { http_response_code(404); echo "Channel not found"; exit; }
if ($channel['owner_id'] != $user['id']) { http_response_code(403); echo "Not authorized"; exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $updates = ['name'=>$name, 'description'=>$desc];

    // profile pic
    if (!empty($_FILES['profile_pic']['tmp_name'])) {
        $f = $_FILES['profile_pic'];
        if ($f['size'] > 5*1024*1024) { $errors[] = "Profile pic too large"; }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/uploads/channel_pp_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = __DIR__ . $safe;
            @mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($f['tmp_name'], $dest)) $updates['profile_pic'] = $safe;
            else $errors[] = "Failed to save profile picture";
        }
    }

    // banner/gif
    if (!empty($_FILES['banner']['tmp_name'])) {
        $f = $_FILES['banner'];
        if ($f['size'] > 30*1024*1024) { $errors[] = "Banner too large"; }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/uploads/channel_banner_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = __DIR__ . $safe;
            @mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                if ($ext === 'gif') $updates['gif_banner'] = $safe;
                else $updates['banner'] = $safe;
            } else $errors[] = "Failed to save banner";
        }
    }

    // background
    if (!empty($_FILES['background']['tmp_name'])) {
        $f = $_FILES['background'];
        if ($f['size'] > 20*1024*1024) { $errors[] = "Background too large"; }
        else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $safe = '/storage/uploads/channel_bg_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $dest = __DIR__ . $safe;
            @mkdir(dirname($dest), 0755, true);
            if (move_uploaded_file($f['tmp_name'], $dest)) $updates['background'] = $safe;
            else $errors[] = "Failed to save background";
        }
    }

    if (!$errors) {
        $res = api_update_channel_media($channel_id, $user['id'], $updates);
        if ($res['ok']) {
            header('Location: channel.php?id=' . $channel_id);
            exit;
        } else $errors[] = $res['error'] ?? 'Failed to update';
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Edit Channel — <?=htmlspecialchars($channel['name'])?></title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/channel.css" />
</head>
<body class="km-body">
  <header class="km-header"><div class="km-brand"><a href="index.php">KidsMaster</a></div></header>

  <main class="container">
    <h1>Edit Channel</h1>
    <?php if ($errors): ?>
      <div class="panel error"><ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field_html() ?>
      <div class="panel">
        <label>Channel Name<br><input name="name" value="<?=htmlspecialchars($_POST['name'] ?? $channel['name'])?>" required></label>
        <label>Description<br><textarea name="description"><?=htmlspecialchars($_POST['description'] ?? $channel['description'])?></textarea></label>

        <label>Profile Picture (max 5MB)<br><input type="file" name="profile_pic" accept="image/*"></label>

        <label>Banner (image or GIF) (max 30MB)<br><input type="file" name="banner" accept="image/*,image/gif"></label>
        <?php if (!empty($channel['gif_banner'])): ?>
          <div>Current GIF Banner:<br><img src="<?=htmlspecialchars($channel['gif_banner'])?>" style="max-width:100%"></div>
        <?php elseif (!empty($channel['banner'])): ?>
          <div>Current Banner:<br><img src="<?=htmlspecialchars($channel['banner'])?>" style="max-width:100%"></div>
        <?php endif; ?>

        <label>Background (used on channel page) (max 20MB)<br><input type="file" name="background" accept="image/*"></label>
        <?php if (!empty($channel['background'])): ?>
          <div>Current Background:<br><img src="<?=htmlspecialchars($channel['background'])?>" style="max-width:100%"></div>
        <?php endif; ?>

        <div style="margin-top:10px;">
          <button class="btn" type="submit">Save Changes</button>
          <a class="btn ghost" href="channel.php?id=<?=htmlspecialchars($channel_id)?>">Cancel</a>
        </div>
      </div>
    </form>
  </main>
</body>
</html>