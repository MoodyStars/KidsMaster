// assets/js/main.js (UPDATED)
// Adds WebSocket chat with fallback to polling, emoji + flag stubs, and secure CSRF handling for POST endpoints.

document.addEventListener('DOMContentLoaded', function(){
  loadChannels();
  initChatSocket();
});

let ws = null;
let wsConnected = false;
let chatRoom = null; // media id for watch page context

function initChatSocket() {
  // If on a watch page, set chatRoom by reading a global var set server-side or from URL param
  const meta = document.querySelector('meta[name="media-id"]');
  if (meta) chatRoom = meta.getAttribute('content');

  if (!chatRoom) return;

  const wsUrl = (location.protocol === 'https:' ? 'wss' : 'ws') + '://' + location.hostname + ':8080';
  try {
    ws = new WebSocket(wsUrl);
    ws.onopen = () => { wsConnected = true; console.log('WebSocket connected'); };
    ws.onmessage = (ev) => {
      const data = JSON.parse(ev.data);
      // optionally filter by media_id if implemented
      appendChatLine(`${data.user_name}: ${data.message}`);
    };
    ws.onclose = () => { wsConnected = false; setTimeout(initChatSocket, 2000); };
  } catch(e) {
    console.warn('WebSocket init failed, fallback to polling');
    pollChat(chatRoom);
  }
}

function sendChat(mediaId) {
  const msgEl = document.getElementById('chatMsg');
  if(!msgEl) return;
  const message = msgEl.value.trim();
  if(!message) return;
  const payload = { media_id: mediaId, user_name: (window.KM_USER ? window.KM_USER.username : 'guest'), message };
  if (wsConnected && ws) {
    ws.send(JSON.stringify(payload));
    msgEl.value = '';
    return;
  }
  // fallback to POST
  fetch('/api.php?rest=chat_send', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
    body: JSON.stringify(payload)
  }).then(r=>r.json()).then(res=>{
    if(res.ok){ msgEl.value=''; pollChat(mediaId); }
  });
}

function appendChatLine(text) {
  const win = document.getElementById('chatWindow');
  if (!win) return;
  const div = document.createElement('div');
  div.className = 'chat-line';
  div.textContent = text;
  win.appendChild(div);
  win.scrollTop = win.scrollHeight;
}

/* Emoji and flag UI - integrate pickers */
function openEmojiPicker(){ 
  // minimal example: insert a Unicode emoji at cursor
  const textarea = document.getElementById('commentText') || document.getElementById('chatMsg');
  if (!textarea) return;
  textarea.value += ' 😊';
}

/* Country flags: you can use libraries like flag-icon-css or Twemoji for rendering */
function selectCountryFlag(code) {
  const el = document.getElementById('countryFlag');
  if (!el) return;
  el.value = code;
}

/* Poll chat fallback (keeps old polling implementation) */
let lastChatId = 0;
let chatPollTimer = null;
function pollChat(mediaId){
  fetch('/api.php?rest=chat_poll&media_id='+encodeURIComponent(mediaId)+'&since='+lastChatId)
    .then(r=>r.json()).then(res=>{
      if(res.ok && res.messages){
        res.messages.forEach(m=>{
          appendChatLine(`${m.user_name}: ${m.message}`);
          lastChatId = Math.max(lastChatId, m.id);
        });
      }
    }).catch(()=>{});
  if (chatPollTimer) clearTimeout(chatPollTimer);
  chatPollTimer = setTimeout(()=>pollChat(mediaId), 2500);
}