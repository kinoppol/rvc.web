<?php
declare(strict_types=1);

/* รายการข่าวทั้งหมด — กรองหมวด หน่วยงาน และค้นหา */

require __DIR__ . '/app/bootstrap.php';

$cat  = array_key_exists(input('cat'), NEWS_CATEGORIES) ? input('cat') : '';
$q    = mb_substr(input('q'), 0, 100);
$unit = input('u');
$unitRow = $unit !== '' ? Database::one('SELECT * FROM units WHERE slug = ? AND is_active = 1', [$unit]) : null;

$where  = [NEWS_PUBLIC];
$params = [];
if ($cat !== '') {
    $where[]  = 'n.category = ?';
    $params[] = $cat;
}
if ($unitRow) {
    $where[]  = 'n.unit_id = ?';
    $params[] = $unitRow['id'];
}
if ($q !== '') {
    $where[]  = '(n.title LIKE ? OR n.excerpt LIKE ?)';
    $like     = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like);
}
$w     = implode(' AND ', $where);
$total = (int) Database::value("SELECT COUNT(*) FROM news n WHERE $w", $params);
$pg    = paginate($total, 12, input_int('page', 1));
$rows  = Database::all("SELECT n.*, u.name AS unit_name, u.kind AS unit_kind, u.group_key FROM news n LEFT JOIN units u ON u.id = n.unit_id
                        WHERE $w ORDER BY n.published_at DESC, n.id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);

$heading    = $unitRow ? 'ข่าวของ' . unit_title($unitRow) : ($cat ? NEWS_CATEGORIES[$cat] : 'ข่าวสารทั้งหมด');
$pageTitle  = $heading;
$nav        = 'news';
$popupWhere = 'site';
require BASE_PATH . '/app/views/header.php';
?>
<div class="page-h"><div class="wrap">
  <div class="crumb"><a href="<?= e(url()) ?>">หน้าแรก</a>›<a href="<?= e(url('news.php')) ?>">ข่าวสาร</a><?php if ($cat || $unitRow): ?>›<span><?= e($heading) ?></span><?php endif; ?></div>
  <h1 class="hd"><?= e($heading) ?></h1>
</div></div>
<div class="wrap" style="padding-top:32px;padding-bottom:72px">
  <form class="filters" method="get" role="search">
    <?php if ($unitRow): ?><input type="hidden" name="u" value="<?= e($unitRow['slug']) ?>"><?php endif; ?>
    <label for="q" style="position:absolute;left:-9999px">ค้นหาข่าว</label>
    <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาข่าว">
    <label for="cat" style="position:absolute;left:-9999px">หมวด</label>
    <select id="cat" name="cat"><option value="">ทุกหมวด</option><?php foreach (NEWS_CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <button type="submit">ค้นหา</button>
  </form>
  <?php if (!$rows): ?>
    <p class="empty">ไม่พบข่าว<?= $q !== '' ? 'ที่ตรงกับ "' . e($q) . '"' : '' ?></p>
  <?php else: ?>
    <p class="small" style="color:var(--mute);margin:-8px 0 20px">ทั้งหมด <?= number_format($total) ?> ข่าว</p>
    <div class="cards">
      <?php foreach ($rows as $n): [$tag, $color, $tint] = news_tag($n); ?>
        <a class="card" href="<?= e(news_url($n)) ?>">
          <div class="img<?= $n['cover'] ? '' : ' hatch' ?>" style="background-color:<?= e($tint) ?>;<?= $n['cover'] ? 'background-image:url(' . e(upload_url($n['cover'])) . ')' : '' ?>"></div>
          <div class="c"><div class="m"><span style="color:<?= e($color) ?>;font-weight:600"><?= e($tag) ?></span> · <?= e(thai_date($n['published_at'])) ?></div>
            <div class="t"><?= e($n['title']) ?></div></div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
      <nav class="pager" aria-label="หน้า"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <?php if ($i === $pg['page']): ?><span class="on" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e(url('news.php', ['cat' => $cat, 'q' => $q, 'u' => $unitRow['slug'] ?? null, 'page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?></nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require BASE_PATH . '/app/views/footer.php';
