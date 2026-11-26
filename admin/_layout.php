<?php
/**
 * Layout ต้นสำหรับหน้า admin — ต้องตั้งค่า $adminTitle และผ่าน Auth::require() + Csrf::require() แล้ว
 * ใช้หลัง include bootstrap.php:
 *   $adminTitle = '...'; include admin/_layout.php;  ...เนื้อหา...  include admin/_layout_end.php;
 */
$adminTitle = $adminTitle ?? '';
$authUser = Auth::user();
$navActive = $navActive ?? '';
$menu = [
    'dashboard'  => ['index.php',      'แผงควบคุม'],
    'migrations' => ['migrations.php', 'Migrations'],
    'news'       => ['news.php',       'ข่าวสาร'],
    'popups'     => ['popups.php',     'ป๊อปอัพประกาศ'],
    'units'      => ['units.php',      'หน่วยงาน'],
    'settings'   => ['settings.php',   'การตั้งค่า'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($adminTitle) ?> — ระบบจัดการ</title>
<link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>">
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand">ระบบจัดการ
      <small><?= e(Settings::get('site_name', 'เว็บไซต์')) ?></small>
    </div>
    <nav>
      <?php foreach ($menu as $key => [$file, $label]): ?>
        <a href="<?= e(url('admin/' . $file)) ?>" class="<?= $navActive === $key ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('admin/logout.php')) ?>">ออกจากระบบ</a>
    </nav>
  </aside>
  <main class="content">
    <?php foreach (get_flashes() as $f): ?>
      <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
