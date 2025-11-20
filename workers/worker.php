<?php
// workers/worker.php
// CLI worker that consumes encoding jobs from Redis (kidsmaster:jobs) or polls encoding_jobs table.
// Processes job types: hls, thumbnail, trim, remix.
// Usage: php workers/worker.php
//
// Requirements: PHP CLI, ext-pdo, ext-redis (recommended), ffmpeg installed on PATH.

ini_set('display_errors',1);
error_reporting(E_ALL);

$root = dirname(__DIR__);
require_once $root . '/_includes/init.php';

// config
$redisHost = '127.0.0.1';
$redisPort = 6379;
$redisTimeout = 0.5;
$queueKey = 'kidsmaster:jobs';
$pollInterval = 2; // seconds when no job

echo "KidsMaster worker starting...\n";

$pdo = km_db();
$useRedis = extension_loaded('redis');

$redis = null;
if ($useRedis) {
    try {
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort, $redisTimeout);
        echo "Connected to Redis at {$redisHost}:{$redisPort}\n";
    } catch (Exception $e) {
        echo "Redis connection failed: " . $e->getMessage() . "\n";
        $redis = null;
    }
}

function process_job($pdo, $job) {
    $jobId = (int)$job['job_id'];
    $type = $job['type'];
    $payload = $job['payload'];

    echo "[".date('Y-m-d H:i:s')."] Processing job {$jobId} type={$type}\n";
    // mark job processing
    $stmt = $pdo->prepare("UPDATE encoding_jobs SET status='processing', attempts = attempts + 1, updated_at = :ts WHERE id = :id");
    $stmt->execute([':ts'=>date('Y-m-d H:i:s'), ':id'=>$jobId]);

    try {
        if ($type === 'hls') {
            do_hls($pdo, $jobId, $payload);
        } elseif ($type === 'thumbnail') {
            do_thumbnail($pdo, $jobId, $payload);
        } elseif ($type === 'trim') {
            do_trim($pdo, $jobId, $payload);
        } elseif ($type === 'remix') {
            do_remix($pdo, $jobId, $payload);
        } else {
            throw new Exception("Unknown job type: {$type}");
        }
        $pdo->prepare("UPDATE encoding_jobs SET status='done', updated_at = :ts WHERE id = :id")->execute([':ts'=>date('Y-m-d H:i:s'),':id'=>$jobId]);
        echo "Job {$jobId} done.\n";
    } catch (Exception $e) {
        $err = $e->getMessage();
        $pdo->prepare("UPDATE encoding_jobs SET status='failed', last_error=:err, updated_at = :ts WHERE id = :id")->execute([':err'=>$err,':ts'=>date('Y-m-d H:i:s'),':id'=>$jobId]);
        echo "Job {$jobId} failed: {$err}\n";
    }
}

function local_path_from_url($url) {
    // simplistic mapping for local storage: if starts with /storage/ return __DIR__ root path
    if (strpos($url, '/') === 0) return __DIR__ . '/../' . ltrim($url, '/');
    // if it's an absolute URL, return as-is (ffmpeg can read http)
    return $url;
}

function do_hls($pdo, $jobId, $payload) {
    $media_id = $payload['media_id'] ?? null;
    $file_url = $payload['file_url'] ?? null;
    if (!$media_id || !$file_url) throw new Exception("Missing media_id or file_url");
    $src = local_path_from_url($file_url);
    $outdir = __DIR__ . "/../storage/hls/{$media_id}";
    @mkdir($outdir, 0755, true);
    $index = $outdir . '/index.m3u8';
    $cmd = "ffmpeg -y -i " . escapeshellarg($src) . " -preset fast -g 48 -sc_threshold 0 -map 0 -f hls -hls_time 6 -hls_list_size 0 -hls_segment_filename " . escapeshellarg($outdir . '/seg%03d.ts') . " " . escapeshellarg($index) . " 2>&1";
    echo "Running: $cmd\n";
    exec($cmd, $out, $code);
    if ($code !== 0) throw new Exception("ffmpeg failed for hls: " . implode("\n",$out));
    // update media.hls_url relative path
    $publicUrl = '/storage/hls/'.$media_id.'/index.m3u8';
    $pdo->prepare("UPDATE media SET hls_url = :hls, processed = 1 WHERE id = :id")->execute([':hls'=>$publicUrl,':id'=>$media_id]);
}

function do_thumbnail($pdo, $jobId, $payload) {
    $media_id = $payload['media_id'] ?? null;
    $file_url = $payload['file_url'] ?? null;
    if (!$media_id || !$file_url) throw new Exception("Missing media_id or file_url");
    $src = local_path_from_url($file_url);
    $outdir = __DIR__ . "/../storage/thumbs/".intval($media_id);
    @mkdir($outdir,0755,true);
    $thumb = $outdir . '/thumb.jpg';
    // try ffmpeg first (works for video + images)
    $cmd = "ffmpeg -y -i " . escapeshellarg($src) . " -ss 00:00:01 -vframes 1 -vf scale=320:-1 " . escapeshellarg($thumb) . " 2>&1";
    exec($cmd, $out, $code);
    if ($code !== 0 || !file_exists($thumb)) {
        // fallback to GD if it's an image
        if (file_exists($src)) {
            $im = @imagecreatefromstring(file_get_contents($src));
            if ($im) {
                $w = imagesx($im); $h = imagesy($im);
                $nw = 320; $nh = intval($h * ($nw/$w));
                $tmp = imagecreatetruecolor($nw,$nh);
                imagecopyresampled($tmp,$im,0,0,0,0,$nw,$nh,$w,$h);
                imagejpeg($tmp,$thumb,80);
                imagedestroy($tmp); imagedestroy($im);
            } else {
                throw new Exception("Cannot create thumbnail for non-image/video $src");
            }
        } else throw new Exception("Source not found: $src");
    }
    $public = '/storage/thumbs/'.intval($media_id).'/thumb.jpg';
    $pdo->prepare("UPDATE media SET thumbnail = :t WHERE id = :id")->execute([':t'=>$public,':id'=>$media_id]);
}

