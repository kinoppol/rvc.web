<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';
require_installed();

$siteName = Settings::get('site_name', 'เว็บไซต์');
$pageTitle = 'ข่าวสาร';
$navActive = 'news';
$popupScope = 'main';

$cats  = news_categories();
$cat   = trim((string) ($_GET['cat'] ?? ''));
$q     = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id
        WHERE n.published_at <= NOW()';
$args = [];
if ($cat !== '' && in_array($cat, $cats, true)) {
    $sql .= ' AND n.category = ?';
    $args[] = $cat;
}
if ($q !== '') {
    $sql .= ' AND n.title LIKE ?';
    $args[] = '%' . $q . '%';
}
$sql .= ' ORDER BY n.published_at DESC LIMIT 50';
$st = Database::pdo()->prepare($sql);
$st->execute($args);
$news = $st->fetchAll();

include __DIR__ . '/views/site_header.php';
?>
<div class="wrap">
  <h2 class="sechead">ข่าวสารและประกาศ</h2>
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาจากหัวข้อข่าว"
           style="flex:1 1 220px;min-height:44px;padding:8px 12px;border:1px solid #8796ad;border-radius:4px">
    <select name="cat" style="min-height:44px;padding:8px 12px;border:1px solid #8796ad;border-radius:4px">
      <option value="">ทุกหมวด</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
    <button style="min-height:44px;padding:8px 20px;border:0;border-radius:4px;background:#1f5aa6;color:#fff;font-weight:600;cursor:pointer">ค้นหา</button>
  </form>

  <?php if ($news): ?>
  <div class="grid cols3">
    <?php foreach ($news as $n): ?>
    <a class="card" href="<?= e(url('news_view.php?id=' . $n['id'])) ?>">
      <span class="thumb"><?= $n['image_path'] !== '' ? '<img src="' . e(url('data/uploads/' . $n['image_path'])) . '" alt="">' : '' ?></span>
      <span class="body">
        <span class="chip"><?= e($n['category']) ?></span>
        <h3><?= e($n['title']) ?></h3>
        <p class="sum"><?= e(mb_strimwidth((string) $n['summary'], 0, 80, '…', 'UTF-8')) ?></p>
        <span class="date"><?= $n['unit_name'] !== '' ? e($n['unit_name']) . ' · ' : '' ?><?= e(fmt_dt($n['published_at'])) ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">ไม่พบข่าวตามเงื่อนไขที่ค้นหา</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/views/site_footer.php'; ?>
