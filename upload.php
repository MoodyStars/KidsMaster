<?php
// upload.php
// Chunked upload endpoint with validation, per-user quota enforcement and thumbnail generation.
// POST params:
// - upload_id: unique id for this file (client generates, e.g. uuid)
// - chunk_index: current chunk index (0-based)
// - total_chunks: total number of chunks
// - filename: original file name
// - mime: optional mime
// - chunk: file data (file input named 'chunk')
// When last chunk arrives it assembles file, validates and stores it, generates thumbnail.

session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/api.php';

csrf_check();
require_login();
$user = current_user();
$uid = $user['id'];

// Config
$uploadBase = __DIR__ . '/storage/uploads';
$tmpBase = __DIR__ . '/storage/tmp';
@mkdir($uploadBase, 0755, true);
@mkdir($tmpBase, 0755, true);

// Basic inputs
$uploadId = $_POST['upload_id'] ?? null;
$chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
$totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;
$filename = $_POST['filename'] ?? 'upload.bin';
$mime = $_POST['mime'] ?? ($_FILES['chunk']['type'] ?? 'application/octet-stream');

if (!$uploadId || $chunkIndex === null || $totalChunks === null) {
    http_response_code(400);
    echo json_encode(['ok'=>0,'error'=>'missing_params']);
    exit;
}

$tmpDir = $tmpBase . '/'.$uploadId;
@mkdir($tmpDir, 0755, true);

// move chunk
if (!empty($_FILES['chunk']['tmp_name'])) {
    $chunkTmp = $_FILES['chunk']['tmp_name'];
    $dest = $tmpDir . '/chunk_'.$chunkIndex;
    if (!move_uploaded_file($chunkTmp, $dest)) {
        http_response_code(500);
        echo json_encode(['ok'=>0,'error'=>'chunk_move_failed']);
        exit;
    }
} else {
    // allow raw body for some clients
    $dest = $tmpDir . '/chunk_'.$chunkIndex;
    $data = file_get_contents('php://input');
    if ($data === false) {
        http_response_code(400);
        echo json_encode(['ok'=>0,'error'=>'no_chunk_data']);
        exit;
    }
    file_put_contents($dest, $data);
}

// If not last chunk, return progress
if ($chunkIndex < $totalChunks - 1) {
    echo json_encode(['ok'=>1,'status'=>'chunk_stored','chunk_index'=>$chunkIndex]);
    exit;
}

// Last chunk received -> assemble
$finalPath = $uploadBase . '/' . date('Y/m');
@mkdir(dirname($finalPath), 0755, true);

// Create a safe filename
$ext = pathinfo($filename, PATHINFO_EXTENSION);
$safeName = bin2hex(random_bytes(12)) . ($ext ? ('.'.$ext) : '');
$fullPath = $finalPath . '/' . $safeName;

// assemble
$out = fopen($fullPath, 'wb');
if (!$out) { http_response_code(500); echo json_encode(['ok'=>0,'error'=>'cannot_write']); exit; }
for ($i = 0; $i < $totalChunks; $i++) {
    $part = $tmpDir . '/chunk_'.$i;
    if (!file_exists($part)) {
        fclose($out);
        http_response_code(400);
        echo json_encode(['ok'=>0,'error'=>"missing_chunk_$i"]);
        exit;
    }
    $in = fopen($part, 'rb');
    stream_copy_to_stream($in, $out);
    fclose($in);
}
fclose($out);

// validate file type (simple)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected = finfo_file($finfo, $fullPath);
finfo_close($finfo);

// Allowed types lists (extend)
$allowedVideo = ['video/mp4','video/webm','video/ogg'];
$allowedAudio = ['audio/mpeg','audio/ogg','audio/wav'];
$allowedImage = ['image/jpeg','image/png','image/gif'];
$allowedSoftware = ['application/zip','application/x-zip-compressed','application/octet-stream'];

$category = 'storage';
if (in_array($detected, $allowedVideo)) $category = 'video';
elseif (in_array($detected, $allowedAudio)) $category = 'audio';
elseif (in_array($detected, $allowedImage)) $category = 'image';
elseif (in_array($detected, $allowedSoftware)) $category = 'software';

// Check per-user storage quota (example quota 512GB = 549755813888 bytes)
$quotaBytes = 549755813888;
$currentUsage = api_get_user_storage_usage($uid);
$fileSize = filesize($fullPath);
if (($currentUsage + $fileSize) > $quotaBytes) {
    // cleanup assembled file
    unlink($fullPath);
    http_response_code(400);
    echo json_encode(['ok'=>0,'error'=>'quota_exceeded','current'=>$currentUsage,'size'=>$fileSize,'quota'=>$quotaBytes]);
    exit;
}

// Generate thumbnail for images and videos (simple approach)
$thumbnailUrl = null;
if ($category === 'image') {
    $thumbPath = $finalPath . '/thumb_' . $safeName . '.jpg';
    if (function_exists('gd_info')) {
        $img = @imagecreatefromstring(file_get_contents($fullPath));
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            $nw = 320; $nh = (int)($h * ($nw / $w));
            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0,0,0,0,$nw,$nh,$w,$h);
            imagejpeg($tmp, $thumbPath, 80);
            imagedestroy($tmp);
            imagedestroy($img);
            $thumbnailUrl = str_replace(__DIR__, '', $thumbPath);
        }
    } elseif (class_exists('Imagick')) {
        $im = new Imagick($fullPath);
        $im->thumbnailImage(320, 0);
        $im->setImageFormat('jpeg');
        $im->writeImage($thumbPath);
        $thumbnailUrl = str_replace(__DIR__, '', $thumbPath);
    }
} elseif ($category === 'video') {
    // placeholder: integrate ffmpeg on server to snapshot a frame -> thumbnail
    $thumbPath = $finalPath . '/thumb_' . $safeName . '.jpg';
    $cmd = "ffmpeg -y -i " . escapeshellarg($fullPath) . " -ss 00:00:01 -vframes 1 -vf scale=320:-1 " . escapeshellarg($thumbPath) . " 2>&1";
    @exec($cmd);
    if (file_exists($thumbPath)) $thumbnailUrl = str_replace(__DIR__, '', $thumbPath);
}

// Persist to DB (media table)
$pdo = db();
$stmt = $pdo->prepare("INSERT INTO media (channel_id, title, description, type, category, tags, thumbnail, file_url, mime, duration, views, created_at, updated_at) VALUES (:cid,:title,:desc,:type,:cat,:tags,:thumb,:file,:mime,:dur,0,:now,:now)");
$now = date('Y-m-d H:i:s');
$title = $filename;
$desc = '';
$tags = '';
$mime = $detected;
$fileUrl = '/storage/uploads/' . date('Y/m') . '/' . $safeName; // public URL path
$stmt->execute([
    ':cid' => api_get_default_channel_for_user($uid),
    ':title' => $title,
    ':desc' => $desc,
    ':type' => $category,
    ':cat' => $category,
    ':tags' => $tags,
    ':thumb' => $thumbnailUrl,
    ':file' => $fileUrl,
    ':mime' => $mime,
    ':dur' => null,
    ':now' => $now
]);

$mediaId = $pdo->lastInsertId();

// Update storage usage
api_add_user_storage_usage($uid, $fileSize);


// cleanup tmp
array_map('unlink', glob($tmpDir . '/chunk_*'));
@rmdir($tmpDir);

echo json_encode(['ok'=>1,'id'=>$mediaId,'file_url'=>$fileUrl,'thumbnail'=>$thumbnailUrl,'mime'=>$mime]);