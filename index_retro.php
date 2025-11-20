<?php
// index_retro.php
// Example integration of the retro theme into the existing scaffold home page.
// Place this file alongside index.php and link to it for the retro experience.

session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/theme_retro.php';
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/includes/api_cat_group.php';

$currentUser = current_user();
$categories = api_list_categories();
$featured = api_list_media(['type'=>'video','limit'=>6,'featured'=>1]);
echo retro_header('KidsMaster — Retro 2011');

// Hero area
?>
<section class="km-retro-hero">
  <div class="left">
    <h2>KidsMaster — Share your Memes & Nostalgia</h2>
    <p class="km-retro-small">A 2011 layout remake with modern improvements — upload, share, and watch forever. Toggle to 2025 reveal for a modern layout.</p>
    <div style="margin-top:12px;">
      <a class="btn" href="/upload_ui.php">Upload</a>
      <a class="btn ghost" href="/browse.php">Browse</a>
    </div>
  </div>
  <div class="right">
    <div class="km-retro-aside">
      <h4>Categories</h4>
      <ul class="small-list">
        <?php foreach ($categories as $c): ?>
          <li><a href="browse.php?category=<?=urlencode($c['title'])?>"><?=htmlspecialchars($c['title'])?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</section>

<section class="km-retro-grid">
  <aside class="km-retro-aside">
    <div class="km-retro-panel">
      <h4>Navigation</h4>
      <ul class="small-list">
        <li><a href="/my_videos.php">My Videos</a></li>
        <li><a href="/my_channels.php">My Channels</a></li>
        <li><a href="/playlists.php">Playlists</a></li>
        <li><a href="/events.php">Events & Contests</a></li>
      </ul>
    </div>
    <div class="km-retro-side-ad">
      <strong>Retro Picks</strong>
      <p>Featured old-school clips and mixes</p>
    </div>
  </aside>

  <div>
    <div class="km-retro-panel">
      <h3>Featured Videos</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php foreach ($featured as $f): ?>
          <a class="km-retro-thumb" href="watch.php?id=<?=htmlspecialchars($f['id'])?>" style="width:200px;">
            <img src="<?=htmlspecialchars($f['thumbnail'])?>" alt="<?=htmlspecialchars($f['title'])?>">
            <h4><?=htmlspecialchars($f['title'])?></h4>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="km-retro-panel" style="margin-top:12px;">
      <h3>Recently Added</h3>
      <div class="thumb-grid" style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php $recent = api_list_media(['limit'=>8]); foreach($recent as $r): ?>
          <a class="km-retro-thumb" href="watch.php?id=<?=htmlspecialchars($r['id'])?>" style="width:180px;">
            <img src="<?=htmlspecialchars($r['thumbnail'])?>" alt="<?=htmlspecialchars($r['title'])?>">
            <h4><?=htmlspecialchars($r['title'])?></h4>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <aside class="km-retro-aside">
    <div class="km-retro-panel">
      <h4>Community</h4>
      <p><a href="/groups.php">Groups</a> • <a href="/people.php">People</a> • <a href="/blogs.php">Blogs</a></p>
    </div>

    <div class="km-retro-panel" style="margin-top:12px;">
      <h4>Events & Contests</h4>
      <p>Retro Remix — upload 2000s clips!</p>
    </div>
  </aside>
</section>

<?php
echo retro_footer();
?>