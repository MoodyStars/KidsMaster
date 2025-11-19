<?php
// includes/csrf.php
// CSRF token helpers - use csrf_protect() at beginning of POST endpoints and validate in forms.

function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field_html() {
    $t = csrf_token();
    return '<input type="hidden" name="_csrf" value="'.htmlspecialchars($t).'">';
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['_csrf'] ?? null;
        $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$posted && $hdr) $posted = $hdr;
        if (!$posted || !hash_equals($_SESSION['_csrf_token'] ?? '', $posted)) {
            http_response_code(403);
            echo json_encode(['ok'=>0,'error'=>'invalid_csrf']);
            exit;
        }
    }
}