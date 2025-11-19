<?php
// includes/auth.php
// Simple auth helpers: register, login, require_login(), current_user()
// IMPORTANT: integrate with your session management and add rate-limiting / email verification for production.

require_once __DIR__ . '/../api.php';
require_once __DIR__ . '/csrf.php';

function password_hash_safe($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function auth_register($username, $email, $password) {
    $pdo = db();
    // Basic validation
    if (!preg_match('/^[A-Za-z0-9_\.]{3,30}$/', $username)) {
        return ['ok'=>0,'error'=>'Invalid username'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok'=>0,'error'=>'Invalid email'];
    }
    if (strlen($password) < 8) {
        return ['ok'=>0,'error'=>'Password too short'];
    }

    // Check exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
    $stmt->execute([':u'=>$username,':e'=>$email]);
    if ($stmt->fetch()) return ['ok'=>0,'error'=>'User exists'];

    $hash = password_hash_safe($password);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, last_seen, created_at) VALUES (:u,:e,:p, :ls, :ca)");
    $now = date('Y-m-d H:i:s');
    $stmt->execute([':u'=>$username,':e'=>$email,':p'=>$hash,':ls'=>$now,':ca'=>$now]);
    $id = $pdo->lastInsertId();

    // create default channel
    $cstmt = $pdo->prepare("INSERT INTO channels (owner_id, name, created_at) VALUES (:owner, :name, :ca)");
    $cstmt->execute([':owner'=>$id, ':name'=>$username, ':ca'=>$now]);

    $_SESSION['user'] = ['id'=>$id,'username'=>$username,'email'=>$email];
    return ['ok'=>1,'user_id'=>$id];
}

function auth_login($usernameOrEmail, $password) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE username = :u OR email = :u LIMIT 1");
    $stmt->execute([':u'=>$usernameOrEmail]);
    $u = $stmt->fetch();
    if (!$u) return ['ok'=>0,'error'=>'User not found'];
    if (!password_verify($password, $u['password_hash'])) return ['ok'=>0,'error'=>'Wrong password'];

    // update last_seen
    $now = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE users SET last_seen = :ls WHERE id = :id")->execute([':ls'=>$now,':id'=>$u['id']]);

    $_SESSION['user'] = ['id'=>$u['id'],'username'=>$u['username'],'email'=>$u['email']];
    return ['ok'=>1,'user'=>$_SESSION['user']];
}

function require_login() {
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['ok'=>0,'error'=>'authentication_required']);
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}