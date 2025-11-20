/* assets/js/upload_widget.legacy.js
   Legacy uploader compatible with older browsers (uses var, no Promises, XHR only).
   This is a conservative drop-in alternative to upload_widget.js for IE7-11 and older browsers.
*/
(function () {
  var CHUNK_SIZE = 2 * 1024 * 1024; // 2MB
  var currentXhr = null;

  function $(sel) { return document.querySelector(sel); }
  function onReady(fn) {
    if (document.readyState === 'complete' || document.readyState === 'interactive') return fn();
    if (document.addEventListener) document.addEventListener('DOMContentLoaded', fn);
    else document.attachEvent('onreadystatechange', function () { if (document.readyState === 'complete') fn(); });
  }

  onReady(function () {
    var start = document.getElementById('startUpload');
    var cancel = document.getElementById('cancelUpload');
    if (start) start.onclick = onStart;
    if (cancel) cancel.onclick = onCancel;
  });

  function onStart(e) {
    if (e && e.preventDefault) e.preventDefault();
    var fileEl = document.getElementById('uploadFile');
    if (!fileEl || !fileEl.files || !fileEl.files[0]) return alert('Choose a file');
    var file = fileEl.files[0];
    var uploadId = 'u_' + (new Date()).getTime().toString(36) + '_' + Math.random().toString(36).substring(2, 8);
    var totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    var prog = document.getElementById('uploadProgress');
    if (prog) prog.style.display = 'block';
    uploadChunk(file, uploadId, 0, totalChunks);
  }

  function onCancel(e) {
    if (e && e.preventDefault) e.preventDefault();
    if (currentXhr) try { currentXhr.abort(); } catch (ex) {}
    var prog = document.getElementById('uploadProgress');
    if (prog) { prog.style.display = 'none'; var t = document.getElementById('progressText'); if (t) t.innerText='Cancelled'; }
  }

  function uploadChunk(file, uploadId, idx, totalChunks) {
    var startPos = idx * CHUNK_SIZE;
    var endPos = Math.min(file.size, startPos + CHUNK_SIZE);
    var chunk = file.slice(startPos, endPos);
    var form = new FormData();
    form.append('upload_id', uploadId);
    form.append('chunk_index', idx);
    form.append('total_chunks', totalChunks);
    form.append('filename', file.name);
    form.append('mime', file.type || '');
    form.append('chunk', chunk);

    currentXhr = new XMLHttpRequest();
    currentXhr.open('POST', '/upload.php', true);
    try {
      currentXhr.setRequestHeader('X-CSRF-Token', window.KM_CSRF || '');
    } catch (e) {}
    currentXhr.onreadystatechange = function () {
      if (currentXhr.readyState !== 4) return;
      if (currentXhr.status >= 200 && currentXhr.status < 300) {
        var resp = {};
        try { resp = JSON.parse(currentXhr.responseText || '{}'); } catch (e) { resp = {}; }
        var pct = Math.round(((idx + 1) / totalChunks) * 100);
        var fill = document.querySelector('.bar-fill');
        if (fill) fill.style.width = pct + '%';
        var pt = document.getElementById('progressText');
        if (pt) pt.innerText = pct + '%';
        if (idx + 1 < totalChunks) {
          uploadChunk(file, uploadId, idx + 1, totalChunks);
        } else {
          finalizeUpload(resp.file_url || '', resp.thumbnail || '');
        }
      } else {
        alert('Upload failed at chunk ' + idx);
      }
    };
    currentXhr.onerror = function () { alert('Network error'); };
    currentXhr.send(form);
  }

  function finalizeUpload(fileUrl, thumbnailUrl) {
    var payload = {
      file_url: fileUrl,
      thumbnail: thumbnailUrl,
      title: (document.getElementById('uploadTitle') || { value: '' }).value,
      description: (document.getElementById('uploadDesc') || { value: '' }).value,
      tags: (document.getElementById('uploadTags') || { value: '' }).value,
      channel_id: (document.getElementById('uploadChannel') || { value: '' }).value,
      _csrf: window.KM_CSRF || ''
    };
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/ajax/upload_finalize.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          var j = JSON.parse(xhr.responseText || '{}');
          if (j.ok) {
            var pt = document.getElementById('progressText'); if (pt) pt.innerText = 'Completed';
            window.location = '/watch.php?id=' + j.id;
            return;
          }
        } catch (e) {}
      }
      alert('Finalizing upload failed');
    };
    xhr.send(JSON.stringify(payload));
  }
})();