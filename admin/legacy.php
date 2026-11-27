<?php
declare(strict_types=1);

/* นำเข้าข้อมูลจากเว็บไซต์เดิม — เลือกหลายรายการแล้วสร้างเป็นข่าว/หน้าเว็บ พร้อมทำสำเนารูปและไฟล์แนบไว้ในเว็บนี้ */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

if (!Database::tableExists(Database::pdo(), 'legacy_files')) {
    admin_header('นำเข้าจากเว็บเดิม', 'legacy');
    echo '<div class="head"><div><h1>นำเข้าจากเว็บเดิม</h1></div></div><div class="msg warn">ยังไม่ได้สร้างตารางสำหรับนำเข้าข้อมูล — ไปที่เมนู <a href="' . e(url('admin/migrations.php')) . '">Migrations</a> แล้วกดติดตั้ง migration ที่รออยู่</div>';
    admin_footer();
    exit;
}

$src = input('src') ?: 'news';
if ($src !== 'pages' && !isset(LegacySite::SOURCES[$src])) {
    $src = 'news';
}

/* ------------------------------ บันทึก ------------------------------ */
if (is_post()) {
    set_time_limit(600);
    session_write_close();
    $act = input('act');
    $msgs = [];
    if ($act === 'settings') {
        session_start();
        foreach (['legacy_site_url' => LegacySite::DEFAULT_SITE, 'legacy_api_url' => LegacySite::DEFAULT_API] as $k => $def) {
            $v = rtrim(input($k), '/');
            Settings::set($k, preg_match('~^https?://~i', $v) && $v !== $def ? $v : '');
        }
        Settings::set('legacy_hosts', input('legacy_hosts') === LegacySite::DEFAULT_HOSTS ? '' : mb_substr(input('legacy_hosts'), 0, 500));
        foreach (glob(BASE_PATH . '/storage/cache/legacy_*.cache') ?: [] as $f) {
            @unlink($f);
        }
        flash('ok', 'บันทึกการตั้งค่าเว็บเดิมแล้ว และล้างแคชข้อมูลเว็บเดิม');
        redirect(url('admin/legacy.php', ['src' => $src]));
    }
    if ($act === 'refresh') {
        session_start();
        foreach (glob(BASE_PATH . '/storage/cache/legacy_*.cache') ?: [] as $f) {
            @unlink($f);
        }
        flash('ok', 'ล้างแคชแล้ว — รายการจะดึงใหม่จากเว็บเดิม');
        redirect(url('admin/legacy.php', ['src' => $src]));
    }
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))))), 0, 30);
    $paths = array_slice(array_values(array_filter((array) ($_POST['paths'] ?? []), 'is_string')), 0, 20);
    $ok = 0;
    $skip = 0;
    $warn = [];
    if ($act === 'import' && $src !== 'pages') {
        $publish = input('status') === 'published';
        foreach ($ids as $iid) {
            try {
                $r = LegacySite::importAsNews($src, $iid, (int) $me['id'], $publish);
                $r['skipped'] ? $skip++ : $ok++;
                foreach ($r['warnings'] as $w) {
                    $warn[] = '#' . $iid . ': ' . $w;
                }
            } catch (Throwable $ex) {
                $warn[] = '#' . $iid . ': ' . $ex->getMessage();
            }
        }
    } elseif ($act === 'import' && $src === 'pages') {
        foreach ($paths as $path) {
            try {
                $ref = 'page:' . $path;
                if (Database::value('SELECT id FROM pages WHERE source_ref = ?', [$ref])) {
                    $skip++;
                    continue;
                }
                $p = LegacySite::page($path);
                $slug = slugify(trim(str_replace('/', '-', preg_replace('~^/(base9?|info|curriculum)/~', '', $path) ?? $path), '-')) ?: 'page';
                $base = $slug;
                for ($n = 2; Database::value('SELECT id FROM pages WHERE slug = ?', [$slug]); $n++) {
                    $slug = $base . '-' . $n;
                }
                Database::insert('pages', ['title' => mb_substr($p['title'], 0, 255), 'slug' => $slug, 'body' => $p['text'], 'is_published' => 0, 'source_ref' => $ref]);
                $ok++;
                foreach ($p['warnings'] as $w) {
                    $warn[] = $path . ': ' . $w;
                }
            } catch (Throwable $ex) {
                $warn[] = $path . ': ' . $ex->getMessage();
            }
        }
    }
    session_start();
    if ($ok || $skip) {
        flash('ok', 'นำเข้าแล้ว ' . $ok . ' รายการ' . ($skip ? ' · ข้าม ' . $skip . ' รายการที่เคยนำเข้าแล้ว' : '')
            . ($src === 'pages' ? ' — หน้าที่นำเข้ายังไม่เผยแพร่ ตรวจทานในเมนูหน้าเว็บก่อน' : ''));
    }
    if ($warn) {
        flash('warn', implode("\n", array_slice($warn, 0, 15)));
    }
    if (!$ok && !$skip && !$warn) {
        flash('err', 'กรุณาเลือกรายการที่ต้องการนำเข้า');
    }
    redirect(url('admin/legacy.php', ['src' => $src, 'q' => input('q') ?: null, 'page' => input_int('page') ?: null]));
}