function do_trim($pdo, $jobId, $payload) {
    $media_id = $payload['media_id'] ?? null;
    $file_url = $payload['file_url'] ?? null;
    $start = floatval($payload['start'] ?? 0);
    $end = floatval($payload['end'] ?? 0);
    if (!$media_id || !$file_url || $end <= $start) throw new Exception("Invalid trim params");
    $src = local_path_from_url($file_url);
    $outdir = __DIR__ . "/../storage/trims/{$media_id}";
    @mkdir($outdir,0755,true);
    $out = $outdir . '/trim_'.time().'.mp4';
    // use ffmpeg re-encode safe path (ensure accurate cut)
    $cmd = "ffmpeg -y -i " . escapeshellarg($src) . " -ss " . escapeshellarg($start) . " -to " . escapeshellarg($end) . " -c:v libx264 -c:a aac -strict -2 " . escapeshellarg($out) . " 2>&1";
    exec($cmd, $outLog, $code);
    if ($code !== 0) throw new Exception("ffmpeg trim failed: " . implode("\n",$outLog));
    // register new media row as derived clip
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO media (channel_id, title, description, type, category, tags, thumbnail, file_url, mime, duration, views, processed, created_at, updated_at) SELECT channel_id, CONCAT(title, ' (trim)'), description, type, category, tags, thumbnail, :file_url, mime, :dur, 0, 1, :ts, :ts FROM media WHERE id = :mid");
    // try to estimate duration
    $dur = $end - $start;
    $filePublic = '/storage/trims/'.$media_id.'/'.basename($out);
    $stmt->execute([':file_url'=>$filePublic, ':dur'=>$dur, ':ts'=>$now, ':mid'=>$media_id]);
}

function do_remix($pdo, $jobId, $payload) {
    $media_id = $payload['media_id'] ?? null;
    $file_url = $payload['file_url'] ?? null;
    $preset = $payload['preset'] ?? 'lofi';
    if (!$media_id || !$file_url) throw new Exception("Invalid remix params");
    $src = local_path_from_url($file_url);
    $outdir = __DIR__ . "/../storage/remix/{$media_id}";
    @mkdir($outdir,0755,true);
    $out = $outdir . '/remix_'.time().'.mp3';
    // select a filter by preset
    $filter = '';
    if ($preset === 'lofi') $filter = "-af 'aresample=44100,asetrate=44100*0.9,atempo=1.0,afftdn' ";
    elseif ($preset === 'echo') $filter = "-af 'aecho=0.8:0.9:1000:0.3' ";
    elseif ($preset === 'spedup') $filter = "-filter_complex 'atempo=1.25' ";
    $cmd = "ffmpeg -y -i " . escapeshellarg($src) . " " . $filter . " -c:a libmp3lame -q:a 4 " . escapeshellarg($out) . " 2>&1";
    exec($cmd, $outLog, $code);
    if ($code !== 0) throw new Exception("ffmpeg remix failed: " . implode("\n",$outLog));
    // create new media entry for remix
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO media (channel_id, title, description, type, category, tags, thumbnail, file_url, mime, duration, views, processed, created_at, updated_at) SELECT channel_id, CONCAT(title, ' (remix)'), description, 'audio', category, tags, thumbnail, :file_url, 'audio/mpeg', NULL, 0, 1, :ts, :ts FROM media WHERE id = :mid");
    $publicPath = '/storage/remix/'.$media_id.'/'.basename($out);
    $stmt->execute([':file_url'=>$publicPath, ':ts'=>$now, ':mid'=>$media_id]);
}

// main loop
while (true) {
    try {
        if ($redis) {
            // BRPOP blocks until an element is available
            $res = $redis->brPop($queueKey, 5); // blocking pop with 5s timeout
            if ($res && is_array($res) && isset($res[1])) {
                $payload = json_decode($res[1], true);
                if ($payload) process_job($pdo, $payload);
                continue;
            }
        }
        // fallback: poll DB for queued jobs
        $stmt = $pdo->prepare("SELECT id, job_type, payload FROM encoding_jobs WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED");
        $pdo->beginTransaction();
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $payload = ['job_id'=>$row['id'],'type'=>$row['job_type'],'payload'=>json_decode($row['payload'], true)];
            // mark as processing
            $pdo->prepare("UPDATE encoding_jobs SET status='processing', attempts = attempts + 1 WHERE id = :id")->execute([':id'=>$row['id']]);
            $pdo->commit();
            process_job($pdo, $payload);
            continue;
        }
        $pdo->commit();
        // sleep briefly
        sleep($pollInterval);
    } catch (Exception $e) {
        // log and continue
        error_log("Worker loop exception: " . $e->getMessage());
        if ($pdo->inTransaction()) $pdo->rollBack();
        sleep(2);
    }
}