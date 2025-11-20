<?php
// videos.php - browse videos (type=video) with categories, tags, search, pagination
require_once __DIR__ . '/_includes/init.php';
require_once __DIR__ . '/_includes/header.php';
$pdo = km_db();

$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 24;
$offset = ($page-1)*$per;

$where = "WHERE m.type = 'video'";
$params = [];
if ($q) { $where .= ' AND (m.title LIKE :q OR m.description LIKE :q OR m.tags LIKE :q)'; $params[':q']="%$q%"; }
if ($category) {
    // search by category slug
    $where .= ' AND (m.category = :cat OR FIND_IN_SET(:cat, m.tags))';
    $params[':cat'] = $category;
}

$stmt = $pdo->prepare("SELECT m.* FROM media m $where ORDER BY m.created_at DESC LIMIT :lim OFFSET :off");
$stmt->bindValue(':lim', $per, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
foreach ($params as $k=>$v) if ($k!=='lim' && $k!=='off') $stmt->bindValue($k, $v);
$stmt->execute();
$results = $stmt->fetchAll();

$total = $pdo->prepare("SELECT COUNT(*) FROM media m $where");
foreach ($params as $k=>$v) $total->bindValue($k,$v);
$total->execute();
$totalCount = $total->fetchColumn();
$pages = ceil($totalCount / $per);
?>
<section class="panel">
  <h2>Videos</h2>
  <form method="get" class="search-form">
    <input name="q" placeholder="Search videos..." value="<?= km_esc($q) ?>">
    <input name="category" placeholder="Category" value="<?= km_esc($category) ?>">
    <button class="btn">Search</button>
  </form>

  <div class="thumb-grid">
    <?php foreach ($results as $r): ?>
      <article class="thumb"><a href="/watch.php?id=<?= (int)$r['id'] ?>"><img src="<?= km_esc($r['thumbnail']) ?>"><div class="thumb-meta"><h4><?= km_esc($r['title']) ?></h4><small><?= (int)$r['views'] ?> views</small></div></a></article>
    <?php endforeach; ?>
  </div>

  <div class="pagination">
    <?php for ($i=1;$i<=$pages;$i++): ?>
      <a href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>" class="<?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
</section>
<?php require_once __DIR__ . '/_includes/footer.php'; ?>