/* ------------------------------ แสดงผล ------------------------------ */
$q = input('q');
$error = null;
$list = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$pages = [];
try {
    if ($src === 'pages') {
        $pages = LegacySite::pages();
    } else {
        $list = LegacySite::list($src, $q, max(1, input_int('page', 1)), 30);
    }
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}
$done = LegacySite::importedRefs();
$donePages = array_column(Database::all('SELECT id, source_ref FROM pages WHERE source_ref IS NOT NULL'), 'id', 'source_ref');
$files = Database::one('SELECT COUNT(*) n, COALESCE(SUM(bytes), 0) b FROM legacy_files') ?? [];

admin_header('นำเข้าจากเว็บเดิม', 'legacy', 'ผู้ดูแลระบบ › นำเข้าจากเว็บเดิม');
?>
<div class="head">
  <div><h1>นำเข้าจากเว็บไซต์เดิม</h1>
    <p>ดึงข่าว กิจกรรม รางวัล ประกาศ และหน้าเนื้อหาจาก <a href="<?= e(LegacySite::siteBase()) ?>" target="_blank" rel="noopener"><?= e(LegacySite::siteBase()) ?></a>
      — รูปและไฟล์แนบบนเว็บเดิมจะถูกทำสำเนาไว้ในเว็บนี้ เพราะเว็บเดิมจะถูกปิด · ลิงก์ภายนอก (Google Drive ฯลฯ) คงไว้ตามเดิม</p></div>
  <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="refresh"><input type="hidden" name="src" value="<?= e($src) ?>"><button class="btn ghost" type="submit">ดึงรายการใหม่</button></form>
</div>
<div class="stats">
  <div class="stat"><div class="l">ข่าวที่นำเข้าแล้ว</div><div class="v"><?= count($done) ?></div></div>
  <div class="stat"><div class="l">หน้าเว็บที่นำเข้าแล้ว</div><div class="v"><?= count($donePages) ?></div></div>
  <div class="stat"><div class="l">ไฟล์ที่ทำสำเนาไว้</div><div class="v"><?= (int) ($files['n'] ?? 0) ?></div><div class="small mute"><?= e(number_format(((int) ($files['b'] ?? 0)) / 1048576, 1)) ?> MB ใน uploads/legacy/</div></div>
