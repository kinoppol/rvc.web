<?php
declare(strict_types=1);

/* ข่าวและประกาศ — ผู้ดูแลระบบจัดการได้ทั้งหมด · ผู้เขียนข่าวจัดการเฉพาะข่าวของตน/หน่วยงานตน และต้องรออนุมัติ */

require __DIR__ . '/_init.php';
$me = Auth::require();
Csrf::verify();
$isAdmin = Auth::isAdmin();

/** ผู้ใช้คนนี้แก้ไขข่าวนี้ได้หรือไม่ */
function can_edit(array $n, array $me): bool
{
    return $me['role'] === 'admin'
        || (int) $n['author_id'] === (int) $me['id']
        || ($me['unit_id'] && (int) $n['unit_id'] === (int) $me['unit_id']);
}

function load_news(int $id, array $me): array
{
    $n = Database::one('SELECT * FROM news WHERE id = ?', [$id]);
    if (!$n) {
        abort(404, 'ไม่พบข่าวนี้');
    }
    if (!can_edit($n, $me)) {
        abort(403, 'คุณไม่มีสิทธิ์แก้ไขข่าวนี้');
    }
    return $n;
}

$act = input('act');
$units = Database::all("SELECT id, name, kind FROM units WHERE is_active = 1 ORDER BY FIELD(kind,'department','division','work'), sort, name");
$unitIds = array_map('intval', array_column($units, 'id'));

