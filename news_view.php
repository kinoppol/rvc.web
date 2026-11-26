<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';
require_installed();

$siteName = Settings::get('site_name', 'เว็บไซต์');
$navActive = 'news';
$popupScope = 'main';

$id = (int) ($_GET['id'] ?? 0);
$st = Database::pdo()->prepare(
    'SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id WHERE n.id = ?'
);
$st->execute([$id]);
$n = $st->fetch();
if (!$n || $n['published_at'] > date('Y-m-d H:i:s')) {
    http_response_code(404);
    $n = null;
    $pageTitle = 'ไม่พบข่าว';
} else {
    $pageTitle = $n['title'];
}

include __DIR__ . '/views/site_header.php';
?>
<div class="wrap">
  <?php if ($n): ?>
  <a class="back" href="<?= e(url('news.php')) ?>">‹ กลับสู่หน้าข่าวสาร</a>
  <article class="article">
    <h1><?= e($n['title']) ?></h1>
    <div class="meta">
      <span class="chip"><?= e($n['category']) ?></span>
      <?= $n['unit_name'] !== '' ? ' · ' . e($n['unit_name']) : '' ?> · <?= e(fmt_dt($n['published_at'])) ?>
    </div>
    <?php if ($n['image_path'] !== ''): ?>
      <img class="full" src="<?= e(url('data/uploads/' . $n['image_path'])) ?>" alt="<?= e($n['title']) ?>">
    <?php endif; ?>
    <div class="body"><?= text_to_html((string) $n['body']) ?></div>
  </article>
  <?php else: ?>
    <div class="empty">ไม่พบข่าวที่ต้องการดู — <a href="<?= e(url('news.php')) ?>">กลับสู่หน้าข่าวสาร</a></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/views/site_footer.php'; ?>
