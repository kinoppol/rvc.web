<?php
declare(strict_types=1);

/* หน่วยงานทั้งหมด — แผนกวิชาตามกลุ่ม และฝ่าย/งาน */

require __DIR__ . '/app/bootstrap.php';

$groups    = departments_by_group();
$divisions = divisions_with_works();

$pageTitle  = 'หน่วยงาน';
$nav        = 'units';
$popupWhere = 'site';
require BASE_PATH . '/app/views/header.php';
?>
<div class="page-h"><div class="wrap">
  <div class="crumb"><a href="<?= e(url()) ?>">หน้าแรก</a>›<span>หน่วยงาน</span></div>
  <h1 class="hd">หน่วยงานและแผนกวิชา</h1>
</div></div>
<section class="sec" style="padding-top:48px">
  <div class="wrap">
    <div class="sec-h"><h2 class="hd">แผนกวิชา</h2></div>
    <div class="depts">
      <?php foreach ($groups as $key => $depts): $g = unit_group($key); ?>
        <div class="dept-g" style="border-top-color:<?= e($g['color']) ?>">
          <div class="hd gn" style="color:<?= e($g['color']) ?>"><?= e($g['label']) ?></div>
          <div style="margin-top:10px"><?php foreach ($depts as $d): ?><a class="ul" href="<?= e(unit_url($d)) ?>"><?= e($d['name']) ?><span aria-hidden="true">→</span></a><?php endforeach; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<section class="sec alt">
  <div class="wrap">
    <div class="sec-h"><h2 class="hd">ฝ่ายและงาน</h2></div>
    <div class="depts">
      <?php foreach ($divisions as $d): ?>
        <div class="dept-g" style="border-top-color:#1f5aa6">
          <a class="hd gn" href="<?= e(unit_url($d)) ?>" style="color:#1f5aa6;border:0;padding:0;display:block;font-size:1.25rem"><?= e($d['name']) ?></a>
          <div style="margin-top:10px"><?php foreach ($d['works'] as $w): ?><a class="ul" href="<?= e(unit_url($w)) ?>"><?= e($w['name']) ?><span aria-hidden="true">→</span></a><?php endforeach; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require BASE_PATH . '/app/views/footer.php';