</div>
<nav class="quick" aria-label="ชนิดข้อมูล" style="margin:0 0 16px">
  <?php foreach (LegacySite::SOURCES + ['pages' => ['label' => 'หน้าเนื้อหา']] as $k => $s): ?>
    <a class="chip<?= $k === $src ? ' on' : '' ?>" style="display:inline-flex;align-items:center;text-decoration:none;<?= $k === $src ? 'background:var(--blue);border-color:var(--blue);color:#fff' : 'color:inherit' ?>" href="<?= e(url('admin/legacy.php', ['src' => $k])) ?>"><?= e($s['label']) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($error): ?><div class="msg err">เชื่อมต่อเว็บเดิมไม่ได้: <?= e($error) ?></div><?php endif; ?>

<div class="cols">
<section class="tbl wide" style="display:block">
<?php if ($src === 'pages'): ?>
  <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="import"><input type="hidden" name="src" value="pages">
    <div class="ttl"><span><?= count($pages) ?> หน้า · นำเข้าเป็นหน้าเว็บ (ยังไม่เผยแพร่) ครั้งละไม่เกิน 20 หน้า</span><button class="btn sm" type="submit">นำเข้าหน้าที่เลือก</button></div>
    <div class="tw"><table>
      <thead><tr><th style="width:48px"><input type="checkbox" data-all aria-label="เลือกทั้งหมด"></th><th>หน้า</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php foreach ($pages as $path): $pid = $donePages['page:' . $path] ?? null; ?>
        <tr class="row"><td><input type="checkbox" name="paths[]" value="<?= e($path) ?>" <?= $pid ? 'disabled' : '' ?> aria-label="เลือก <?= e($path) ?>"></td>
          <td><b><?= e(LegacySite::pageLabel($path)) ?></b> <a class="small" href="<?= e(LegacySite::siteBase() . $path) ?>" target="_blank" rel="noopener"><?= e($path) ?></a></td>
          <td><?= $pid ? '<a class="pill ok" href="' . e(url('admin/pages.php', ['act' => 'edit', 'id' => $pid])) . '">นำเข้าแล้ว</a>' : '<span class="pill mute">ยังไม่นำเข้า</span>' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </form>
<?php else: ?>
  <form class="bar" method="get"><input type="hidden" name="src" value="<?= e($src) ?>">
    <label for="q" class="sr">ค้นหา</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาจากหัวข้อหรือรายละเอียด">
    <button class="btn ghost" type="submit">ค้นหา</button></form>
  <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="import"><input type="hidden" name="src" value="<?= e($src) ?>">
    <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="page" value="<?= (int) $list['page'] ?>">
    <div class="ttl"><span><?= (int) $list['total'] ?> รายการ · นำเข้าเป็นข่าวหมวด “<?= e(NEWS_CATEGORIES[LegacySite::SOURCES[$src]['category']] ?? '') ?>” ครั้งละไม่เกิน 30 รายการ</span>
      <span class="acts-cell">
        <label for="st" class="sr">สถานะเมื่อนำเข้า</label>
        <select id="st" name="status" style="width:auto;min-height:36px"><option value="draft">เป็นฉบับร่าง</option><option value="published">เผยแพร่ทันที (ตามวันที่เดิม)</option></select>
        <button class="btn sm" type="submit">นำเข้ารายการที่เลือก</button></span></div>
    <div class="tw"><table style="min-width:640px">
      <thead><tr><th style="width:48px"><input type="checkbox" data-all aria-label="เลือกทั้งหมดในหน้านี้"></th><th>หัวข้อ</th><th>วันที่</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php if (!$list['items'] && !$error): ?><tr><td colspan="4" class="empty">ไม่พบรายการ</td></tr><?php endif; ?>
      <?php foreach ($list['items'] as $it): $nid = $done[$it['ref']] ?? null; ?>
        <tr class="row">
          <td><input type="checkbox" name="ids[]" value="<?= (int) $it['id'] ?>" <?= $nid ? 'disabled' : '' ?> aria-label="เลือก <?= e($it['title']) ?>"></td>
          <td><div class="ncell"><div class="thumb" style="<?= $it['image'] ? 'background-image:url(' . e($it['image']) . ')' : '' ?>"></div>
            <div><b><?= e($it['title'] ?: '(ไม่มีหัวข้อ)') ?></b><div class="small mute"><?= e(str_limit($it['text'], 110)) ?>
              <?= $it['attach'] ? ' · <span style="color:var(--blue)">มีไฟล์แนบ</span>' : '' ?></div></div></div></td>
          <td class="small mute nowrap"><?= e(thai_date($it['date'])) ?></td>
          <td><?= $nid ? '<a class="pill ok" href="' . e(url('admin/news.php', ['act' => 'edit', 'id' => $nid])) . '">นำเข้าแล้ว</a>' : '<span class="pill mute">ยังไม่นำเข้า</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </form>
  <?php if ($list['pages'] > 1): ?>
    <nav class="pager" aria-label="หน้า"><?php for ($i = 1; $i <= $list['pages']; $i++): ?>
      <?php if ($i === $list['page']): ?><span class="on"><?= $i ?></span><?php elseif ($i <= 3 || $i > $list['pages'] - 2 || abs($i - $list['page']) <= 2): ?><a href="<?= e(url('admin/legacy.php', ['src' => $src, 'q' => $q ?: null, 'page' => $i])) ?>"><?= $i ?></a><?php elseif ($i === 4 || $i === $list['pages'] - 2): ?><span>…</span><?php endif; ?>
    <?php endfor; ?></nav>
  <?php endif; ?>
