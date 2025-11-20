// assets/js/contact.js - AJAX contact form
document.addEventListener('DOMContentLoaded', function(){
  const f = document.getElementById('contactForm');
  if (!f) return;
  f.addEventListener('submit', function(e){
    e.preventDefault();
    const data = {
      name: document.getElementById('contactName').value,
      email: document.getElementById('contactEmail').value,
      subject: document.getElementById('contactSubject').value,
      body: document.getElementById('contactBody').value,
      _csrf: window.KM_CSRF || ''
    };
    fetch('/ajax/contact_api.php?action=submit', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data)
    }).then(r=>r.json()).then(j=>{
      const el = document.getElementById('contactResult');
      if (j.ok) el.innerHTML = '<div class="panel">Thanks — your message has been received.</div>';
      else el.innerHTML = '<div class="panel error">Error: ' + (j.error || 'Failed') + '</div>';
    }).catch(()=>{ alert('Network error'); });
  });
});