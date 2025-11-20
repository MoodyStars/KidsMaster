// assets/js/channel.js (enhanced)
// - EmojiButton integration points (if included) for a polished picker.
// - Country flag selector populated with common codes and conversion to emoji.
// - WebSocket room-aware chat with optimistic UI and fallback to AJAX writer.
// - Sends avatar & country metadata; renders avatar + flag in messages.
// - Reddit embed stub improved to open in a popup and offer copy link.

document.addEventListener('DOMContentLoaded', function(){
  bindSubscribe();
  bindFlagSelector();
  initEmojiPicker();
  bindChannelChat();
  bindLiveBtn();
  bindRedditStub();
});

function bindSubscribe(){
  const btn = document.getElementById('subscribeBtn');
  if (!btn) return;
  btn.addEventListener('click', async function(){
    const channelId = this.dataset.channel;
    const subscribed = this.dataset.subs === '1';
    const action = subscribed ? 'unsubscribe' : 'subscribe';
    try {
      const res = await fetch('/ajax/channel_api.php?action=' + action, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.KM_CSRF || ''},
        body: 'channel_id='+encodeURIComponent(channelId)+'&_csrf='+encodeURIComponent(window.KM_CSRF || '')
      });
      const j = await res.json();
      if (j.ok) {
        this.dataset.subs = subscribed ? '0' : '1';
        this.textContent = subscribed ? 'Subscribe' : 'Subscribed';
      } else alert(j.error || 'Failed');
    } catch(e){ alert('Network error'); }
  });
}

function bindFlagSelector(){
  const sel = document.getElementById('chatCountryFlag');
  if (!sel) return;
  const flags = [
    ['','-- Flag --'],
    ['us','🇺🇸 US'],['gb','🇬🇧 UK'],['in','🇮🇳 IN'],['eg','🇪🇬 EG'],
    ['sa','🇸🇦 SA'],['ae','🇦🇪 AE'],['ca','🇨🇦 CA'],['au','🇦🇺 AU'],
    ['de','🇩🇪 DE'],['fr','🇫🇷 FR'],['es','🇪🇸 ES'],['it','🇮🇹 IT']
  ];
  sel.innerHTML = flags.map(f => `<option value="${f[0]}">${f[1]}</option>`).join('');
}

let emojiPicker = null;
function initEmojiPicker(){
  // EmojiButton optional integration
  if (window.EmojiButton) {
    emojiPicker = new EmojiButton({position:'top-end'});
    document.querySelectorAll('.emoji-btn').forEach(btn=>{
      const targetId = btn.dataset.target || 'channelChatMsg';
      btn.addEventListener('click', () => {
        emojiPicker.togglePicker(btn);
        emojiPicker.on('emoji', selection => {
          const target = document.getElementById(targetId);
          if (target) { target.value += selection.emoji; target.focus(); }
        });
      });
    });
  } else {
    // fallback: clicking emoji button appends a simple smiley
    document.querySelectorAll('.emoji-btn').forEach(btn=>{
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target || 'channelChatMsg');
        if (target) { target.value += ' 😊'; target.focus(); }
      });
    });
  }
}

/* Channel chat: WS preferred, fallback to AJAX */
let chWs = null;
let chConnected = false;
let chRoom = null;

function bindChannelChat(){
  const meta = document.querySelector('meta[name="channel-id"]');
  if (!meta) return;
  chRoom = meta.getAttribute('content');
  const sendBtn = document.getElementById('channelSendBtn');
  const input = document.getElementById('channelChatMsg');
  if (!sendBtn || !input) return;

  initChannelSocket();

  sendBtn.addEventListener('click', async function(){
    const message = input.value.trim();
    if (!message) return;
    const country = document.getElementById('chatCountryFlag')?.value || '';
    const avatar = (window.KM_USER && window.KM_USER.avatar) ? window.KM_USER.avatar : '';
    const payload = { channel_id: chRoom, user_name: (window.KM_USER?window.KM_USER.username:'guest'), message, country, avatar, user_id: (window.KM_USER?window.KM_USER.id:null), ts: new Date().toISOString() };

    // optimistic render
    appendChannelChatLine('You: ' + message, avatar, country, new Date().toISOString());

    if (chConnected && chWs) {
      try {
        chWs.send(JSON.stringify(payload));
        input.value = '';
        return;
      } catch(e){ /* fallback to AJAX */ }
    }

    // fallback: AJAX writer
    try {
      const res = await fetch('/ajax/channel_api.php?action=chat_send', {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
        body: JSON.stringify(payload)
      });
      const j = await res.json();
      if (j.ok) {
        input.value = '';
      } else {
        alert(j.error || 'Failed to send');
      }
    } catch(e){ alert('Network error'); }
  });
}

