<?php
// _includes/init.php
// Central bootstrap for KidsMaster - DB, session, autoload, helpers, security headers.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic error display for dev (turn off in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database helper (reuse earlier api.php db() if present)
function km_db() {
    static $pdo;
    if ($pdo) return $pdo;
    $host = getenv('KM_DB_HOST') ?: '127.0.0.1';
    $db   = getenv('KM_DB_NAME') ?: 'kidsmaster';
    $user = getenv('KM_DB_USER') ?: 'km_user';
    $pass = getenv('KM_DB_PASS') ?: 'km_pass';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $opts = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
    $pdo = new PDO($dsn, $user, $pass, $opts);
    return $pdo;
}

// Minimal auth helpers (expects includes/auth.php to extend)
function km_current_user() {
    return $_SESSION['user'] ?? null;
}

function km_require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: /login.php');
        exit;
    }
}

// CSRF token helpers
if (!function_exists('km_csrf_token')) {
    function km_csrf_token() {
        if (empty($_SESSION['_km_csrf'])) $_SESSION['_km_csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['_km_csrf'];
    }
    function km_csrf_field() {
        return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(km_csrf_token()).'">';
    }
    function km_csrf_check() {
        $posted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$posted || !hash_equals($_SESSION['_km_csrf'] ?? '', $posted)) {
            http_response_code(403);
            echo json_encode(['ok'=>0,'error'=>'invalid_csrf']);
            exit;
        }
    }
}

// Simple sanitization helper
function km_esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Load optional helpers
$inc = __DIR__ . '/../includes/';
if (file_exists($inc . 'auth.php')) require_once $inc . 'auth.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');