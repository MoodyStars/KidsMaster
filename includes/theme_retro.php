<?php
// includes/theme_retro.php (updated)
// Retro header/footer helpers now include conditional legacy support for older browsers.
// The header injects compatibility scripts/styles for IE7-11 and older Chromium/Firefox as needed.

function retro_header($title = 'KidsMaster') {
    $user = $_SESSION['user'] ?? null;
    $csrf = function_exists('csrf_token') ? csrf_token() : '';
    ob_start();
    ?>
    <div class="km-retro-wrap">
      <div class="km-retro-container">
        <!--[if lt IE 10]>
          <link rel="stylesheet" href="/assets/css/legacy.css">
          <script src="/assets/js/compat.js"></script>
        <![endif]-->

        <!-- Also load compat.js for browsers that don't support modern APIs -->
        <script>
          (function(){
            var needCompat = false;
            // very conservative checks
            if (!window.addEventListener || !window.JSON || !document.querySelector) needCompat = true;
            if (needCompat) {
              var s = document.createElement('script'); s.src = '/assets/js/compat.js'; s.async = false;
              (document.getElementsByTagName('head')[0]||document.documentElement).appendChild(s);
            } else {
              // still expose stub helpers (compat will be lazy-loaded on-demand)
              // no-op
            }
          })();
        </script>

        <header class="km-retro-header" role="banner">
          <div class="km-retro-brand">
            <div class="logo">KM</div>
            <div>
              <h1><?=htmlspecialchars($title)?></h1>
              <div class="km-retro-tag">Better than TV — watch what you want</div>
            </div>
          </div>
          <nav class="km-retro-nav" role="navigation" aria-label="Main navigation">
            <a href="/">Home</a>
            <a href="/browse.php">Browse</a>
            <a href="/channels.php">Channels</a>
            <a href="/upload_ui.php" class="btn-ghost">Upload</a>
            <div class="search" role="search">
              <form action="/browse.php" method="get" style="display:inline;">
                <input name="q" type="search" placeholder="Search videos, audio..." aria-label="Search">
              </form>
            </div>
            <button id="revealToggle" class="btn-ghost" aria-pressed="false">2011 Mode</button>
            <?php if ($user): ?>
              <a href="/my_videos.php"><?=htmlspecialchars($user['username'])?></a>
              <form method="post" action="/logout.php" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>">
                <button class="btn-ghost">Logout</button>
              </form>
            <?php else: ?>
              <a href="/signup.php">Sign Up</a>
              <a href="/login.php">Login</a>
            <?php endif; ?>
          </nav>
        </header>
        <main role="main" style="padding:12px;">
    <?php
    return ob_get_clean();
}

function retro_footer() {
    ob_start();
    ?>
        </main>
        <footer class="km-retro-footer" role="contentinfo">
          <div class="small-links">
            <a href="/about.php">About</a> |
            <a href="/help.php">Help</a> |
            <a href="/privacy.php">Privacy</a> |
            <a href="/terms.php">Terms</a>
          </div>
          <div class="km-retro-small" style="margin-top:8px;">Copyright © <?=date('Y')?> KidsMaster All rights reserved.</div>
        </footer>
      </div>
    </div>
    <?php
    return ob_get_clean();
}