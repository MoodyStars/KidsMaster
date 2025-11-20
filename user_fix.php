<?php
// user_fix.php - admin utilities to fix common user issues (recalculate storage, reset avatars)
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

// require moderator/admin
if (empty($user['is_moderator']) && ($user['id'] != 1)) { http_response_code(403); echo "Forbidden"; exit; }

$action = $_GET['action'] ?? null;
if ($action === 'recalc_storage') {
    // recompute storage per user from storage_files table
    $stmt = $pdo->query("SELECT owner_id, SUM(size) as total FROM storage_files GROUP BY owner_id");
    $updates = 0;
    while ($r = $stmt->fetch()) {
        // store into users table user_storage (add column if present)
        $pdo->prepare("UPDATE users SET storage_used = :s WHERE id = :id")->execute([':s'=>$r['total'], ':id'=>$r['owner_id']]);
        $updates++;
    }
    echo "Recalculated for {$updates} users";
    exit;
}

require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Admin User Fixes</h2>
  <ul>
    <li><a href="/user_fix.php?action=recalc_storage" class="btn">Recalculate Storage Usage</a></li>
    <li><a href="/user_fix.php?action=cleanup_avatars" class="btn">Cleanup Broken Avatars (TODO)</a></li>
  </ul>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>