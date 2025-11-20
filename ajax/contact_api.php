<?php
// ajax/contact_api.php - saves contact messages to DB
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_cat_group.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? 'submit');

if ($action === 'submit') {
    // CSRF for authenticated users; for guests rely on token field still
    if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check();
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name = trim($payload['name'] ?? '');
    $email = trim($payload['email'] ?? '');
    $subject = trim($payload['subject'] ?? '');
    $body = trim($payload['body'] ?? '');
    $user = current_user();
    $uid = $user['id'] ?? null;
    if (!$body) { echo json_encode(['ok'=>0,'error'=>'empty_body']); exit; }
    $id = api_contact_submit($uid, $name, $email, $subject, $body);
    echo json_encode(['ok'=>1,'id'=>$id]);
    exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);