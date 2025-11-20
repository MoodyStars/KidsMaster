// assets/js/retro2011.js
// Retro UI behaviors and "2025 reveal" toggle.
// - Sticky header on scroll (2011 feel turned modern in 2025 reveal)
// - Toggle between retro and reveal modes (persist in localStorage)
// - Simple GIF banner mute/unmute control
// - Small helper to enhance old-school thumbnails with hover popouts

(function(){
  const REVEAL_KEY = 'kidsmaster_reveal_2025';
  document.addEventListener('DOMContentLoaded', initRetro);

  function initRetro(){
    // apply reveal mode if saved
    const reveal = localStorage.getItem(REVEAL_KEY) === '1';
    if (reveal) document.body.classList.add('km-reveal-2025');

    // bind toggle button if present
    const btn = document.getElementById('revealToggle');
    if (btn) {
      btn.addEventListener('click', function(){
        document.body.classList.toggle('km-reveal-2025');
        const on = document.body.classList.contains('km-reveal-2025');
        localStorage.setItem(REVEAL_KEY, on ? '1' : '0');
        btn.textContent = on ? '2025 Mode' : '2011 Mode';
      });
      btn.textContent = document.body.classList.contains('km-reveal-2025') ? '2025 Mode' : '2011 Mode';
    }

    // sticky retro header
    const hdr = document.querySelector('.km-retro-header');
    if (hdr) {
      const orig = hdr.offsetTop;
      window.addEventListener('scroll', function(){
        if (window.pageYOffset > orig+10) {
          hdr.classList.add('km-sticky');
          hdr.style.position = 'fixed';
          hdr.style.top = '0';
          hdr.style.left = '50%';
          hdr.style.transform = 'translateX(-50%)';
          hdr.style.width = '100%';
          hdr.style.zIndex = '999';
        } else {
          hdr.classList.remove('km-sticky');
          hdr.style.position = '';
          hdr.style.top = '';
          hdr.style.left = '';
          hdr.style.transform = '';
          hdr.style.width = '';
          hdr.style.zIndex = '';
        }
      });
    }

    // GIF banner mute/unmute
    document.querySelectorAll('.banner-gif').forEach(img=>{
      // Pause animated GIF by default: use a canvas trick if supported (fallback shows GIF)
      try {
        // Replace gif with poster image if data-poster provided
        const poster = img.getAttribute('data-poster');
        if (poster) {
          const wrapper = document.createElement('div');
          wrapper.style.position='relative';
          wrapper.innerHTML = `<img src="${poster}" class="banner-poster" style="width:100%;height:200px;object-fit:cover;border-radius:0" /><button class="gif-play" aria-label="Play GIF" style="position:absolute;left:12px;bottom:12px;padding:6px 8px;border-radius:6px;background:rgba(0,0,0,0.6);color:#fff;border:none">Play GIF</button>`;
          img.parentNode.replaceChild(wrapper, img);
          const btn = wrapper.querySelector('.gif-play');
          btn.addEventListener('click', ()=> {
            const g = document.createElement('img');
            g.src = img.src;
            g.className = 'banner-gif';
            g.style.width='100%'; g.style.height='200px'; g.style.objectFit='cover';
            wrapper.replaceChild(g, wrapper.firstChild);
          });
        }
      } catch(e){}
    });

    // thumbnail hover popout (retro tooltip card)
    document.querySelectorAll('.km-retro-thumb').forEach(thumb=>{
      thumb.addEventListener('mouseenter', function(e){
        const title = thumb.querySelector('h4')?.textContent || '';
        const meta = thumb.querySelector('.thumb-meta')?.textContent || '';
        const card = document.createElement('div');
        card.className = 'retro-pop';
        card.style.position='absolute';
        card.style.background='white';
        card.style.padding='8px';
        card.style.borderRadius='6px';
        card.style.boxShadow='0 6px 18px rgba(0,0,0,0.12)';
        card.style.zIndex='9999';
        card.innerHTML = `<strong>${escapeHtml(title)}</strong><div class="km-retro-small">${escapeHtml(meta)}</div>`;
        document.body.appendChild(card);
        const r = thumb.getBoundingClientRect();
        card.style.left = (r.left + 8) + 'px';
        card.style.top = (r.top - card.offsetHeight - 8) + 'px';
        thumb._popup = card;
      });
      thumb.addEventListener('mouseleave', function(){
        if (thumb._popup) { thumb._popup.remove(); thumb._popup = null; }
      });
    });

  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
})();