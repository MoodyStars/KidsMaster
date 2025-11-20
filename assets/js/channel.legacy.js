/* assets/js/channel.legacy.js
   Legacy channel chat client compatible with old browsers.
   Uses KMWebSocket if available, falls back to long-polling/HTTP writer via kmAjax.
   Avoids fetch/Promise/ES6 syntax.
*/
(function () {
  function $(id) { return document.getElementById(id); }
  function onReady(fn) {
    if (document.readyState === 'complete' || document.readyState === 'interactive') return fn();
    if (document.addEventListener) document.addEventListener('DOMContentLoaded', fn);
    else document.attachEvent('onreadystatechange', function () { if (document.readyState === 'complete') fn(); });
  }

  onReady(function () {
    bindSubscribe();
    bindFlagSelector();
    bindEmojiButton();
    bindChannelChat();
    bindLiveBtn();
    bindRedditStub();
  });

  function bindSubscribe() {
    var btn = $('subscribeBtn');
    if (!btn) return;
    btn.onclick = function () {
      var channelId = btn.getAttribute('data-channel');
      var subscribed = btn.getAttribute('data-subs') === '1';
      var action = subscribed ? 'unsubscribe' : 'subscribe';
      var body = 'channel_id=' + encodeURIComponent(channelId) + '&_csrf=' + encodeURIComponent(window.KM_CSRF || '');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '/ajax/channel_api.php?action=' + action, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.ok) {
            btn.setAttribute('data-subs', subscribed ? '0' : '1');
            btn.innerText = subscribed ? 'Subscribe' : 'Subscribed';
            return;
          }
        } catch (e) {}
        alert('Failed');
      };
      xhr.send(body);
    };
  }

  function bindFlagSelector() {
    var sel = $('chatCountryFlag');
    if (!sel) return;
    var flags = [
      ['', '-- Flag --'],
      ['us','\uD83C\uDDFA\uD83C\uDDF8 US'],['gb','\uD83C\uDDEC\uD83C\uDDE7 UK'],['in','\uD83C\uDDEE\uD83C\uDDF3 IN'],
      ['eg','\uD83C\uDDEA\uD83C\uDDEC EG']
    ];
    var html = '';
    for (var i = 0; i < flags.length; i++) html += '<option value="' + flags[i][0] + '">' + flags[i][1] + '</option>';
    sel.innerHTML = html;
  }

  function bindEmojiButton() {
    var btns = document.getElementsByClassName('emoji-btn');
    for (var i = 0; i < btns.length; i++) {
      (function (btn) {
        btn.onclick = function () {
          var target = document.getElementById(btn.getAttribute('data-target') || 'channelChatMsg');
          if (target) target.value += ' \u263A';
        };
      })(btns[i]);
    }
  }

  var chWs = null, chRoom = null, chConnected = false;

  function bindChannelChat() {
    var meta = document.querySelector('meta[name="channel-id"]');
    if (!meta) return;
    chRoom = meta.getAttribute('content');
    var sendBtn = $('channelSendBtn');
    var input = $('channelChatMsg');
    if (!sendBtn || !input) return;
    initChannelSocket();
    sendBtn.onclick = function () {
      var message = input.value && input.value.trim();
      if (!message) return;
      var country = document.getElementById('chatCountryFlag') ? document.getElementById('chatCountryFlag').value : '';
      var avatar = (window.KM_USER && window.KM_USER.avatar) ? window.KM_USER.avatar : '';
      var payload = { channel_id: chRoom, user_name: (window.KM_USER ? window.KM_USER.username : 'guest'), message: message, country: country, avatar: avatar, user_id: (window.KM_USER ? window.KM_USER.id : null) };
      appendChannelChatLine('You: ' + message, avatar, country, (new Date()).toISOString());
      if (chConnected && chWs) {
        try { chWs.send(JSON.stringify(payload)); input.value = ''; return; } catch (e) {}
      }
      // fallback via ajax
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '/ajax/channel_api.php?action=chat_send', true);
      xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
      xhr.onreadystatechange = function () { if (xhr.readyState === 4) { try { var j = JSON.parse(xhr.responseText||'{}'); if (j.ok) input.value=''; } catch (e) {} } };
      try { xhr.send(JSON.stringify(payload)); } catch (e) {}
    };
  }

  function initChannelSocket() {
    if (!chRoom) return;
    try {
      if (typeof KMWebSocket === 'function') {
        chWs = new KMWebSocket((location.protocol === 'https:' ? 'wss' : 'ws') + '://' + location.hostname + ':8080');
        chWs.onopen = function () { chConnected = true; };
        chWs.onmessage = function (ev) {
          try {
            var data = JSON.parse(ev.data);
            if (data.channel_id && String(data.channel_id) !== String(chRoom)) return;
            appendChannelChatLine((data.user_name || 'guest') + ': ' + data.message, data.user_avatar || '', data.country_code || '', data.ts || (new Date()).toISOString());
          } catch (e) {}
        };
        chWs.onclose = function () { chConnected = false; setTimeout(initChannelSocket, 2000); };
        // if using polling fallback, set poll room
        if (!chWs._ws) chWs._pollRoom = chRoom;
      } else {
        // no KMWebSocket (very old): fallback to periodic xhr poll
        pollChannelFallback();
      }
    } catch (e) { pollChannelFallback(); }
  }

  function pollChannelFallback() {
    var since = 0;
    function pollOnce() {
      var url = '/api.php?rest=chat_poll&media_id=' + encodeURIComponent(chRoom) + '&since=' + encodeURIComponent(since);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.ok && j.messages) {
            for (var i = 0; i < j.messages.length; i++) {
              var m = j.messages[i];
              since = Math.max(since, m.id || 0);
              appendChannelChatLine((m.user_name || 'guest') + ': ' + m.message, m.user_avatar || '', m.country_code || '', m.created_at || (new Date()).toISOString());
            }
          }
        } catch (e) {}
      };
      try { xhr.send(); } catch (e) {}
      setTimeout(pollOnce, 2500);
    }
    pollOnce();
  }

  function appendChannelChatLine(text, avatar, country, ts) {
    var win = document.getElementById('channelChatWindow');
    if (!win) return;
    var d = document.createElement('div'); d.className = 'chat-line';
    var avatarHtml = avatar ? '<img class="chat-avatar" src="' + escapeHtml(avatar) + '" alt="a" />' : '<span class="chat-avatar" style="display:inline-block;width:36px;height:36px;background:#ddd;border-radius:50%"></span>';
    var flag = country ? countryToEmoji(country) : '';
    var timeHtml = ts ? '<small style="margin-left:6px;color:#888;">' + (new Date(ts)).toLocaleTimeString() + '</small>' : '';
    d.innerHTML = '<div style="display:inline-block;vertical-align:top;margin-right:8px;">' + avatarHtml + '</div><div style="display:inline-block;vertical-align:top;"><div style="background:#fff;padding:6px;border-radius:6px;">' + escapeHtml(text) + timeHtml + '</div><div style="font-size:12px;color:#666;margin-top:4px;">' + escapeHtml(flag) + '</div></div>';
    win.appendChild(d);
    win.scrollTop = win.scrollHeight;
  }

  function countryToEmoji(code) {
    if (!code) return '';
    code = String(code).toUpperCase();
    if (code.length !== 2) return code;
    var A = 0x1F1E6;
    var c1 = String.fromCharCode(A + code.charCodeAt(0) - 65);
    var c2 = String.fromCharCode(A + code.charCodeAt(1) - 65);
    try { return String.fromCodePoint ? String.fromCodePoint(A + code.charCodeAt(0) - 65) + String.fromCodePoint(A + code.charCodeAt(1) - 65) : c1 + c2; } catch (e) { return c1 + c2; }
  }

  function bindLiveBtn() {
    var btn = document.getElementById('liveBtn');
    if (!btn) return;
    btn.onclick = function (e) { if (e && e.preventDefault) e.preventDefault(); alert('Live streaming is a stub.'); };
  }

  function bindRedditStub() {
    var el = document.getElementById('redditChatLink');
    if (!el) return;
    el.onclick = function (e) {
      if (e && e.preventDefault) e.preventDefault();
      var meta = document.querySelector('meta[name="channel-id"]');
      var cid = meta ? meta.getAttribute('content') : 0;
      var xhr = new XMLHttpRequest();
      xhr.open('GET', '/ajax/channel_api.php?action=reddit_stub&channel_id=' + encodeURIComponent(cid), true);
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.ok && j.embed_url) { window.open(j.embed_url, '_blank'); return; }
        } catch (e) {}
        alert('Failed to create reddit stub');
      };
      xhr.send();
    };
  }

  function escapeHtml(s) { return String(s || '').replace(/[&<>"']/g, function (m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }
})();