/* ------------------------------ บันทึก ------------------------------ */
if (is_post() && in_array($act, ['save', 'delete', 'approve'], true)) {
    $id  = input_int('id');
    $old = $id ? load_news($id, $me) : null;

    if ($act === 'delete' && $old) {
        Database::delete('news', $id);
        delete_upload($old['cover']);
        flash('ok', 'ลบข่าว "' . $old['title'] . '" แล้ว');
        redirect(url('admin/news.php'));
    }
    if ($act === 'approve' && $old && $isAdmin) {
        Database::update('news', ['status' => 'published', 'published_at' => $old['published_at'] ?: date('Y-m-d H:i:s')], $id);
        flash('ok', 'อนุมัติและเผยแพร่ข่าวแล้ว');
        redirect(url('admin/news.php'));
    }

    $title = mb_substr(input('title'), 0, 255);
    $body  = (string) ($_POST['body'] ?? '');
    $intent = input('intent');                          // draft | publish
    $when  = input('when');                             // now | schedule
    $cat   = array_key_exists(input('category'), NEWS_CATEGORIES) ? input('category') : 'pr';
    $unitId = input_int('unit_id') ?: null;
    if (!$isAdmin) {
        $unitId = $me['unit_id'] ? (int) $me['unit_id'] : null;   // ผู้เขียนโพสต์ได้เฉพาะหน่วยงานที่ดูแล
    } elseif ($unitId !== null && !in_array($unitId, $unitIds, true)) {
        $unitId = null;
    }
    $_SESSION['_news_form'] = $_POST;
    $errors = [];
    if ($title === '') {
        $errors[] = 'กรุณากรอกหัวข้อข่าว';
    }
    $publishAt = null;
    if ($intent === 'publish') {
        if ($when === 'schedule') {
            $publishAt = dt_from_input(input('publish_at'));
            if ($publishAt === null) {
                $errors[] = 'กรุณาระบุวันเวลาที่จะเผยแพร่';
            }
        } else {
            $publishAt = ($old && $old['status'] === 'published' && $old['published_at'] && strtotime($old['published_at']) <= time())
                ? $old['published_at'] : date('Y-m-d H:i:s');
        }
    }
    try {
        $cover = handle_image_upload('cover');
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
        $cover = null;
    }
    if ($errors) {
        if ($cover) {
            delete_upload($cover);
        }
        flash('err', implode("\n", $errors));
        redirect(url('admin/news.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    $excerpt = input('excerpt') ?: str_limit(preg_replace('/^##\s+.*$/m', '', $body) ?? $body, 220);
    $status  = $intent === 'publish' ? ($isAdmin ? 'published' : 'pending') : 'draft';
    $data = [
        'title' => $title, 'excerpt' => mb_substr($excerpt, 0, 500), 'body' => $body, 'category' => $cat,
        'unit_id' => $unitId, 'status' => $status, 'published_at' => $publishAt ?? $old['published_at'] ?? null,
        'pinned' => $isAdmin ? (int) !empty($_POST['pinned']) : (int) ($old['pinned'] ?? 0),
    ];
    $legacyCover = input('cover_legacy');   // ภาพหน้าปกที่ทำสำเนาจากเว็บเดิม (ผู้ช่วย AI)
    if (!$cover && preg_match('~^uploads/legacy/\d{4}/\d{2}/[a-f0-9]{24}\.(jpg|png|webp|gif)$~', $legacyCover) && is_file(BASE_PATH . '/' . $legacyCover)) {
        $cover = $legacyCover;
    }
    $ref = input('source_ref');
    if (preg_match('~^[a-z]+:\d+$~', $ref) && !Database::value('SELECT id FROM news WHERE source_ref = ? AND id <> ?', [$ref, $id])) {
        $data['source_ref'] = $ref;
    }
    if ($cover) {
        $data['cover'] = $cover;
    } elseif (!empty($_POST['remove_cover'])) {
        $data['cover'] = null;
    }
    if ($old) {
        Database::update('news', $data, $id);
        if (array_key_exists('cover', $data)) {
            delete_upload($old['cover']);
        }
    } else {
        $id = Database::insert('news', $data + ['author_id' => $me['id']]);
    }
    unset($_SESSION['_news_form']);
    flash('ok', match ($status) {
        'draft'   => 'บันทึกฉบับร่างแล้ว',
        'pending' => 'ส่งข่าวให้ผู้ดูแลอนุมัติแล้ว',
        default   => strtotime((string) $data['published_at']) > time() ? 'ตั้งเวลาเผยแพร่ ' . thai_date($data['published_at'], 'time') . ' แล้ว' : 'เผยแพร่ข่าวแล้ว',
    });
    redirect(url('admin/news.php', ['act' => 'edit', 'id' => $id]));
}

/* ------------------------------ ฟอร์มเขียน/แก้ไข ------------------------------ */
if ($act === 'new' || $act === 'edit') {
    $n = $act === 'edit' ? load_news(input_int('id'), $me) : [
        'id' => 0, 'title' => '', 'excerpt' => '', 'body' => '', 'cover' => null, 'category' => 'pr',
        'unit_id' => $me['unit_id'], 'status' => 'draft', 'published_at' => null, 'pinned' => 0,
    ];
    if (!empty($_SESSION['_news_form'])) {   // คืนค่าที่กรอกไว้เมื่อบันทึกไม่ผ่าน
        $f = $_SESSION['_news_form'];
        $n = array_merge($n, array_intersect_key($f, array_flip(['title', 'excerpt', 'body', 'category'])));
        unset($_SESSION['_news_form']);
    }
    $scheduled = $n['published_at'] && strtotime($n['published_at']) > time();
    admin_header($n['id'] ? 'แก้ไขข่าว' : 'เขียนข่าว', 'news');
    ?>
    <form method="post" enctype="multipart/form-data">
    <?= Csrf::field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/news.php')) ?>">‹ กลับไปรายการข่าว</a>
        <h1><?= $n['id'] ? 'แก้ไขข่าว' : 'เขียนข่าว' ?> <?= $n['id'] ? status_pill(news_state($n)) : '' ?></h1></div>
      <div class="acts">
        <?= ai_button('news', ['title' => '#nt', 'body' => '#body', 'excerpt' => '#ex']) ?>
        <?php if ($n['id']): ?><a class="btn ghost" href="<?= e(url('news_view.php', ['id' => $n['id'], 'preview' => 1])) ?>" target="_blank" rel="noopener">ดูตัวอย่าง</a><?php endif; ?>
        <button class="btn ghost" type="submit" name="intent" value="draft">บันทึกฉบับร่าง</button>
        <button class="btn" type="submit" name="intent" value="publish"><?= $isAdmin ? 'เผยแพร่' : 'ส่งอนุมัติ' ?></button>
      </div>
    </div>
    <div class="cols">
      <section class="card wide" style="display:block">
        <label class="f" for="nt">หัวข้อข่าว</label>
        <input id="nt" class="title" name="title" value="<?= e($n['title']) ?>" maxlength="255" placeholder="เช่น นักศึกษาแผนกการโรงแรมฝึกงานโรงแรมระดับสี่ดาว" required>
        <div class="f" style="font-weight:600;margin:18px 0 6px">ภาพหน้าปก</div>
        <div class="drop">
          <img id="cv" src="<?= e(upload_url($n['cover']) ?? '') ?>" alt="" <?= $n['cover'] ? '' : 'hidden' ?>>
          <input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#cv" aria-label="เลือกรูปภาพหน้าปก">
          <div class="hint">JPG, PNG, WebP ไม่เกิน 5 MB · แนะนำแนวนอน 16:9 (เช่น 1600 × 900 px)</div>
          <?php if ($n['cover']): ?><label class="chk" style="justify-content:center"><input type="checkbox" name="remove_cover" value="1"> ลบภาพหน้าปก</label><?php endif; ?>
        </div>
        <div style="font-weight:600;margin:18px 0 6px">เนื้อหาข่าว</div>
        <div class="editor">
          <div class="toolbar" role="toolbar" aria-label="จัดรูปแบบ">
            <button type="button" data-target="#body" data-insert="&#10;&#10;## {}&#10;" data-sample="หัวข้อย่อย">หัวข้อย่อย</button>
            <button type="button" data-target="#body" data-insert="&#10;- {}" data-sample="รายการ">• รายการ</button>
            <button type="button" data-target="#body" data-insert="{}" data-sample="https://">ใส่ลิงก์</button>
          </div>
          <label for="body" class="sr">เนื้อหา</label>
          <textarea id="body" name="body" class="body" placeholder="พิมพ์หรือวางข้อความจาก Word ได้เลย — เว้นบรรทัดว่างเพื่อขึ้นย่อหน้าใหม่ · ขึ้นต้นบรรทัดด้วย ## สำหรับหัวข้อย่อย · - สำหรับรายการ"><?= e($n['body']) ?></textarea>
        </div>
        <label class="f" for="ex">คำโปรย <span class="opt">(ไม่บังคับ — ถ้าว่าง ระบบดึงจากย่อหน้าแรกอัตโนมัติ)</span></label>
        <textarea id="ex" name="excerpt" maxlength="500" style="min-height:80px"><?= e($n['excerpt']) ?></textarea>
      </section>
      <aside class="narrow">
        <section class="card">
          <h2>การเผยแพร่</h2>
          <label class="chk"><input type="radio" name="when" value="now" <?= $scheduled ? '' : 'checked' ?>> เผยแพร่ทันที</label>
          <label class="chk"><input type="radio" name="when" value="schedule" <?= $scheduled ? 'checked' : '' ?>> ตั้งเวลาเผยแพร่</label>
          <input type="datetime-local" name="publish_at" value="<?= e(dt_to_input($scheduled ? $n['published_at'] : null)) ?>" aria-label="วันเวลาเผยแพร่" style="margin-top:6px">
          <?php if ($isAdmin): ?>
            <label class="chk top"><input type="checkbox" name="pinned" value="1" <?= $n['pinned'] ? 'checked' : '' ?>> ปักหมุดเป็นข่าวเด่นหน้าแรก</label>
          <?php endif; ?>
          <?php if ($n['id'] && $n['status'] === 'published'): ?>
            <p class="hint">เผยแพร่เมื่อ <?= e(thai_date($n['published_at'], 'time')) ?></p>
          <?php endif; ?>
        </section>
        <section class="card">
          <label class="f" for="c2">หมวดข่าว</label>
          <select id="c2" name="category"><?php foreach (NEWS_CATEGORIES as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $n['category'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
          <label class="f" for="u2">แสดงในเว็บไซต์ย่อย</label>
          <?php if ($isAdmin): ?>
            <select id="u2" name="unit_id"><option value="">— ไม่ระบุ (เฉพาะหน้าหลัก) —</option>
              <?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $n['unit_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e(unit_title($u)) ?></option><?php endforeach; ?>
            </select>
          <?php else: ?>
            <select id="u2" disabled><option><?= e($me['unit_name'] ?: '— ไม่ได้กำหนดหน่วยงาน —') ?></option></select>
            <div class="hint">คุณมีสิทธิ์โพสต์เฉพาะหน่วยงานที่ดูแล ข่าวต้องผ่านการอนุมัติจากผู้ดูแลระบบก่อนเผยแพร่</div>
          <?php endif; ?>
        </section>
        <?php if ($n['id']): ?>
        <section class="card">
          <button class="linkbtn danger" type="submit" form="del-form">ลบข่าวนี้</button>
        </section>
        <?php endif; ?>
      </aside>
    </div>
    </form>
    <?php if ($n['id']): ?>
      <form id="del-form" method="post" data-confirm="ลบข่าวนี้ถาวร?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"></form>
    <?php endif;
    admin_footer();
    exit;
}

/* ------------------------------ รายการข่าว ------------------------------ */
$q      = input('q');
$cat    = input('category');
$unitF  = input_int('unit');
$stF    = input('status');
$where  = ['1 = 1'];
$params = [];
if (!$isAdmin) {
    $where[]  = '(n.author_id = ? OR (n.unit_id IS NOT NULL AND n.unit_id = ?))';
    $params[] = $me['id'];
    $params[] = (int) $me['unit_id'];
}
if ($q !== '') {
    $where[]  = 'n.title LIKE ?';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if (isset(NEWS_CATEGORIES[$cat])) {
    $where[]  = 'n.category = ?';
    $params[] = $cat;
}
if ($unitF) {
    $where[]  = 'n.unit_id = ?';
    $params[] = $unitF;
}
$where[] = match ($stF) {
    'live'      => "n.status = 'published' AND n.published_at <= NOW()",
    'scheduled' => "n.status = 'published' AND n.published_at > NOW()",
    'draft'     => "n.status = 'draft'",
    'pending'   => "n.status = 'pending'",
    default     => '1 = 1',
};
$w     = implode(' AND ', $where);
$total = (int) Database::value("SELECT COUNT(*) FROM news n WHERE $w", $params);
$pg    = paginate($total, 20, input_int('page', 1));
$rows  = Database::all("SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id
                        WHERE $w ORDER BY (n.status = 'pending') DESC, COALESCE(n.published_at, n.created_at) DESC, n.id DESC
                        LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params);
$scope = $isAdmin ? '' : ' AND (author_id = ' . (int) $me['id'] . ($me['unit_id'] ? ' OR unit_id = ' . (int) $me['unit_id'] : '') . ')';
$c = Database::one("SELECT SUM(status = 'published' AND published_at <= NOW() AND published_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) m,
    SUM(status = 'published' AND published_at > NOW()) s, SUM(status = 'draft') d, SUM(status = 'pending') p FROM news WHERE 1 = 1{$scope}") ?? [];

admin_header('ข่าวและประกาศ', 'news');
?>
<div class="head">
  <div><h1>ข่าวและประกาศ</h1><p>ข่าวที่เผยแพร่จะขึ้นทั้งหน้าแรกของวิทยาลัยและหน้าเว็บไซต์ย่อยของหน่วยงานที่เลือก</p></div>
  <a class="btn big" href="<?= e(url('admin/news.php', ['act' => 'new'])) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>เขียนข่าวใหม่</a>
</div>
<div class="stats">
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'live'])) ?>"><div class="l">เผยแพร่เดือนนี้</div><div class="v"><?= (int) ($c['m'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'scheduled'])) ?>"><div class="l">ตั้งเวลาไว้</div><div class="v" style="color:var(--warn)"><?= (int) ($c['s'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'draft'])) ?>"><div class="l">ฉบับร่าง</div><div class="v" style="color:var(--mute)"><?= (int) ($c['d'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'pending'])) ?>"><div class="l">รออนุมัติ</div><div class="v" style="color:var(--blue)"><?= (int) ($c['p'] ?? 0) ?></div></a>
</div>
<section class="tbl">
  <form class="bar" method="get">
    <label for="q" class="sr">ค้นหาข่าว</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาจากหัวข้อข่าว">
    <label for="cat" class="sr">หมวด</label>
    <select id="cat" name="category"><option value="">ทุกหมวด</option><?php foreach (NEWS_CATEGORIES as $k => $v): ?><option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <?php if ($isAdmin): ?>
    <label for="unit" class="sr">หน่วยงาน</label>
    <select id="unit" name="unit"><option value="">ทุกหน่วยงาน</option><?php foreach ($units as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $unitF === (int) $u['id'] ? 'selected' : '' ?>><?= e(unit_title($u)) ?></option><?php endforeach; ?></select>
    <?php endif; ?>
    <label for="st" class="sr">สถานะ</label>
    <select id="st" name="status"><option value="">ทุกสถานะ</option>
      <?php foreach (['live' => 'เผยแพร่แล้ว', 'scheduled' => 'ตั้งเวลาไว้', 'draft' => 'ฉบับร่าง', 'pending' => 'รออนุมัติ'] as $k => $v): ?><option value="<?= $k ?>" <?= $stF === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
    <button class="btn ghost" type="submit">กรอง</button>
  </form>
  <div class="tw"><table style="min-width:720px">
    <thead><tr><th>ข่าว</th><th>หมวด</th><th>วันที่</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">ไม่พบข่าว</td></tr><?php endif; ?>
    <?php foreach ($rows as $n): ?>
      <tr class="row">
        <td><div class="ncell"><div class="thumb" style="<?= $n['cover'] ? 'background-image:url(' . e(upload_url($n['cover'])) . ')' : '' ?>"></div>
          <div><b><?= e($n['title']) ?></b><?= $n['pinned'] ? ' <span class="pill info">ปักหมุด</span>' : '' ?><div class="small mute"><?= e($n['unit_name'] ?? '') ?></div></div></div></td>
        <td class="mute"><?= e(NEWS_CATEGORIES[$n['category']] ?? '') ?></td>
        <td class="mute nowrap"><?= $n['published_at'] ? e(thai_date($n['published_at'])) : '—' ?></td>
        <td><?= status_pill(news_state($n)) ?></td>
        <td class="r"><div class="acts-cell">
          <?php if ($isAdmin && $n['status'] === 'pending'): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button class="btn sm" type="submit">อนุมัติ</button></form>
          <?php endif; ?>
          <a class="btn sm ghost" href="<?= e(url('admin/news.php', ['act' => 'edit', 'id' => $n['id']])) ?>">แก้ไข</a></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($pg['pages'] > 1): ?>
    <nav class="pager" aria-label="หน้า"><?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
      <?php if ($i === $pg['page']): ?><span class="on"><?= $i ?></span><?php else: ?><a href="<?= e(url('admin/news.php', ['q' => $q, 'category' => $cat, 'unit' => $unitF ?: null, 'status' => $stF, 'page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?></nav>
  <?php endif; ?>
</section>
<?php admin_footer();
