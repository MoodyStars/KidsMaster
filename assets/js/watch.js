// assets/js/watch.js
// Handles threaded comment posting, replying, reporting, deletion (moderator) and emoji picker integration.

document.addEventListener('DOMContentLoaded', function(){
  initEmoji();
});

function initEmoji(){
  if (window.EmojiButton) {
    const picker = new EmojiButton({position:'top-end'});
    document.querySelectorAll('.emoji-btn').forEach(btn=>{
      btn.addEventListener('click', () => {
        picker.togglePicker(btn);
        picker.on('emoji', selection => {
          const target = document.getElementById(btn.dataset.target || 'commentText');
          if (target) target.value += selection.emoji;
        });
      });
    });
  } else {
    document.querySelectorAll('.emoji-btn').forEach(btn=>{
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target || 'commentText');
        if (target) target.value += ' 😊';
      });
    });
  }
}

function postComment(evt, mediaId){
  evt.preventDefault();
  const body = document.getElementById('commentText').value.trim();
  if (!body) return false;
  const country = document.getElementById('commentCountryFlag')?.value || '';
  const parentId = document.getElementById('reply_parent_id')?.value || null;
  fetch('/comment_post.php?action=post', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
    body: JSON.stringify({media_id: mediaId, body: body, parent_id: parentId, country: country})
  }).then(r=>r.json()).then(j=>{
    if (j.ok) {
      // append to top-level or under parent (simple refresh for reliability)
      location.reload();
    } else {
      alert(j.error || 'Failed to post');
    }
  });
  return false;
}

function replyTo(commentId, author){
  // create a small reply form or set parent id hidden field and focus
  let f = document.getElementById('commentForm');
  if (!f) return alert('Please log in to reply');
  let hidden = document.getElementById('reply_parent_id');
  if (!hidden) {
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.id = 'reply_parent_id';
    hidden.name = 'parent_id';
    f.appendChild(hidden);
  }
  hidden.value = commentId;
  document.getElementById('commentText').focus();
  window.scrollTo(0, document.getElementById('commentText').offsetTop - 120);
}

function reportComment(commentId){
  if (!confirm('Report this comment?')) return;
  fetch('/comment_post.php?action=report', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
    body: JSON.stringify({comment_id: commentId, reason: 'inappropriate'})
  }).then(r=>r.json()).then(j=>{
    if (j.ok) alert('Reported');
    else alert(j.error || 'Failed');
  });
}

function deleteComment(commentId){
  if (!confirm('Delete comment?')) return;
  fetch('/comment_post.php?action=delete', {
    method:'POST',
    headers:{'Content-Type':'application/json','X-CSRF-Token': window.KM_CSRF || ''},
    body: JSON.stringify({comment_id: commentId})
  }).then(r=>r.json()).then(j=>{
    if (j.ok) location.reload();
    else alert(j.error || 'Failed');
  });
}