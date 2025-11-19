<?php
// Simple homepage scaffold for KidsMaster (2011 vibe -> 2025 reveal)
// Assumes sessions + DB in api.php helper functions
session_start();
require_once __DIR__ . '/api.php';

$categories = [
  "Business", "Cars and Vehicles", "Cartoon", "Comedy", "Event and Party", "Family", "Fashion and Lifestyle",
  "Funny", "Games", "Howto and DIY", "Miscellaneous", "Music", "News and Politics", "People and Blog",
  "Pets and Animals", "Science and Technology", "2000s Nostalgic", "Kids songs", "YTP", "Animate", "Travel and Holiday"
];

$featured = api_list_media(['type' => 'video', 'limit' => 8, 'featured' => 1]); // wrapper returns array
$recent = api_list_media(['limit' => 12, 'sort' => 'last_viewed']);
$mostViewed = api_list_media(['limit' => 8, 'sort' => 'views']);
$lastUsersOnline = api_get_last_online_users(5);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>KidsMaster — Share your Memes & Nostalgia</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="assets/css/style.css" />
  <script defer src="assets/js/main.js"></script>
</head>
<body class="km-body">
  <header class="km-header">
    <div class="km-brand">
      <a href="index.php">KidsMaster</a>
      <span class="tagline">Better than TV — watch what you want, when you want it!</span>
    </div>
    <nav class="km-nav">
      <form id="searchForm" class="km-search" onsubmit="return false;">
        <input id="q" name="q" type="search" placeholder="Search videos, audio, images, games..." />
        <select id="type" name="type">
          <option value="">All</option>
          <option value="video">Video</option>
          <option value="audio">Audio</option>
          <option value="image">Image</option>
          <option value="software">Software</option>
          <option value="game">Game</option>
          <option value="storage">Storage</option>
        </select>
        <button type="button" onclick="searchSite()">Search</button>
      </form>
      <div class="km-links">
        <?php if (isset($_SESSION['user'])): ?>
          <a href="upload.php" class="btn">Upload</a>
          <a href="my.php">My Videos</a>
          <a href="channels.php">Channels</a>
          <a href="logout.php">Log out</a>
        <?php else: ?>
          <a href="signup.php">Sign Up</a>
          <a href="login.php">Log In</a>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <main class="km-main">
    <section class="km-hero">
      <div class="hero-left">
        <h1>KidsMaster - Share your Memes and nostalgia</h1>
        <p>Upload Quickly, Share Easily, Watch forever. Sign Up — It's Free.</p>
        <div class="cta-row">
          <a href="upload.php" class="btn large">Upload</a>
          <a href="browse.php" class="btn ghost">Browse</a>
        </div>
      </div>
      <div class="hero-right">
        <div class="featured-carousel" id="featuredCarousel">
          <?php foreach ($featured as $item): ?>
            <a class="feat-item" href="watch.php?id=<?=htmlspecialchars($item['id'])?>">
              <img src="<?=htmlspecialchars($item['thumbnail'])?>" alt="<?=htmlspecialchars($item['title'])?>" />
              <div class="feat-meta"><?=htmlspecialchars($item['title'])?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="km-grid">
      <aside class="left-col">
        <div class="panel">
          <h3>Categories</h3>
          <ul class="categories">
            <?php foreach ($categories as $c): ?>
              <li><a href="browse.php?category=<?=urlencode($c)?>"><?=htmlspecialchars($c)?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="panel">
          <h3>Channels</h3>
          <ul id="channelsList">
            <!-- Channels will be loaded by JS -->
          </ul>
        </div>

        <div class="panel">
          <h3>Last Users Online</h3>
          <ul>
            <?php foreach ($lastUsersOnline as $u): ?>
              <li><?=htmlspecialchars($u['username'])?> <small><?=htmlspecialchars($u['last_seen'])?></small></li>
            <?php endforeach; ?>
          </ul>
        </div>

      </aside>

      <section class="center-col">
        <div class="panel">
          <h2>Recently Viewed</h2>
          <div class="thumb-grid">
            <?php foreach ($recent as $r): ?>
              <article class="thumb">
                <a href="watch.php?id=<?=htmlspecialchars($r['id'])?>">
                  <img src="<?=htmlspecialchars($r['thumbnail'])?>" alt="<?=htmlspecialchars($r['title'])?>" />
                  <div class="thumb-meta">
                    <h4><?=htmlspecialchars($r['title'])?></h4>
                    <small><?=htmlspecialchars($r['views'])?> views</small>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="panel">
          <h2>Most Viewed</h2>
          <div class="thumb-grid small">
            <?php foreach ($mostViewed as $m): ?>
              <article class="thumb">
                <a href="watch.php?id=<?=htmlspecialchars($m['id'])?>">
                  <img src="<?=htmlspecialchars($m['thumbnail'])?>" alt="<?=htmlspecialchars($m['title'])?>" />
                  <div class="thumb-meta">
                    <h4><?=htmlspecialchars($m['title'])?></h4>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

      </section>

      <aside class="right-col">
        <div class="panel">
          <h3>Featured Tags</h3>
          <div class="tags">
            <a href="browse.php?tag=nostalgia">nostalgia</a>
            <a href="browse.php?tag=memes">memes</a>
            <a href="browse.php?tag=2000s">2000s</a>
            <a href="browse.php?tag=kids">kids</a>
            <a href="browse.php?tag=animation">animation</a>
          </div>
        </div>

        <div class="panel">
          <h3>Events & Contests</h3>
          <ul>
            <li>Retro Remix — Upload 2000s clips</li>
            <li>Animation Week</li>
          </ul>
        </div>

      </aside>
    </section>

    <footer class="km-footer">
      <div class="links">
        <a href="about.php">About Us</a> |
        <a href="help.php">Help</a> |
        <a href="advertise.php">Advertise on KidsMaster</a> |
        <a href="terms.php">Terms of Use</a> |
        <a href="privacy.php">Privacy Policy</a> |
        <a href="rss.php">RSS</a>
      </div>
      <div class="copyright">Copyright © 2025 KidsMaster All rights reserved.</div>
    </footer>
  </main>
</body>
</html>