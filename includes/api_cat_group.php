<?php
// includes/api_cat_group.php
// Helper functions for categories, groups, members and contact messages.

require_once __DIR__ . '/../api.php';

function api_list_categories() {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, slug, title, description FROM categories ORDER BY title ASC");
    return $stmt->fetchAll();
}

function api_get_category_by_slug($slug) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, slug, title, description FROM categories WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function api_get_media_by_category($category_id, $limit = 24, $page = 1) {
    $pdo = db();
    $offset = max(0, ($page - 1) * $limit);
    $stmt = $pdo->prepare("SELECT m.* FROM media m JOIN category_media cm ON cm.media_id = m.id WHERE cm.category_id = :cid ORDER BY m.created_at DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':cid', $category_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* Groups */
function api_create_group($owner_id, $name, $description = '', $is_public = 1) {
    $pdo = db();
    $slug = preg_replace('/[^a-z0-9\-]+/','-',strtolower(trim($name)));
    $slug = trim($slug,'-') . '-' . substr(bin2hex(random_bytes(3)),0,6);
    $stmt = $pdo->prepare("INSERT INTO `groups` (owner_id, name, slug, description, is_public, created_at) VALUES (:owner, :name, :slug, :desc, :pub, :ts)");
    $stmt->execute([':owner'=>$owner_id,':name'=>$name,':slug'=>$slug,':desc'=>$description,':pub'=>$is_public?1:0,':ts'=>date('Y-m-d H:i:s')]);
    $gid = $pdo->lastInsertId();
    // insert owner as member with role owner
    $m = $pdo->prepare("INSERT INTO group_members (group_id, user_id, role, joined_at) VALUES (:gid,:uid,'owner',:ts)");
    $m->execute([':gid'=>$gid,':uid'=>$owner_id,':ts'=>date('Y-m-d H:i:s')]);
    return $gid;
}

function api_get_group($group_id_or_slug) {
    $pdo = db();
    if (is_numeric($group_id_or_slug)) {
        $stmt = $pdo->prepare("SELECT * FROM `groups` WHERE id = :id LIMIT 1");
        $stmt->execute([':id'=>$group_id_or_slug]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM `groups` WHERE slug = :s LIMIT 1");
        $stmt->execute([':s'=>$group_id_or_slug]);
    }
    return $stmt->fetch() ?: null;
}

function api_list_groups($limit=24, $page=1) {
    $pdo = db();
    $offset = max(0, ($page - 1) * $limit);
    $stmt = $pdo->prepare("SELECT g.*, u.username AS owner_name FROM `groups` g LEFT JOIN users u ON g.owner_id = u.id ORDER BY g.created_at DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function api_join_group($group_id, $user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role, joined_at) VALUES (:g,:u,'member',:ts)");
    $stmt->execute([':g'=>$group_id,':u'=>$user_id,':ts'=>date('Y-m-d H:i:s')]);
    return ['ok'=>1];
}

function api_leave_group($group_id, $user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = :g AND user_id = :u");
    $stmt->execute([':g'=>$group_id,':u'=>$user_id]);
    return ['ok'=>1];
}

function api_group_members($group_id, $limit=100) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT gm.*, u.username, u.avatar FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = :g ORDER BY gm.joined_at ASC LIMIT :lim");
    $stmt->bindValue(':g', $group_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/* Contact messages */
function api_contact_submit($user_id, $name, $email, $subject, $body) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, name, email, subject, body, created_at) VALUES (:uid,:name,:email,:subject,:body,:ts)");
    $stmt->execute([':uid'=>$user_id,':name'=>$name,':email'=>$email,':subject'=>$subject,':body'=>$body,':ts'=>date('Y-m-d H:i:s')]);
    return $pdo->lastInsertId();
}

function api_list_group_media($group_id, $limit=24) {
    // For now, return recent media by group members (simple heuristic)
    $pdo = db();
    $stmt = $pdo->prepare("SELECT m.* FROM media m JOIN group_members gm ON gm.user_id = m.channel_id OR gm.user_id = m.channel_id WHERE gm.group_id = :gid ORDER BY m.created_at DESC LIMIT :lim");
    $stmt->bindValue(':gid', $group_id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}