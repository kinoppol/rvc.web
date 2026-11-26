<?php
declare(strict_types=1);

/* หน้าเว็บเนื้อหาคงที่ (ประวัติ วิสัยทัศน์ ติดต่อ ฯลฯ) */

require __DIR__ . '/app/bootstrap.php';

$p = Database::one('SELECT * FROM pages WHERE slug = ?', [input('p')]);
if (!$p || (!$p['is_published'] && !Auth::user())) {
    abort(404, 'ไม่พบหน้านี้');
}
$navMap = ['about' => 'about', 'history' => 'about', 'vision' => 'about', 'structure' => 'about', 'executives' => 'about',
    'director-message' => 'about', 'curriculum' => 'curriculum', 'contact' => 'contact'];
$others = Database::all("SELECT slug, title FROM pages WHERE is_published = 1 AND slug IN ('about','history','vision','structure','executives','director-message') ORDER BY FIELD(slug,'about','history','vision','structure','executives','director-message')");

$pageTitle  = $p['title'];
$nav        = $navMap[$p['slug']] ?? '';
$popupWhere = 'site';
require BASE_PATH . '/app/views/header.php';
?>
<div class="page-h"><div class="wrap">
  <div class="crumb"><a href="<?= e(url()) ?>">หน้าแรก</a>›<span><?= e($p['title']) ?></span></div>
  <h1 class="hd"><?= e($p['title']) ?></h1>
</div></div>
<div class="wrap layout">
  <article class="main-col article">
    <?php if (!$p['is_published']): ?><div class="draft-banner">ตัวอย่าง — หน้านี้ยังไม่เผยแพร่</div><?php endif; ?>
    <div class="prose"><?= text_to_html((string) $p['body']) ?></div>
    <?php if ($p['slug'] === 'contact'): ?>
      <div class="box" style="margin-top:24px"><h3><?= e(Settings::get('site_name')) ?></h3>
        <p style="margin:0;line-height:1.9;color:var(--mute)"><?= nl2br(e(Settings::get('address', '[ที่อยู่วิทยาลัย]')), false) ?>
          <?php if ($ph = Settings::get('phone')): ?><br>โทร <?= e($ph) ?><?php endif; ?>
          <?php if ($em = Settings::get('email')): ?><br><a href="mailto:<?= e($em) ?>"><?= e($em) ?></a><?php endif; ?></p></div>
    <?php endif; ?>
  </article>
  <?php if (isset($navMap[$p['slug']]) && $navMap[$p['slug']] === 'about' && $others): ?>
  <aside><div class="box"><h3>เกี่ยวกับวิทยาลัย</h3>
    <?php foreach ($others as $o): ?><a class="row" href="<?= e(page_url($o['slug'])) ?>" <?= $o['slug'] === $p['slug'] ? 'aria-current="page" style="font-weight:700;color:var(--navy)"' : '' ?>><?= e($o['title']) ?></a><?php endforeach; ?>
  </div></aside>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/footer.php';
