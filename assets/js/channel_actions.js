// assets/js/channel_actions.js
// Client-side for channel interactions: subscribe, pfp/banner uploads, chat (WS + fallback), emoji + flag handling.
// Uses KMWebSocket (compat.js) if available. Avoids heavy libs to remain portable.

(function(){
  function $(sel){ return document.querySelector(sel); }
  document.addEventListener('DOMContentLoaded', init);

  function init(){
    bindSubscribeButtons();
    bindChannelEditForms();
    bindChat();
  }

  function bindSubscribeButtons(){
    Array.prototype.slice.call(document.querySelectorAll('[data-subscribe-button]')).forEach(function(btn){
      btn.addEventListener('click', function(){
        var ch = btn.dataset.channel;
        var action = btn.dataset.subscribed === '1' ? 'unsubscribe' : 'subscribe';
        var fd = new FormData();
        fd.append('_csrf', window.KM_CSRF || '');
        fd.append('channel_id', ch);
        fetch('/ajax/channel_actions.php?action='+action, { method:'POST', body: fd }).then(r=>r.json()).then(function(j){
          if (j.ok) {
            btn.dataset.subscribed = btn.dataset.subscribed === '1' ? '0':'1';
            btn.textContent = btn.dataset.subscribed === '1' ? 'Subscribed':'Subscribe';
          } else alert(j.error || 'Failed');
        });
      });
    });
  }

  function bindChannelEditForms(){
    var f = $('#channelThemeForm');
    if (!f) return;
    f.addEventListener('submit', function(e){
      // use standard form submit; server handles upload. Could enhance with AJAX + progress.
    });
  }

  // Channel chat (room-aware)
  var ws = null;
  function bindChat(){
    var meta = document.querySelector('meta[name="channel-id"]');
    if (!meta) return;
    var room = meta.getAttribute('content');
    var send = $('#channelSendBtn');
    var input = $('#channelChatMsg');
    var flagEl = $('#chatCountryFlag');
    var win = $('#channelChatWindow');

    // Connect WS
    try {
      ws = (typeof KMWebSocket === 'function') ? new KMWebSocket((location.protocol==='https:'?'wss':'ws')+'://'+location.hostname+':8080') : null;
      if (ws && ws.onopen) { ws.onopen = function(){ console.log('WS open'); }; }
      if (ws && ws.onmessage) {
        ws.onmessage = function(ev){
          var data = JSON.parse(ev.data);
          if (String(data.channel_id) !== String(room)) return;
          appendChatLine(data.user_name+': '+data.message, data.user_avatar, data.country_code, data.created_at || data.ts);
        };
      }
      if (ws && ws._ws && ws.send) {
        // set poll room for fallback if needed
        ws._pollRoom = room;
      }
    } catch(e){
      ws = null;
    }

    if (send) send.addEventListener('click', function(){
      var msg = input.value.trim();
      if (!msg) return;
      var payload = { channel_id: room, user_name: (window.KM_USER?window.KM_USER.username:'guest'), message: msg, country: flagEl ? flagEl.value : '', avatar: (window.KM_USER && window.KM_USER.avatar) ? window.KM_USER.avatar : '' };
      if (ws && ws.send) {
        try { ws.send(JSON.stringify(payload)); input.value=''; appendChatLine('You: '+msg, payload.avatar, payload.country, new Date().toISOString()); return; } catch(e){}
      }
      // fallback HTTP
      fetch('/ajax/channel_actions.php?action=chat_send', { method:'POST', headers: {'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || '' }, body: JSON.stringify(payload) })
        .then(r=>r.json()).then(j=>{ if (j.ok) input.value=''; else alert('Failed'); });
    });
  }

  function appendChatLine(text, avatar, country, ts){
    var win = $('#channelChatWindow');
    if (!win) return;
    var div = document.createElement('div');
    div.className = 'chat-line';
    var left = document.createElement('div');
    left.className = 'chat-left';
    if (avatar) left.innerHTML = '<img class="chat-avatar" src="'+escapeHtml(avatar)+'">';
    else left.innerHTML = '<div class="chat-avatar" style="background:#ddd;"></div>';
    var right = document.createElement('div');
    right.className = 'chat-right';
    var meta = country ? '<span class="chat-flag">'+escapeHtml(countryToEmoji(country))+'</span>' : '';
    var timeHtml = ts ? ' <small style="color:#999;margin-left:8px;">'+(new Date(ts)).toLocaleTimeString()+'</small>' : '';
    right.innerHTML = '<div class="chat-text">'+escapeHtml(text)+timeHtml+'</div><div class="chat-meta">'+meta+'</div>';
    div.appendChild(left); div.appendChild(right);
    win.appendChild(div); win.scrollTop = win.scrollHeight;
  }

  function countryToEmoji(code){
    if (!code) return '';
    code = String(code).toUpperCase();
    if (code.length !== 2) return code;
    var A = 0x1F1E6;
    return String.fromCodePoint(A + code.charCodeAt(0) - 65) + String.fromCodePoint(A + code.charCodeAt(1) - 65);
  }

  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }

})();