```markdown
# KidsMaster Retro 2011 Theme — 2025 Reveal

What this theme adds
- A boxed, nostalgic 2011-style layout inspired by Wenoo, VidLii, ZippCast and KidsTube.
- Retro header, chunky navigation buttons, classic hero, three-column site grid and sidebars.
- Channel banner support for GIF banners with a poster/pause-first behavior (to preserve CPU and bandwidth).
- SMS-style chat visuals and avatar+flag rendering that matches the 2011 portal aesthetic.
- A client-side "2025 reveal" toggle that modernizes spacing, search width and thumbnail sizes while preserving the retro look.
- Small UX niceties: sticky header, hover thumbnail popouts, optimistic GIF play behavior, and a simple reveal toggle persisted in localStorage.

Files added
- assets/css/retro2011.css — full retro & reveal CSS
- assets/js/retro2011.js — client behaviors for theme and reveal toggle
- README-theme.md — integration notes and usage

How to integrate
1. Include the CSS and JS in pages you want themed:
   - <link rel="stylesheet" href="/assets/css/retro2011.css">
   - <script defer src="/assets/js/retro2011.js"></script>

2. Wrap your main content in the wrapper:
   - <div class="km-retro-wrap"><div class="km-retro-container">... site ...</div></div>

3. Update header markup to match the retro classes (example snippet included below).

Header example (insert in your header):
```html
<header class="km-retro-header">
  <div class="km-retro-brand">
    <div class="logo">KM</div>
    <div>
      <h1>KidsMaster</h1>
      <div class="km-retro-tag">Better than TV — watch what you want</div>
    </div>
  </div>
  <nav class="km-retro-nav">
    <a href="/">Home</a>
    <a href="/browse.php">Browse</a>
    <a href="/upload_ui.php" class="btn-ghost">Upload</a>
    <div class="search"><input type="search" placeholder="Search..."></div>
    <button id="revealToggle" class="btn-ghost">2011 Mode</button>
  </nav>
</header>
```

Notes & recommended next steps
- Replace heavy animated GIF banners with a small poster frame and optional "Play GIF" to reduce CPU/memory on mobile.
- Gradually add responsive scaling rules to support very narrow screens; the theme keeps the 3-column layout until 980px where it collapses.
- For accessibility, ensure keyboard focus styles and ARIA labels for interactive elements (search, reveal toggle, GIF play).
- If you want, I can:
  - generate updated header/footer include templates that integrate with your existing PHP scaffold (auth, csrf),
  - create a channel-specific skin picker so creators can toggle retro/modern look per channel,
  - or convert the GIF poster behavior into a service-worker assisted lazy loader for improved performance.

```