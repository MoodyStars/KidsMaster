// assets/js/channel.js
// Handles subscribe/unsubscribe, channel WebSocket chat (room-aware), emoji & flag stubs and reddit-chat stub.

document.addEventListener('DOMContentLoaded', function(){
  bindSubscribe();
  bindChannelChat();
  bindLiveBtn();
  bindRedditStub();
});

function bindSubscribe(){
  const btn = document.getElementById('subscribeBtn');
  if (!btn) return;
  btn.addEventListener('click', function(){
    const channelId = this.dataset.channel;
    const subscribed = this.dataset.subs === '1';
    const action = subscribed ? 'unsubscribe' : 'subscribe';
    fetch('/ajax/channel_api.php?action=' + action, {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.KM_CSRF || ''},
      body: 'channel_id='+encodeURIComponent(channelId)+'&_csrf='+encodeURIComponent(window.KM_CSRF || '')
    }).then(r=>r.json()).then(res=>{
      if (res.ok) {
        this.dataset.subs = subscribed ? '0' : '1';
        this.textContent = subscribed ? 'Subscribe' : 'Subscribed';
      } else alert(res.error || 'Failed');
    });
  });
}

/* Channel chat: attempt WebSocket to room-aware server, fallback to polling via ajax */
let chWs = null;
let chConnected = false;
let chRoom = null;

function bindChannelChat(){
  const meta = document.querySelector('meta[name="channel-id"]');
  if (!meta) return;
  chRoom = meta.getAttribute('content');
  const win = document.getElementById('channelChatWindow');
  const sendBtn = document.getElementById('channelSendBtn');
  const input = document.getElementById('channelChatMsg');
  if (!sendBtn || !input) return;

  initChannelSocket();

  sendBtn.addEventListener('click', function(){
    const message = input.value.trim();
    if (!message) return;
    const payload = { channel_id: chRoom, user_name: (window.KM_USER?window.KM_USER.username:'guest'), message };
    if (chConnected && chWs) {
      chWs.send(JSON.stringify(payload));
      input.value = '';
      appendChannelChatLine('You: ' + message);
      return;
    }
    // fallback
    fetch('/ajax/channel_api.php?action=chat_send', {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
      body: JSON.stringify({ channel_id: chRoom, message })
    }).then(r=>r.json()).then(res=>{
      if (res.ok) { input.value=''; appendChannelChatLine('You: '+message); }
      else alert('Failed to send');
    });
  });
}

function initChannelSocket(){
  if (!chRoom) return;
  const wsUrl = (location.protocol === 'https:' ? 'wss' : 'ws') + '://' + location.hostname + ':8080';
  try {
    chWs = new WebSocket(wsUrl);
    chWs.onopen = () => { chConnected = true; console.log('channel WS open'); };
    chWs.onmessage = ev => {
      try {
        const data = JSON.parse(ev.data);
        // only accept messages for this channel_id
        if (data.channel_id && String(data.channel_id) !== String(chRoom)) return;
        appendChannelChatLine((data.user_name || 'guest') + ': ' + data.message);
      } catch(e){}
    };
    chWs.onclose = () => { chConnected = false; setTimeout(initChannelSocket, 3000); };
  } catch(e){ console.warn('WS fail', e); }
}

function appendChannelChatLine(text){
  const win = document.getElementById('channelChatWindow');
  if (!win) return;
  const d = document.createElement('div');
  d.className = 'chat-line';
  d.textContent = text;
  win.appendChild(d);
  win.scrollTop = win.scrollHeight;
}

function openEmojiPicker(){ 
  const input = document.getElementById('channelChatMsg');
  if (!input) return;
  input.value += ' 😊';
}

function bindLiveBtn(){
  const btn = document.getElementById('liveBtn');
  if (!btn) return;
  btn.addEventListener('click', function(e){
    e.preventDefault();
    alert('Live streaming support is a stub — integrate with your streaming backend (RTMP/HLS) to enable live.');
  });
}

function bindRedditStub(){
  const el = document.getElementById('redditChatLink');
  if (!el) return;
  el.addEventListener('click', function(e){
    e.preventDefault();
    // stub: create or link to a reddit thread/chat; in production implement OAuth & reddit API integration
    alert('Reddit chat stub — integrate Reddit API and embed via oEmbed or link to subreddit/chat.');
  });
}