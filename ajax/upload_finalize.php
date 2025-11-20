<?php
// ajax/upload_finalize.php
// Minimal finalize endpoint: create media DB record using file_url produced by upload.php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../api.php';
header('Content-Type: application/json; charset=utf-8');
require_login();
csrf_check();

$payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$channel_id = (int)($payload['channel_id'] ?? 0);
$title = trim($payload['title'] ?? '');
$desc = trim($payload['description'] ?? '');
$tags = trim($payload['tags'] ?? '');
$file_url = trim($payload['file_url'] ?? '');
$thumbnail = trim($payload['thumbnail'] ?? '');
$mime = '';

if (!$channel_id || !$file_url) { echo json_encode(['ok'=>0,'error'=>'missing']); exit; }

$pdo = db();
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("INSERT INTO media (channel_id, title, description, type, category, tags, thumbnail, file_url, mime, duration, views, created_at, updated_at) VALUES (:cid,:title,:desc,:type,:cat,:tags,:thumb,:file,:mime, NULL, 0, :now, :now)");
$type = 'storage';
$cat = 'storage';
$stmt->execute([
  ':cid'=>$channel_id, ':title'=>$title, ':desc'=>$desc, ':type'=>$type, ':cat'=>$cat, ':tags'=>$tags, ':thumb'=>$thumbnail, ':file'=>$file_url, ':mime'=>$mime, ':now'=>$now
]);
$id = $pdo->lastInsertId();
echo json_encode(['ok'=>1,'id'=>$id]);