<?php
// admin/jobs.php
// Admin dashboard to list encoding_jobs and perform retry/requeue/cancel/delete actions.

require_once __DIR__ . '/../_includes/init.php';
km_require_login();
$user = km_current_user();
$pdo = km_db();

// Only allow moderators (or admin user id 1)
if (empty($user['is_moderator']) && $user['id'] != 1) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Filters
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 40;
$offset = ($page - 1) * $per;

$where = "WHERE 1=1";
$params = [];
if ($status) { $where .= " AND status = :status"; $params[':status'] = $status; }

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM encoding_jobs $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, ceil($total / $per));

$stmt = $pdo->prepare("SELECT id, job_type, status, attempts, last_error, created_at, updated_at, payload FROM encoding_jobs $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

$page_title = "Admin Jobs";
require_once __DIR__ . '/../_includes/header.php';
?>
<section class="panel">
  <h2>Encoding Jobs</h2>

  <form method="get" style="margin-bottom:12px;">
    <label>Status:
      <select name="status">
        <option value="">All</option>
        <option value="queued" <?= $status==='queued' ? 'selected' : '' ?>>Queued</option>
        <option value="processing" <?= $status==='processing' ? 'selected' : '' ?>>Processing</option>
        <option value="done" <?= $status==='done' ? 'selected' : '' ?>>Done</option>
        <option value="failed" <?= $status==='failed' ? 'selected' : '' ?>>Failed</option>
      </select>
    </label>
    <button class="btn">Filter</button>
  </form>

  <table style="width:100%;border-collapse:collapse;">
    <thead>
      <tr>
        <th>ID</th>
        <th>Type</th>
        <th>Status</th>
        <th>Attempts</th>
        <th>Created</th>
        <th>Updated</th>
        <th>Payload</th>
        <th>Last Error</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
        <tr style="border-top:1px solid #eee;">
          <td><?= (int)$j['id'] ?></td>
          <td><?= km_esc($j['job_type']) ?></td>
          <td><?= km_esc($j['status']) ?></td>
          <td><?= (int)$j['attempts'] ?></td>
          <td><?= km_esc($j['created_at']) ?></td>
          <td><?= km_esc($j['updated_at']) ?></td>
          <td style="max-width:300px;overflow:auto;"><pre style="white-space:pre-wrap;"><?= km_esc(json_encode(json_decode($j['payload'], true), JSON_PRETTY_PRINT)) ?></pre></td>
          <td style="max-width:300px;overflow:auto;color:#900;"><pre style="white-space:pre-wrap;"><?= km_esc($j['last_error']) ?></pre></td>
          <td>
            <button class="btn" data-action="view" data-id="<?= (int)$j['id'] ?>">View</button>
            <button class="btn" data-action="retry" data-id="<?= (int)$j['id'] ?>">Retry</button>
            <button class="btn ghost" data-action="requeue" data-id="<?= (int)$j['id'] ?>">Requeue</button>
            <button class="btn ghost" data-action="cancel" data-id="<?= (int)$j['id'] ?>">Cancel</button>
            <button class="btn ghost" data-action="delete" data-id="<?= (int)$j['id'] ?>">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:12px;">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="btn <?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</section>

<!-- Modal / viewer -->
<div id="jobModal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:18px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.2);width:80%;max-width:900px;z-index:9999;">
  <button id="closeModal" style="float:right">Close</button>
  <div id="jobDetail"></div>
</div>

<script>
(function(){
  function postAction(action, id) {
    var fd = new FormData();
    fd.append('_csrf', window.KM_CSRF || '');
    fd.append('action', action);
    fd.append('job_id', id);
    return fetch('/ajax/admin_jobs_api.php', { method: 'POST', body: fd }).then(r=>r.json());
  }

  document.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-action');
    var id = btn.getAttribute('data-id');
    if (action === 'view') {
      fetch('/ajax/admin_jobs_api.php?action=view&job_id='+encodeURIComponent(id)).then(r=>r.json()).then(j=>{
        document.getElementById('jobDetail').innerHTML = '<pre>'+JSON.stringify(j, null, 2)+'</pre>';
        document.getElementById('jobModal').style.display = 'block';
      });
      return;
    }
    if (!confirm('Perform "'+action+'" on job '+id+'?')) return;
    postAction(action, id).then(function(res){
      if (res.ok) {
        alert('OK');
        location.reload();
      } else {
        alert(res.error || 'Failed');
      }
    }).catch(function(){ alert('Network error'); });
  });

  document.getElementById('closeModal').addEventListener('click', function(){ document.getElementById('jobModal').style.display = 'none'; });
})();
</script>

<?php require_once __DIR__ . '/../_includes/footer.php'; ?>