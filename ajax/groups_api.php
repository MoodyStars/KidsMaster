<?php
// ajax/groups_api.php - handles create, join, leave, fetch members (JSON)
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_cat_group.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'create') {
    require_login();
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $public = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 1;
    if (!$name) { echo json_encode(['ok'=>0,'error'=>'Missing name']); exit; }
    $gid = api_create_group($_SESSION['user']['id'], $name, $desc, $public);
    echo json_encode(['ok'=>1,'id'=>$gid,'redirect'=>'/group.php?id='.$gid]);
    exit;
}

if ($action === 'join') {
    require_login();
    csrf_check();
    $gid = (int)($_POST['group_id'] ?? 0);
    if (!$gid) { echo json_encode(['ok'=>0,'error'=>'Missing group']); exit; }
    $res = api_join_group($gid, $_SESSION['user']['id']);
    echo json_encode($res);
    exit;
}

if ($action === 'leave') {
    require_login();
    csrf_check();
    $gid = (int)($_POST['group_id'] ?? 0);
    if (!$gid) { echo json_encode(['ok'=>0,'error'=>'Missing group']); exit; }
    $res = api_leave_group($gid, $_SESSION['user']['id']);
    echo json_encode($res);
    exit;
}

if ($action === 'members') {
    $gid = (int)($_GET['group_id'] ?? 0);
    if (!$gid) { echo json_encode(['ok'=>0,'error'=>'Missing group']); exit; }
    $m = api_group_members($gid, 500);
    echo json_encode(['ok'=>1,'members'=>$m]);
    exit;
}

echo json_encode(['ok'=>0,'error'=>'unknown_action']);