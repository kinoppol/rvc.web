<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';
require_installed();

$siteName = Settings::get('site_name', 'เว็บไซต์');
$pageTitle = 'หน่วยงาน';
$navActive = 'units';
$popupScope = 'unit';

$units = Database::pdo()->query(
    'SELECT u.*, (SELECT COUNT(*) FROM news n WHERE n.unit_id = u.id AND n.published_at <= NOW()) AS news_count
     FROM units u ORDER BY u.sort_order, u.name'
)->fetchAll();

include __DIR__ . '/views/site_header.php';
?>
<div class="wrap">
  <h2 class="sechead">หน่วยงาน</h2>
  <?php if ($units): ?>
  <div class="grid cols3">
    <?php foreach ($units as $u): ?>
    <a class="card" href="<?= e(url('unit.php?id=' . $u['id'])) ?>">
      <span class="body">
        <h3><?= e($u['name']) ?></h3>
        <p class="sum"><?= e(mb_strimwidth((string) $u['about'], 0, 100, '…', 'UTF-8')) ?></p>
        <span class="chip"><?= (int) $u['news_count'] ?> ข่าว</span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">ยังไม่มีข้อมูลหน่วยงาน — เพิ่มได้จากระบบผู้ดูแล</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/views/site_footer.php'; ?>
