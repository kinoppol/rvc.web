<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';
require_installed();

$siteName = Settings::get('site_name', 'เว็บไซต์');
$navActive = 'units';
$popupScope = 'unit';

$id = (int) ($_GET['id'] ?? 0);
$st = Database::pdo()->prepare('SELECT * FROM units WHERE id = ?');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) {
    http_response_code(404);
    $u = null;
    $pageTitle = 'ไม่พบหน่วยงาน';
} else {
    $pageTitle = $u['name'];
}

$unitNews = [];
if ($u) {
    $st = Database::pdo()->prepare(
        'SELECT * FROM news WHERE unit_id = ? AND published_at <= NOW() ORDER BY published_at DESC LIMIT 6'
    );
    $st->execute([$id]);
    $unitNews = $st->fetchAll();
}

include __DIR__ . '/views/site_header.php';
?>
<div class="wrap">
  <?php if ($u): ?>
  <a class="back" href="<?= e(url('units.php')) ?>">‹ กลับสู่หน้าหน่วยงาน</a>
  <div class="unithead">
    <h1><?= e($u['name']) ?></h1>
    <?= $u['about'] !== '' ? '<p>' . e($u['about']) . '</p>' : '' ?>
  </div>

  <h2 class="sechead" style="font-size:21px">ข่าวและกิจกรรมของหน่วยงาน</h2>
  <?php if ($unitNews): ?>
  <div class="grid cols3">
    <?php foreach ($unitNews as $n): ?>
    <a class="card" href="<?= e(url('news_view.php?id=' . $n['id'])) ?>">
      <span class="body">
        <span class="chip"><?= e($n['category']) ?></span>
        <h3><?= e($n['title']) ?></h3>
        <p class="sum"><?= e(mb_strimwidth((string) $n['summary'], 0, 70, '…', 'UTF-8')) ?></p>
        <span class="date"><?= e(fmt_dt($n['published_at'])) ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">หน่วยงานนี้ยังไม่มีข่าวเผยแพร่</div>
  <?php endif; ?>

  <?php if ($u['contact_name'] !== '' || $u['contact_phone'] !== '' || $u['contact_email'] !== ''): ?>
  <div class="contactbox" style="margin-top:24px">
    <h3>ติดต่อหน่วยงาน</h3>
    <?php if ($u['contact_name'] !== ''): ?><p>ผู้ติดต่อ: <?= e($u['contact_name']) ?></p><?php endif; ?>
    <?php if ($u['contact_phone'] !== ''): ?><p>โทรศัพท์: <?= e($u['contact_phone']) ?></p><?php endif; ?>
    <?php if ($u['contact_email'] !== ''): ?><p>อีเมล: <?= e($u['contact_email']) ?></p><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty">ไม่พบหน่วยงานที่ต้องการดู — <a href="<?= e(url('units.php')) ?>">กลับสู่หน้าหน่วยงาน</a></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/views/site_footer.php'; ?>
