// assets/js/groups.js - client interactions for groups
document.addEventListener('DOMContentLoaded', function(){
  bindCreateForm();
  bindJoinLeave();
});

function bindCreateForm(){
  const f = document.getElementById('createGroupForm');
  if (!f) return;
  f.addEventListener('submit', async function(e){
    e.preventDefault();
    const data = new FormData(f);
    try {
      const res = await fetch(f.action, { method:'POST', body: data });
      const j = await res.json();
      if (j.ok && j.redirect) {
        window.location = j.redirect;
      } else {
        alert(j.error || 'Failed to create group');
      }
    } catch (err) { alert('Network error'); }
  });
}

function bindJoinLeave(){
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('#joinBtn');
    if (!btn) return;
    ev.preventDefault();
    const gid = btn.dataset.group;
    const member = btn.dataset.member === '1';
    const action = member ? 'leave' : 'join';
    fetch('/ajax/groups_api.php?action=' + action, {
      method:'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token': window.KM_CSRF || ''},
      body: 'group_id='+encodeURIComponent(gid)+'&_csrf='+encodeURIComponent(window.KM_CSRF || '')
    }).then(r=>r.json()).then(j=>{
      if (j.ok) {
        btn.dataset.member = member ? '0' : '1';
        btn.textContent = member ? 'Join Group' : 'Leave Group';
        location.reload();
      } else alert(j.error || 'Failed');
    }).catch(()=>alert('Network error'));
  });
}