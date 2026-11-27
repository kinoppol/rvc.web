<?php
declare(strict_types=1);

/* อ่านข่าว — ผู้ที่เข้าสู่ระบบดูตัวอย่างข่าวที่ยังไม่เผยแพร่ได้ (?preview=1) */

require __DIR__ . '/app/bootstrap.php';

$id = input_int('id');
$n  = Database::one('SELECT n.*, u.name AS unit_name, u.kind AS unit_kind, u.slug AS unit_slug, u.group_key
                     FROM news n LEFT JOIN units u ON u.id = n.unit_id WHERE n.id = ?', [$id]);
$isPublic = $n && $n['status'] === 'published' && $n['published_at'] && strtotime($n['published_at']) <= time();
if (!$n || (!$isPublic && !Auth::user())) {
    abort(404, 'ไม่พบข่าวนี้ หรือข่าวยังไม่เผยแพร่');
}
if ($isPublic && empty($_SESSION['_viewed'][$id])) {
    Database::run('UPDATE news SET views = views + 1 WHERE id = ?', [$id]);
    $_SESSION['_viewed'][$id] = 1;
}
[$tag, $color] = news_tag($n);
$related = Database::all('SELECT n.* FROM news n WHERE ' . NEWS_PUBLIC . ' AND n.id <> ? AND n.category = ? ORDER BY n.published_at DESC LIMIT 5', [$id, $n['category']]);

$pageTitle  = $n['title'];
$metaDesc   = $n['excerpt'];
$ogImage    = $n['cover'] ? upload_url($n['cover']) : null;
$nav        = 'news';
$popupWhere = 'site';
require BASE_PATH . '/app/views/header.php';
?>
<div class="page-h"><div class="wrap">
  <div class="crumb"><a href="<?= e(url()) ?>">หน้าแรก</a>›<a href="<?= e(url('news.php')) ?>">ข่าวสาร</a>›<a href="<?= e(url('news.php', ['cat' => $n['category']])) ?>"><?= e(NEWS_CATEGORIES[$n['category']] ?? '') ?></a></div>
  <h1 class="hd" style="max-width:900px"><?= e($n['title']) ?></h1>
  <div class="meta" style="color:<?= e($color) ?>;margin-top:10px"><?= e($tag) ?> <span>· <?= e(thai_date($n['published_at'] ?? $n['created_at'], 'long')) ?> · อ่าน <?= number_format((int) $n['views']) ?> ครั้ง</span></div>
</div></div>
<div class="wrap layout">
  <article class="main-col article">
    <?php if (!$isPublic): ?><div class="draft-banner">ตัวอย่าง — ข่าวนี้ยังไม่เผยแพร่ (<?= e(news_state($n)[0]) ?>) ผู้เข้าชมทั่วไปยังมองไม่เห็น</div><?php endif; ?>
    <?php if ($n['cover']): ?><div class="figure" data-sk style="background-image:url(<?= e(upload_url($n['cover'])) ?>)" role="img" aria-label="ภาพประกอบข่าว"></div><?php endif; ?>
    <div class="prose"><?= text_to_html((string) $n['body']) ?></div>
    <?php foreach (news_pdfs($n) as $i => $pdf): $src = url($pdf['path']); ?>
      <section class="pdfv" aria-label="เอกสาร <?= e($pdf['label']) ?>">
        <div class="pdfv-bar">
          <span class="t"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5"/></svg><?= e($pdf['label']) ?></span>
          <span class="a">
            <a class="pbtn" href="<?= e($src) ?>" target="_blank" rel="noopener">เปิดในแท็บใหม่ <span aria-hidden="true">↗</span></a>
            <button type="button" class="pbtn" data-pdf-fs hidden>เต็มหน้าจอ <span aria-hidden="true">⛶</span></button>
          </span>
        </div>
        <div class="pdfv-frame" data-sk data-pdf-view="<?= e($src) ?>">
          <iframe src="<?= e($src) ?>#navpanes=0&amp;view=FitH" title="<?= e($pdf['label']) ?>" <?= $i > 0 ? 'loading="lazy"' : '' ?>></iframe>
        </div>
      </section>
    <?php endforeach; ?>
    <?php if ($n['unit_slug']): ?>
      <p style="margin-top:32px"><a href="<?= e(unit_url(['slug' => $n['unit_slug']])) ?>">← ไปที่หน้า<?= e(unit_title(['name' => $n['unit_name'], 'kind' => $n['unit_kind']])) ?></a></p>
    <?php endif; ?>
  </article>
  <aside>
    <div class="box"><h3>ข่าว<?= e(NEWS_CATEGORIES[$n['category']] ?? '') ?>ล่าสุด</h3>
      <?php foreach ($related as $r): ?><a class="row" href="<?= e(news_url($r)) ?>"><span class="small" style="color:var(--mute)"><?= e(thai_date($r['published_at'], 'short')) ?></span><br><?= e($r['title']) ?></a><?php endforeach; ?>
      <?php if (!$related): ?><p class="small" style="color:var(--mute);margin:0">ไม่มีข่าวอื่นในหมวดนี้</p><?php endif; ?>
      <a style="display:inline-block;margin-top:12px;font-weight:600;text-decoration:none" href="<?= e(url('news.php', ['cat' => $n['category']])) ?>">ดูทั้งหมด →</a>
    </div>
  </aside>
</div>
<?php require BASE_PATH . '/app/views/footer.php';
