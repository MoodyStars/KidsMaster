<?php
// upload_files.php - batch upload page for power users (uses upload_widget.js)
require_once __DIR__ . '/_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

$stmt = $pdo->prepare("SELECT id,name FROM channels WHERE owner_id = :uid");
$stmt->execute([':uid'=>$user['id']]);
$channels = $stmt->fetchAll();

require_once __DIR__ . '/_includes/header.php';
?>
<section class="panel">
  <h2>Batch Upload</h2>
  <p>You can upload multiple files in succession. Each finished upload will be finalized and added to the selected channel.</p>
  <form id="batchUploadForm" onsubmit="return false;">
    <?= km_csrf_field() ?>
    <label>Channel
      <select id="batchChannel">
        <?php foreach ($channels as $c): ?><option value="<?= (int)$c['id'] ?>"><?= km_esc($c['name']) ?></option><?php endforeach; ?>
      </select>
    </label><br>
    <label>Files (select one at a time or multiple depending on browser)<br><input id="batchFiles" type="file" multiple></label><br>
    <button class="btn" id="startBatch">Start Upload</button>
  </form>

  <div id="batchStatus"></div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const start = document.getElementById('startBatch');
  start.addEventListener('click', function(){
    const files = document.getElementById('batchFiles').files;
    if (!files || files.length === 0) return alert('Choose files');
    const channel = document.getElementById('batchChannel').value;
    // simple sequential upload using upload_widget logic
    (function uploadIndex(i){
      if (i >= files.length) return alert('All done');
      const f = files[i];
      const uploadId = 'batch_'+Date.now()+'_'+i;
      // reuse global upload logic by posting directly to upload.php chunks in 2MB parts.
      const chunkSize = 2*1024*1024;
      let idx = 0;
      function sendChunk(){
        const startPos = idx*chunkSize;
        const end = Math.min(f.size, startPos + chunkSize);
        const chunk = f.slice(startPos, end);
        const fd = new FormData();
        fd.append('upload_id', uploadId);
        fd.append('chunk_index', idx);
        fd.append('total_chunks', Math.ceil(f.size/chunkSize));
        fd.append('filename', f.name);
        fd.append('mime', f.type);
        fd.append('chunk', chunk);
        const xhr = new XMLHttpRequest();
        xhr.open('POST','/upload.php',true);
        xhr.onload = function(){
          if (xhr.status >= 200 && xhr.status < 300) {
            idx++;
            if (startPos + chunkSize >= f.size) {
              // finalize via ajax/upload_finalize.php
              fetch('/ajax/upload_finalize.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ file_url: (JSON.parse(xhr.responseText||'{}').file_url||''), thumbnail: (JSON.parse(xhr.responseText||'{}').thumbnail||''), title: f.name, description: '', tags: '', channel_id: channel, _csrf: window.KM_CSRF })
              }).then(r=>r.json()).then(j=>{
                document.getElementById('batchStatus').innerHTML += '<div>'+f.name+': '+(j.ok?'ok':'error')+'</div>';
                uploadIndex(i+1);
              });
            } else {
              sendChunk();
            }
          } else alert('Chunk upload error');
        };
        xhr.onerror = function(){ alert('Network error'); };
        xhr.send(fd);
      }
      sendChunk();
    })(0);
  });
});
</script>

<?php require_once __DIR__ . '/_includes/footer.php'; ?>