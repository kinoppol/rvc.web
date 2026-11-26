<?php
/** ส่วนหัวหน้าเว็บสาธารณะ — ต้องมี $pageTitle และ $siteName ก่อน include */
$pageTitle = $pageTitle ?? '';
$siteName  = $siteName ?? 'เว็บไซต์';
$navActive = $navActive ?? '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle !== '' ? $pageTitle . ' — ' . $siteName : $siteName) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/site.css')) ?>">
</head>
<body>
<div class="topbar">
  <div class="inner">
    <span>เว็บไซต์เพื่อการจัดการข้อมูลข่าวสารขององค์กร</span>
    <a href="<?= e(url('admin/index.php')) ?>">ผู้ดูแลระบบ</a>
  </div>
</div>
<header class="sitehead">
  <div class="inner">
    <div class="logo">ว</div>
    <div>
      <h1><?= e($siteName) ?></h1>
      <small>ข้อมูลข่าวสาร ข่าวกิจกรรม และบริการของหน่วยงาน</small>
    </div>
  </div>
</header>
<nav class="mainnav">
  <div class="inner">
    <a href="<?= e(url('index.php')) ?>" class="<?= $navActive === 'home' ? 'active' : '' ?>">หน้าแรก</a>
    <a href="<?= e(url('news.php')) ?>" class="<?= $navActive === 'news' ? 'active' : '' ?>">ข่าวสาร</a>
    <a href="<?= e(url('units.php')) ?>" class="<?= $navActive === 'units' ? 'active' : '' ?>">หน่วยงาน</a>
  </div>
</nav>
<main>
