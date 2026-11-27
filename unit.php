<?php
declare(strict_types=1);

/* เว็บไซต์ย่อยหน่วยงาน — เทมเพลตเดียวใช้ได้ทุกหน่วยงาน (ตามดีไซน์ Unit.dc.html) */

require __DIR__ . '/app/bootstrap.php';

$u = Database::one('SELECT u.*, p.name AS parent_name, p.slug AS parent_slug FROM units u LEFT JOIN units p ON p.id = u.parent_id
                    WHERE u.slug = ? AND u.is_active = 1', [input('u')]);
if (!$u) {
    abort(404, 'ไม่พบหน่วยงานนี้');
}
$g      = unit_group($u['group_key']);
$isDept = $u['kind'] === 'department';
$title  = unit_title($u);
$news   = latest_news(6, null, (int) $u['id']);
$works  = $u['kind'] === 'division' ? Database::all("SELECT * FROM units WHERE parent_id = ? AND is_active = 1 ORDER BY sort, name", [$u['id']]) : [];
$people = Database::all("SELECT display_name, role FROM users WHERE unit_id = ? AND is_active = 1 ORDER BY role, display_name", [$u['id']]);
$parentLabel = $isDept ? 'แผนกวิชา' : ($u['parent_name'] ?? UNIT_KINDS[$u['kind']] ?? 'หน่วยงาน');
$parentHref  = $u['parent_slug'] ? unit_url(['slug' => $u['parent_slug']]) : url('units.php');

$sections = $isDept
    ? ['about' => 'หน้าแรกแผนก', 'news' => 'ข่าวและกิจกรรม', 'contact' => 'ติดต่อ']
    : ['about' => 'หน้าแรกหน่วยงาน'] + ($works ? ['works' => 'งานในสังกัด'] : []) + ['news' => 'ข่าวและประกาศ', 'contact' => 'ติดต่อ'];

$pageTitle  = $title;
$metaDesc   = str_limit((string) $u['intro'], 160) ?: null;
$nav        = 'units';
$popupWhere = 'unit';
require BASE_PATH . '/app/views/header.php';
?>
<section class="unit-hero" style="background:<?= e($g['tint']) ?>">
  <div class="bar" aria-hidden="true" style="background:<?= e($g['color']) ?>"></div>
  <div class="deco" aria-hidden="true"><div style="height:180px;background:<?= e($g['color']) ?>"></div><div style="height:240px;background:<?= e($g['color']) ?>"></div><div style="height:200px;background:<?= e($g['color']) ?>"></div></div>
  <div class="wrap">
    <div class="unit-ic" style="background:<?= e($g['color']) ?>"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($g['icon']) ?>"/></svg></div>
    <div class="tx">
      <div class="crumb"><a href="<?= e(url()) ?>">หน้าแรก</a>›<a href="<?= e($parentHref) ?>"><?= e($parentLabel) ?></a>›<span><?= e($u['name']) ?></span></div>
      <h1 class="hd"><?= e($title) ?></h1>
      <?php if ($u['name_en']): ?><div class="en" style="color:<?= e($g['color']) ?>"><?= e($u['name_en']) ?></div><?php endif; ?>
    </div>
  </div>
</section>

<nav class="unit-nav" aria-label="เมนูหน่วยงาน"><div class="wrap">
  <?php foreach ($sections as $id => $label): ?><a href="#<?= $id ?>"><?= e($label) ?></a><?php endforeach; ?>
  <a href="<?= e(url('news.php', ['u' => $u['slug']])) ?>">ข่าวทั้งหมด</a>
</div></nav>

<div class="wrap layout">
  <div class="main-col">
    <div class="about unit-sec" id="about">
      <div class="img"<?= $u['cover'] ? ' data-sk' : '' ?> style="background-color:<?= e($g['tint']) ?>;<?= $u['cover'] ? 'background-image:url(' . e(upload_url($u['cover'])) . ')' : 'background-image:repeating-linear-gradient(135deg,transparent 0 10px,rgba(19,33,58,.06) 10px 11px)' ?>"><?= $u['cover'] ? '' : 'ภาพหน้าปกหน่วยงาน' ?></div>
      <div class="tx">
        <h2>เกี่ยวกับ<?= $isDept ? 'แผนก' : 'หน่วยงาน' ?></h2>
        <div class="prose" style="font-size:1rem;color:var(--mute)"><?= $u['intro'] ? text_to_html($u['intro']) : '<p>[คำแนะนำหน่วยงาน — ผู้ดูแลแก้ไขได้จากระบบจัดการ › เว็บไซต์ย่อยหน่วยงาน]</p>' ?></div>
        <?php if ($isDept): ?>
          <div class="chips"><?php foreach (['ปวช.', 'ปวส.', 'ทวิภาคี'] as $c): ?><span style="background:<?= e($g['tint']) ?>;color:<?= e($g['color']) ?>;border:1px solid <?= e($g['color']) ?>33"><?= $c ?></span><?php endforeach; ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($works): ?>
    <section class="unit-sec" id="works">
      <h2>งานในสังกัด</h2>
      <div class="cards">
        <?php foreach ($works as $w): ?>
          <a class="card" href="<?= e(unit_url($w)) ?>"><div class="c"><div class="t"><?= e($w['name']) ?></div><div class="m"><?= e(str_limit((string) $w['intro'], 80) ?: 'ดูหน้าหน่วยงาน →') ?></div></div></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="unit-sec" id="news">
      <div class="h"><h2 style="margin:0">ข่าวและกิจกรรมของหน่วยงาน</h2><a href="<?= e(url('news.php', ['u' => $u['slug']])) ?>" style="font-weight:600;text-decoration:none;font-size:.9375rem">ทั้งหมด ›</a></div>
      <?php if (!$news): ?><p class="empty">ยังไม่มีข่าวของหน่วยงานนี้</p><?php endif; ?>
      <div class="cards">
        <?php foreach ($news as $n): ?>
          <a class="card" href="<?= e(news_url($n)) ?>">
            <div class="img"<?= news_cover_attr($n) ?>></div>
            <div class="c"><div class="m"><?= e(thai_date($n['published_at'], 'long')) ?></div><div class="t"><?= e($n['title']) ?></div></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <?php if ($people): ?>
    <section class="unit-sec">
      <h2>ผู้ดูแลเว็บไซต์หน่วยงาน</h2>
      <p style="color:var(--mute)"><?= e(implode(' · ', array_filter(array_column($people, 'display_name')))) ?></p>
    </section>
    <?php endif; ?>
  </div>

  <aside>
    <div class="box unit-sec" id="contact">
      <h3>ติดต่อหน่วยงาน</h3>
      <div style="font-size:.9375rem;line-height:1.9;color:var(--mute)"><?= $u['contact'] ? nl2br(e($u['contact']), false) : 'อาคาร [ชื่ออาคาร] ชั้น [0]<br>โทร [000-000-000]<br>[อีเมลหน่วยงาน]' ?></div>
      <?php if ($email = Settings::get('email')): ?>
        <a class="btn-unit" style="background:<?= e($g['color']) ?>" href="mailto:<?= e($email) ?>?subject=<?= rawurlencode('ถึง' . $title) ?>">ส่งข้อความถึงหน่วยงาน</a>
      <?php endif; ?>
    </div>
    <?php if ($u['parent_name']): ?>
      <div class="box"><h3>สังกัด</h3><a class="row" href="<?= e($parentHref) ?>"><?= e($u['parent_name']) ?> →</a></div>
    <?php endif; ?>
  </aside>
</div>
<?php require BASE_PATH . '/app/views/footer.php';
