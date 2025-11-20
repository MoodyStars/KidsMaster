<?php
// setup.php - initial site setup wizard (DB check, create admin user, import categories)
// Run once to initialize basic data; protects itself once setup_completed flag exists.

require_once __DIR__ . '/_includes/init.php';

$pdo = km_db();
$errors = [];
$success = false;

// simple lock file to prevent rerun
$lockFile = __DIR__ . '/storage/.setup_done';
if (file_exists($lockFile)) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // expected: admin_username, admin_email, admin_password
    $admin_user = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass = $_POST['admin_password'] ?? '';

    if (!$admin_user || !$admin_email || strlen($admin_pass) < 8) {
        $errors[] = "Please provide username, email and password (8+ chars).";
    } else {
        // create admin user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $stmt->execute([':u'=>$admin_user]);
        if ($stmt->fetch()) $errors[] = "Username already exists.";
        else {
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');
            $pdo->prepare("INSERT INTO users (username,email,password_hash,last_seen,created_at,is_moderator) VALUES (:u,:e,:p,:ls,:ca,1)")
                ->execute([':u'=>$admin_user,':e'=>$admin_email,':p'=>$hash,':ls'=>$now,':ca'=>$now]);
            $adminId = $pdo->lastInsertId();
            // default channel
            $pdo->prepare("INSERT INTO channels (owner_id,name,created_at) VALUES (:o,:n,:ts)")->execute([':o'=>$adminId,':n'=>$admin_user,':ts'=>$now]);

            // create commonly used categories if not exist (idempotent)
            $cats = [
                ['slug'=>'business','title'=>'Business'],
                ['slug'=>'cartoon','title'=>'Cartoon'],
                ['slug'=>'comedy','title'=>'Comedy'],
                ['slug'=>'family','title'=>'Family'],
                ['slug'=>'music','title'=>'Music'],
                ['slug'=>'2000s','title'=>'2000s Nostalgic'],
                ['slug'=>'kids-songs','title'=>'Kids songs'],
                ['slug'=>'games','title'=>'Games']
            ];
            $ins = $pdo->prepare("INSERT IGNORE INTO categories (slug,title,description,created_at) VALUES (:slug,:title,'',:ts)");
            foreach ($cats as $c) $ins->execute([':slug'=>$c['slug'],':title'=>$c['title'],':ts'=>$now]);

            touch($lockFile);
            $success = true;
        }
    }
}

$page_title = "Setup — KidsMaster";
require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>KidsMaster Setup</h2>
  <?php if ($errors): ?><div class="panel error"><ul><?php foreach ($errors as $e) echo "<li>".km_esc($e)."</li>"; ?></ul></div><?php endif; ?>
  <?php if ($success): ?>
    <div class="notice">Setup complete. You may now <a href="/login.php">log in</a> as the admin user.</div>
  <?php else: ?>
    <form method="post">
      <label>Admin username<br><input name="admin_username" required></label><br>
      <label>Email<br><input name="admin_email" type="email" required></label><br>
      <label>Password (8+ chars)<br><input name="admin_password" type="password" required></label><br>
      <button class="btn">Create Admin</button>
    </form>
  <?php endif; ?>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>