<?php endif; ?>
</section>
<aside class="narrow">
  <section class="card">
    <h2>วิธีใช้</h2>
    <ul class="small" style="margin:0;padding-left:18px;line-height:1.8">
      <li>นำเข้าที่นี่ = คัดลอกตามต้นฉบับ (ไม่ผ่าน AI) เร็วและเหมาะกับข้อมูลจำนวนมาก</li>
      <li>ถ้าต้องการให้ AI เรียบเรียงใหม่ ให้เปิดข่าว/หน้าเว็บ แล้วใช้ <b>ผู้ช่วย AI › ดึงข้อมูลจากเว็บไซต์เดิม</b></li>
      <li>รายการที่นำเข้าแล้วจะไม่ถูกนำเข้าซ้ำ</li>
      <li>รูปหน้าปก, PDF และเอกสารบนเว็บเดิมจะถูกเก็บไว้ใน <code>uploads/legacy/</code></li>
    </ul>
  </section>
  <section class="card">
    <h2>ตั้งค่าเว็บเดิม</h2>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="settings"><input type="hidden" name="src" value="<?= e($src) ?>">
      <label class="f" for="lsu">ที่อยู่เว็บเดิม</label><input id="lsu" name="legacy_site_url" type="url" value="<?= e(LegacySite::siteBase()) ?>">
      <label class="f" for="lau">API ของเว็บเดิม</label><input id="lau" name="legacy_api_url" type="url" value="<?= e(LegacySite::apiBase()) ?>">
      <label class="f" for="lh">โดเมนที่ต้องทำสำเนาไฟล์</label><input id="lh" name="legacy_hosts" value="<?= e(Settings::get('legacy_hosts', LegacySite::DEFAULT_HOSTS)) ?>">
      <div class="hint">คั่นด้วยจุลภาค — ไฟล์จากโดเมนเหล่านี้จะถูกคัดลอกมาเก็บ เพราะจะใช้ไม่ได้หลังปิดเว็บเดิม</div>
      <button class="btn block" type="submit">บันทึก</button>
    </form>
  </section>
</aside>
</div>
<script>
document.querySelectorAll('[data-all]').forEach(function (a) {
  a.addEventListener('change', function () {
    a.closest('table').querySelectorAll('tbody input[type=checkbox]:not(:disabled)').forEach(function (c) { c.checked = a.checked; });
  });
});
</script>
<?php admin_footer();
