<?php
declare(strict_types=1);

require __DIR__ . '/core/bootstrap.php';
require_installed();

$siteName = Settings::get('site_name', 'เว็บไซต์');
$navActive = 'home';
$popupScope = 'home';

$lead = Database::pdo()->query(
    'SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id
     WHERE n.is_lead = 1 AND n.published_at <= NOW() ORDER BY n.published_at DESC LIMIT 1'
)->fetch() ?: null;

$news = Database::pdo()->query(
    'SELECT n.id, n.title, n.summary, n.category, n.image_path, n.published_at, u.name AS unit_name
     FROM news n LEFT JOIN units u ON u.id = n.unit_id
     WHERE n.published_at <= NOW() ORDER BY n.published_at DESC LIMIT 6'
)->fetchAll();

$units = Database::pdo()->query(
    'SELECT u.*, (SELECT COUNT(*) FROM news n WHERE n.unit_id = u.id AND n.published_at <= NOW()) AS news_count
     FROM units u ORDER BY u.sort_order, u.name'
)->fetchAll();

include __DIR__ . '/views/site_header.php';
?>
<section class="hero">
  <div class="inner">
    <h1><?= e($siteName) ?></h1>
    <p>ศูนย์กลางข้อมูลข่าวสาร ข่าวกิจกรรม ประกาศจัดซื้อจัดจ้าง และข้อมูลหน่วยงาน — จัดการจากระบบผู้ดูแล</p>
  </div>
</section>
<div class="wrap">

  <h2 class="sechead">ข่าวสาร</h2>
  <?php if ($lead): ?>
  <div class="grid cols2" style="margin-bottom:18px">
    <a class="card" href="<?= e(url('news_view.php?id=' . $lead['id'])) ?>">
      <span class="thumb"><?= $lead['image_path'] !== '' ? '<img src="' . e(url('data/uploads/' . $lead['image_path'])) . '" alt="">' : '' ?></span>
      <span class="body">
        <span class="chip">ข่าวเด่น · <?= e($lead['category']) ?></span>
        <h3><?= e($lead['title']) ?></h3>
        <p class="sum"><?= e($lead['summary']) ?></p>
        <span class="date"><?= e(fmt_dt($lead['published_at'])) ?></span>
      </span>
    </a>
    <div class="principal">
      <h2><?= e(Settings::get('principal_title', 'สารจากผู้อำนวยการ')) ?></h2>
      <?= text_to_html(Settings::get('principal_message')) ?>
    </div>
  </div>
  <?php else: ?>
    <div class="principal" style="margin-bottom:18px">
      <h2><?= e(Settings::get('principal_title', 'สารจากผู้อำนวยการ')) ?></h2>
      <?= text_to_html(Settings::get('principal_message')) ?>
    </div>
  <?php endif; ?>

  <?php if ($news): ?>
  <div class="grid cols3">
    <?php foreach ($news as $n): ?>
    <a class="card" href="<?= e(url('news_view.php?id=' . $n['id'])) ?>">
      <span class="thumb"><?= $n['image_path'] !== '' ? '<img src="' . e(url('data/uploads/' . $n['image_path'])) . '" alt="">' : '' ?></span>
      <span class="body">
        <span class="chip"><?= e($n['category']) ?></span>
        <h3><?= e($n['title']) ?></h3>
        <p class="sum"><?= e($n['summary']) ?></p>
        <span class="date"><?= $n['unit_name'] !== '' ? e($n['unit_name']) . ' · ' : '' ?><?= e(fmt_dt($n['published_at'])) ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
    <div class="empty">ยังไม่มีข่าวที่เผยแพร่ — เพิ่มได้จากระบบผู้ดูแล</div>
  <?php endif; ?>

  <?php if ($units): ?>
  <h2 class="sechead" style="margin-top:34px">หน่วยงาน</h2>
  <div class="grid cols3">
    <?php foreach ($units as $u): ?>
    <a class="card" href="<?= e(url('unit.php?id=' . $u['id'])) ?>">
      <span class="body">
        <h3><?= e($u['name']) ?></h3>
        <p class="sum"><?= e(mb_strimwidth((string) $u['about'], 0, 90, '…', 'UTF-8')) ?></p>
        <span class="chip"><?= (int) $u['news_count'] ?> ข่าว</span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/views/site_footer.php'; ?>
