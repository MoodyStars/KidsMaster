// assets/js/archive.js
function restoreChannel(form){
  const data = new FormData(form);
  fetch(form.action, {
    method:'POST',
    headers: {'X-CSRF-Token': window.KM_CSRF || ''},
    body: data
  }).then(r=>r.json()).then(res=>{
    if (res.ok) {
      // quick page refresh or remove element
      location.reload();
    } else {
      alert(res.error || 'Failed to restore');
    }
  });
  return false;
}