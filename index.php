<?php
// index.php - Updated Home with expanded sections: Featured, Recently Viewed, Most Viewed,
// Public media by type, Popular Tags, Events & Contests, quick links to Upload/Build/Blogs/People/Help,
// and user utilities (My Videos, My Channels, My Playlist, My Accounts)
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/includes/api_cat_group.php';

// Fetch data
$featured = api_list_media(['type'=>'video','limit'=>8,'featured'=>1]);
$recent = api_list_media(['limit'=>12,'sort'=>'last_viewed']);
$mostViewed = api_list_media(['limit'=>8,'sort'=>'views']);
$popularTags = []; // derive from DB quickly
$pdo = db();
$tagStmt = $pdo->query("SELECT tag, COUNT(*) as c FROM (SELECT TRIM(t) AS tag FROM (SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tags, ',', numbers.n), ',', -1) t
  FROM media JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10) numbers
  ON CHAR_LENGTH(tags) - CHAR_LENGTH(REPLACE(tags, ',', '')) >= numbers.n-1) derived WHERE tag <> '' GROUP BY tag ORDER BY c DESC LIMIT 20) as t");
if ($tagStmt) {
    $popularTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
}
$lastUsersOnline = api_get_last_online_users(5);
$categories = api_list_categories();
$currentUser = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>KidsMaster — Home</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/site_extended.css" />
  <script>window.KM_USER = <?=json_encode($currentUser ?? null)?>; window.KM_CSRF = <?=json_encode(csrf_token())?>;</script>
  <script defer src="assets/js/upload_widget.js"></script>
</head>
<body class="km-body">
  <header class="km-header">
    <div class="km-brand">
      <a href="index.php">KidsMaster</a>
      <span class="tagline">Better than TV — watch what you want, when you want it!</span>
    </div>

    <nav class="km-nav main-nav">
      <a href="index.php">Home</a>
      <a href="upload_ui.php">Upload</a>
      <a href="builds.php">Build</a>
      <a href="blogs.php">Blogs</a>
      <a href="people.php">People</a>
      <a href="help.php">Help</a>
      <?php if ($currentUser): ?>
        <a href="my_videos.php">My Videos</a>
        <a href="my_channels.php">My Channels</a>
        <a href="playlists.php">My Playlist</a>
        <a href="account.php">My Account</a>
        <a href="logout.php">Log out</a>
      <?php else: ?>
        <a href="signup.php">Sign Up</a>
        <a href="login.php">Log In</a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="km-main container">
    <section class="panel hero">
      <div class="hero-left">
        <h1>KidsMaster — Share your Memes & Nostalgia</h1>
        <p>Upload, share and watch videos, audio, images, software and games. Free 512GB user storage quota example — configure per account.</p>
        <div class="cta-row">
          <a href="upload_ui.php" class="btn large">Upload</a>
          <a href="browse.php" class="btn ghost">Browse</a>
        </div>
      </div>
      <div class="hero-right">
        <h3>Last Online</h3>
        <ul class="small-list">
          <?php foreach ($lastUsersOnline as $u): ?>
            <li><?=htmlspecialchars($u['username'])?> <small><?=htmlspecialchars($u['last_seen'])?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="panel section-featured">
      <h2>Featured Videos</h2>
      <div class="featured-row">
        <?php foreach ($featured as $f): ?>
          <div class="feat-card">
            <a href="watch.php?id=<?=htmlspecialchars($f['id'])?>">
              <img src="<?=htmlspecialchars($f['thumbnail'])?>" alt="<?=htmlspecialchars($f['title'])?>">
              <div class="meta"><?=htmlspecialchars($f['title'])?></div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="grid-3">
      <div class="panel">
        <h3>Recently Viewed</h3>
        <div class="thumb-grid">
          <?php foreach ($recent as $r): ?>
            <article class="thumb"><a href="watch.php?id=<?=htmlspecialchars($r['id'])?>"><img src="<?=htmlspecialchars($r['thumbnail'])?>"><div class="thumb-meta"><h4><?=htmlspecialchars($r['title'])?></h4></div></a></article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="panel">
        <h3>Most Viewed</h3>
        <div class="thumb-grid small">
          <?php foreach ($mostViewed as $m): ?>
            <article class="thumb small"><a href="watch.php?id=<?=htmlspecialchars($m['id'])?>"><img src="<?=htmlspecialchars($m['thumbnail'])?>"><div class="thumb-meta"><h4><?=htmlspecialchars($m['title'])?></h4></div></a></article>
          <?php endforeach; ?>
        </div>
      </div>

      <aside class="panel">
        <h3>Popular Tags</h3>
        <div class="tags">
          <?php foreach ($popularTags as $t): ?>
            <a href="browse.php?tag=<?=urlencode($t)?>"><?=htmlspecialchars($t)?></a>
          <?php endforeach; ?>
        </div>
        <hr>
        <h4>Categories</h4>
        <ul class="small-list">
          <?php foreach ($categories as $c): ?>
            <li><a href="browse.php?category=<?=urlencode($c['title'])?>"><?=htmlspecialchars($c['title'])?></a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </section>

    <section class="panel">
      <h2>Media Hubs</h2>
      <div class="hub-grid">
        <a class="hub" href="browse.php?type=video">Videos</a>
        <a class="hub" href="browse.php?type=audio">Audio</a>
        <a class="hub" href="browse.php?type=image">Images</a>
        <a class="hub" href="browse.php?type=software">Software</a>
        <a class="hub" href="browse.php?type=game">Games</a>
        <a class="hub" href="storage.php">Storage (Your Files)</a>
        <a class="hub" href="archive_channels.php">Archive Channels</a>
        <a class="hub" href="events.php">Events & Contests</a>
      </div>
    </section>

    <section class="panel">
      <h2>Community</h2>
      <div class="row">
        <a href="groups.php" class="btn">Groups</a>
        <a href="people.php" class="btn">People</a>
        <a href="blogs.php" class="btn">Blogs</a>
        <a href="contact.php" class="btn ghost">Contact</a>
      </div>
    </section>
  </main>

  <footer class="km-footer">
    <div class="links">
      <a href="about.php">About Us</a> |
      <a href="help.php">Help</a> |
      <a href="advertise.php">Advertise</a> |
      <a href="terms.php">Terms</a> |
      <a href="privacy.php">Privacy</a>
    </div>
    <div class="copyright">Copyright © 2025 KidsMaster All rights reserved.</div>
  </footer>
</body>
</html>