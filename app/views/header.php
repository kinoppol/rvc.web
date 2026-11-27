<?php
/**
 * ส่วนหัวเว็บไซต์หลัก
 * @var string $pageTitle  ชื่อหน้า (ว่าง = หน้าแรก)
 * @var string $nav        เมนูที่เลือก: home|about|units|curriculum|news|contact
 * @var string $popupWhere home|site|unit (ตำแหน่งสำหรับเลือกป๊อปอัพ)
 * @var ?string $metaDesc
 */
$siteName = Settings::get('site_name', 'วิทยาลัยอาชีวศึกษาร้อยเอ็ด');
$nav      = $nav ?? '';
$megaCols = divisions_with_works();
$deptAll  = departments_by_group();
$quick    = array_filter(['ITA' => Settings::get('link_ita') ?: page_url('ita'), 'ร้องเรียน' => Settings::get('link_complaint'), 'ระบบ RMS' => Settings::get('link_rms')]);
$applyUrl = Settings::get('apply_url') ?: page_url('curriculum');
$menu = [
    'home'       => ['หน้าแรก', url()],
    'about'      => ['เกี่ยวกับวิทยาลัย', page_url('about')],
    'units'      => ['หน่วยงาน', url('units.php')],
    'curriculum' => ['หลักสูตร', page_url('curriculum')],
    'news'       => ['ข่าวสาร', url('news.php')],
    'contact'    => ['ติดต่อ', page_url('contact')],
];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' · ' . $siteName : $siteName) ?></title>
<meta name="description" content="<?= e($metaDesc ?? Settings::get('hero_lead')) ?>">
<meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;500;600;700&family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(url('assets/site.css')) ?>?v=5">
<link rel="icon" type="image/png" href="<?= e(url('assets/seal.png')) ?>">
</head>
<body>
<a class="skip" href="#main">ข้ามไปเนื้อหาหลัก</a>

<div class="topbar"><div class="wrap">
  <span><?= e(thai_date(time(), 'full')) ?></span>
  <div class="topbar-r">
    <?php foreach ($quick as $label => $href): ?><a class="desk ul" href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
    <span class="desk sep">|</span>
    <span class="fs" role="group" aria-label="ขนาดตัวอักษร">
      <button type="button" data-fs="-1" aria-label="ตัวอักษรเล็กลง">ก-</button><button type="button" data-fs="0" aria-label="ตัวอักษรปกติ">ก</button><button type="button" data-fs="1" aria-label="ตัวอักษรใหญ่ขึ้น">ก+</button>
    </span>
    <span class="sep">|</span>
    <?php if (Auth::user()): ?>
      <a class="ul login-link" href="<?= e(url('admin/')) ?>">ระบบจัดการ</a>
    <?php else: ?>
      <a class="ul login-link" href="<?= e(url('admin/login.php')) ?>">เข้าสู่ระบบ</a>
    <?php endif; ?>
  </div>
</div></div>

<header class="site-head" id="top">
  <div class="wrap head-in">
    <a class="logo" href="<?= e(url()) ?>">
      <img class="seal" src="<?= e(url('assets/seal.png')) ?>" alt="ตราสำนักงานคณะกรรมการการอาชีวศึกษา">
      <div class="logo-t"><div class="hd"><?= e($siteName) ?></div><div class="en"><?= e(Settings::get('site_name_en')) ?></div></div>
    </a>
    <nav class="mainnav desk" aria-label="เมนูหลัก">
      <?php foreach ($menu as $key => [$label, $href]): ?>
        <?php if ($key === 'units'): ?>
          <button type="button" class="mega-btn <?= $nav === $key ? 'on' : '' ?>" aria-expanded="false" aria-controls="mega"><?= e($label) ?><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
        <?php else: ?>
          <a href="<?= e($href) ?>" class="<?= $nav === $key ? 'on' : 'ul' ?>" <?= $nav === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="head-r">
      <a class="icon-btn" href="<?= e(url('news.php')) ?>#q" aria-label="ค้นหา"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></a>
      <a class="btn-apply desk" href="<?= e($applyUrl) ?>">สมัครเรียน</a>
      <button type="button" class="mob menu-btn" aria-expanded="false" aria-controls="drawer">เมนู<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    </div>
  </div>
  <div class="mega" id="mega" hidden>
    <div class="wrap mega-grid">
      <div><div class="hd mega-t">แผนกวิชา</div>
        <?php foreach ($deptAll as $depts): foreach ($depts as $d): ?><a class="ul" href="<?= e(unit_url($d)) ?>"><?= e($d['name']) ?></a><?php endforeach; endforeach; ?></div>
      <?php foreach ($megaCols as $col): ?>
        <div><a class="hd mega-t" href="<?= e(unit_url($col)) ?>"><?= e($col['name']) ?></a>
          <?php foreach ($col['works'] as $w): ?><a class="ul" href="<?= e(unit_url($w)) ?>"><?= e($w['name']) ?></a><?php endforeach; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<div class="drawer" id="drawer" hidden>
  <nav aria-label="เมนูมือถือ">
    <?php foreach ($menu as $key => [$label, $href]): ?>
      <a href="<?= e($href) ?>" class="<?= $nav === $key ? 'on' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a class="btn-apply" href="<?= e($applyUrl) ?>">สมัครเรียน</a>
    <?php foreach ($quick as $label => $href): ?><a class="sm" href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
  </nav>
</div>
<main id="main">