function initChannelSocket(){
  if (!chRoom) return;
  const proto = (location.protocol==='https:') ? 'wss' : 'ws';
  const wsUrl = proto + '://' + location.hostname + ':8080';
  try {
    chWs = new WebSocket(wsUrl);
    chWs.onopen = () => { chConnected = true; console.log('Channel WS connected'); };
    chWs.onmessage = ev => {
      try {
        const data = JSON.parse(ev.data);
        if (data.channel_id && String(data.channel_id) !== String(chRoom)) return;
        appendChannelChatLine((data.user_name || 'guest') + ': ' + data.message, data.user_avatar || '', data.country_code || '', data.ts || new Date().toISOString());
      } catch(e){ console.warn('WS message parse error', e); }
    };
    chWs.onclose = () => { chConnected = false; setTimeout(initChannelSocket, 2000); };
    chWs.onerror = () => { chConnected = false; chWs.close(); };
  } catch(e){ console.warn('WS init failed', e); }
}

function appendChannelChatLine(text, avatar, country, ts){
  const win = document.getElementById('channelChatWindow');
  if (!win) return;
  const d = document.createElement('div');
  d.className = 'chat-line';
  const avatarHtml = avatar ? `<img class="chat-avatar" src="${escapeHtml(avatar)}" alt="a" />` : `<div class="chat-avatar placeholder"></div>`;
  const flagHtml = country ? `<span class="chat-flag">${escapeHtml(countryToEmoji(country))}</span>` : '';
  const timeHtml = ts ? `<small class="chat-ts">${new Date(ts).toLocaleTimeString()}</small>` : '';
  d.innerHTML = `<div class="chat-left">${avatarHtml}</div><div class="chat-right"><div class="chat-text">${escapeHtml(text)} ${timeHtml}</div><div class="chat-meta">${flagHtml}</div></div>`;
  win.appendChild(d);
  win.scrollTop = win.scrollHeight;
}

function countryToEmoji(code){
  if (!code) return '';
  code = String(code).toUpperCase();
  if (code.length !== 2) return code;
  const A = 0x1F1E6;
  return String.fromCodePoint(A + code.charCodeAt(0) - 65) + String.fromCodePoint(A + code.charCodeAt(1) - 65);
}

function bindLiveBtn(){
  const btn = document.getElementById('liveBtn');
  if (!btn) return;
  btn.addEventListener('click', function(e){
    e.preventDefault();
    alert('Live streaming is a stub. Integrate RTMP ingestion + HLS packaging or a streaming provider to enable live.');
  });
}

function bindRedditStub(){
  const el = document.getElementById('redditChatLink');
  if (!el) return;
  el.addEventListener('click', async function(e){
    e.preventDefault();
    const channelId = document.querySelector('meta[name="channel-id"]').getAttribute('content');
    try {
      const res = await fetch('/ajax/channel_api.php?action=reddit_stub&channel_id=' + encodeURIComponent(channelId));
      const j = await res.json();
      if (j.ok && j.embed_url) {
        // open popup and offer copy link
        window.open(j.embed_url, '_blank', 'noopener');
        if (confirm('Open Reddit chat in new tab? Click Cancel to copy the link to clipboard.')) return;
        navigator.clipboard?.writeText(j.embed_url);
        alert('Link copied to clipboard');
      } else alert('Failed to create reddit stub');
    } catch(e){ alert('Network error'); }
  });
}

/* small utility */
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, function(m){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; }); }