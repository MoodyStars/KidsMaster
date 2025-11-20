// assets/js/upload_widget.js
// Resumable chunked uploader that talks to upload.php (chunked handler).
// - uses HTML5 File API
// - configurable chunk size
// - updates #uploadProgress UI and posts metadata after upload completes

(function(){
  const CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
  let currentXhr = null;

  function $(sel){ return document.querySelector(sel); }
  document.addEventListener('DOMContentLoaded', init);

  function init(){
    const start = $('#startUpload');
    const cancel = $('#cancelUpload');
    if (start) start.addEventListener('click', onStart);
    if (cancel) cancel.addEventListener('click', onCancel);
  }

  function onStart(e){
    e.preventDefault();
    const fileEl = $('#uploadFile');
    if (!fileEl || !fileEl.files || !fileEl.files[0]) return alert('Choose a file');
    const file = fileEl.files[0];
    const uploadId = 'u_' + Date.now().toString(36) + '_' + Math.random().toString(36).substring(2,8);
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    $('#uploadProgress').style.display = 'block';
    uploadChunk(file, uploadId, 0, totalChunks);
  }

  function onCancel(e){
    e.preventDefault();
    if (currentXhr) currentXhr.abort();
    $('#uploadProgress').style.display = 'none';
    $('#progressText').textContent = 'Cancelled';
  }

  function uploadChunk(file, uploadId, idx, totalChunks){
    const startPos = idx * CHUNK_SIZE;
    const endPos = Math.min(file.size, startPos + CHUNK_SIZE);
    const chunk = file.slice(startPos, endPos);
    const form = new FormData();
    form.append('upload_id', uploadId);
    form.append('chunk_index', idx);
    form.append('total_chunks', totalChunks);
    form.append('filename', file.name);
    form.append('mime', file.type);
    form.append('chunk', chunk);

    currentXhr = new XMLHttpRequest();
    currentXhr.open('POST', '/upload.php', true);
    currentXhr.setRequestHeader('X-CSRF-Token', window.KM_CSRF || '');
    currentXhr.onload = function(){
      if (this.status >= 200 && this.status < 300){
        const resp = JSON.parse(this.responseText || '{}');
        const pct = Math.round(((idx+1)/totalChunks)*100);
        document.querySelector('.bar-fill').style.width = pct + '%';
        document.getElementById('progressText').textContent = pct + '%';
        if (idx + 1 < totalChunks) {
          uploadChunk(file, uploadId, idx + 1, totalChunks);
        } else {
          // finalize: send metadata (title, desc, tags, channel)
          finalizeUpload(resp.file_url || '', resp.thumbnail || '');
        }
      } else {
        alert('Upload failed at chunk ' + idx);
      }
    };
    currentXhr.onerror = function(){ alert('Network error'); };
    currentXhr.send(form);
  }

  function finalizeUpload(fileUrl, thumbnailUrl) {
    const payload = {
      file_url: fileUrl,
      thumbnail: thumbnailUrl,
      title: document.getElementById('uploadTitle').value || '',
      description: document.getElementById('uploadDesc').value || '',
      tags: document.getElementById('uploadTags').value || '',
      channel_id: document.getElementById('uploadChannel').value || '',
      _csrf: window.KM_CSRF || ''
    };
    fetch('/ajax/upload_finalize.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    }).then(r=>r.json()).then(j=>{
      if (j.ok) {
        document.getElementById('progressText').textContent = 'Completed';
        location.href = '/watch.php?id=' + j.id;
      } else {
        alert(j.error || 'Finalizing upload failed');
      }
    }).catch(()=>alert('Network error finalizing upload'));
  }
})();