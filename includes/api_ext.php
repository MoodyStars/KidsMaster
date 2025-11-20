<?php
// includes/api_ext.php
// Extension helpers: threaded comments, moderation, live stream management, reddit links.
// Include this file where new features are required (watch.php, live_streams.php, ajax endpoints).

require_once __DIR__ . '/../api.php';

function api_post_comment($media_id, $user_id, $author, $body, $parent_id = null) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO comments (media_id, parent_id, user_id, author, body, created_at) VALUES (:mid, :pid, :uid, :a, :b, :ts)");
    $stmt->execute([
        ':mid' => $media_id,
        ':pid' => $parent_id,
        ':uid' => $user_id,
        ':a' => $author,
        ':b' => $body,
        ':ts' => date('Y-m-d H:i:s')
    ]);
    return (int)$pdo->lastInsertId();
}

function api_report_comment($reporter_id, $comment_id, $reason = null, $notes = null) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO comment_reports (comment_id, reporter_id, reason, notes, created_at) VALUES (:cid,:rid,:reason,:notes,:ts)");
    $stmt->execute([':cid'=>$comment_id, ':rid'=>$reporter_id, ':reason'=>$reason, ':notes'=>$notes, ':ts'=>date('Y-m-d H:i:s')]);
    return ['ok'=>1, 'id'=>$pdo->lastInsertId()];
}

function api_delete_comment($comment_id, $moderator_id = null) {
    // soft-delete for audit purposes
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE comments SET is_deleted = 1 WHERE id = :id");
    $stmt->execute([':id'=>$comment_id]);
    return ['ok'=>1];
}

/* Live stream management */
function api_create_live_stream($channel_id, $title = null, $description = null) {
    $pdo = db();
    // generate a secure random RTMP key
    $rtmp_key = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO live_streams (channel_id, title, description, rtmp_key, status, created_at) VALUES (:cid,:t,:d,:rk,'created',:ts)");
    $stmt->execute([':cid'=>$channel_id, ':t'=>$title, ':d'=>$description, ':rk'=>$rtmp_key, ':ts'=>date('Y-m-d H:i:s')]);
    return $pdo->lastInsertId();
}

function api_get_live_stream($id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM live_streams WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    return $stmt->fetch() ?: null;
}

function api_update_live_stream_status($id, $status) {
    $pdo = db();
    if (!in_array($status, ['created','live','ended'])) return false;
    if ($status === 'live') {
        $stmt = $pdo->prepare("UPDATE live_streams SET status='live', started_at = :ts WHERE id = :id");
        $stmt->execute([':ts'=>date('Y-m-d H:i:s'), ':id'=>$id]);
    } elseif ($status === 'ended') {
        $stmt = $pdo->prepare("UPDATE live_streams SET status='ended', ended_at = :ts WHERE id = :id");
        $stmt->execute([':ts'=>date('Y-m-d H:i:s'), ':id'=>$id]);
    } else {
        $stmt = $pdo->prepare("UPDATE live_streams SET status='created' WHERE id = :id");
        $stmt->execute([':id'=>$id]);
    }
    return true;
}

/* Reddit integration helpers (stubs) */
function api_link_reddit_subreddit($channel_id, $subreddit_name, $thread_url = null) {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO reddit_integrations (channel_id, reddit_subreddit, reddit_thread_url, created_at) VALUES (:cid, :sr, :url, :ts)");
    $stmt->execute([':cid'=>$channel_id, ':sr'=>$subreddit_name, ':url'=>$thread_url, ':ts'=>date('Y-m-d H:i:s')]);
    return $pdo->lastInsertId();
}

function api_get_reddit_embed_url($channel_id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT reddit_subreddit, reddit_thread_url FROM reddit_integrations WHERE channel_id = :cid ORDER BY id DESC LIMIT 1");
    $stmt->execute([':cid'=>$channel_id]);
    $r = $stmt->fetch();
    if (!$r) return null;
    // prefer thread url if present, else subreddit
    if (!empty($r['reddit_thread_url'])) return $r['reddit_thread_url'];
    return 'https://www.reddit.com/r/' . rawurlencode($r['reddit_subreddit']);
}

/* Channel banner/background update helper */
function api_update_channel_media($channel_id, $owner_id, $data = []) {
    // wrapper around api_edit_channel to add background support
    $pdo = db();
    // verify owner
    $stmt = $pdo->prepare("SELECT owner_id FROM channels WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$channel_id]);
    $ch = $stmt->fetch();
    if (!$ch || $ch['owner_id'] != $owner_id) return ['ok'=>0,'error'=>'not_owner'];

    $fields = [];
    $params = [':id'=>$channel_id];
    if (isset($data['banner'])) { $fields[] = "banner = :banner"; $params[':banner'] = $data['banner']; }
    if (isset($data['gif_banner'])) { $fields[] = "gif_banner = :gif"; $params[':gif'] = $data['gif_banner']; }
    if (isset($data['profile_pic'])) { $fields[] = "profile_pic = :pp"; $params[':pp'] = $data['profile_pic']; }
    if (isset($data['background'])) { $fields[] = "background = :bg"; $params[':bg'] = $data['background']; }

    if (empty($fields)) return ['ok'=>0,'error'=>'nothing'];
    $sql = "UPDATE channels SET " . implode(',', $fields) . " WHERE id = :id";
    $pdo->prepare($sql)->execute($params);
    return ['ok'=>1];
}