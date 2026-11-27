<?php
declare(strict_types=1);

/* หน้าเว็บ — เนื้อหาคงที่ เช่น ประวัติวิทยาลัย วิสัยทัศน์ ติดต่อ */

require __DIR__ . '/_init.php';
Auth::require('admin');
Csrf::verify();

$id  = input_int('id');
$act = input('act');
$old = $id ? Database::one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
if ($id && !$old) {
    abort(404, 'ไม่พบหน้านี้');
}

if (is_post()) {
    if ($act === 'delete' && $old) {
        Database::delete('pages', $id);
        flash('ok', 'ลบหน้า "' . $old['title'] . '" แล้ว');
        redirect(url('admin/pages.php'));
    }
    $data = [
        'title'        => mb_substr(input('title'), 0, 255),
        'slug'         => slugify(input('slug')),
        'body'         => (string) ($_POST['body'] ?? ''),
        'is_published' => (int) !empty($_POST['is_published']),
    ];
    $err = match (true) {
        $data['title'] === '' => 'กรุณากรอกชื่อหน้า',
        $data['slug'] === ''  => 'กรุณากรอกชื่อ URL (a-z, 0-9, -)',
        (bool) Database::value('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$data['slug'], $id]) => 'ชื่อ URL นี้ถูกใช้แล้ว',
        default => null,
    };
    if ($err) {
        flash('err', $err);
        redirect(url('admin/pages.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    $old ? Database::update('pages', $data, $id) : ($id = Database::insert('pages', $data));
    flash('ok', 'บันทึกหน้า "' . $data['title'] . '" แล้ว');
    redirect(url('admin/pages.php', ['act' => 'edit', 'id' => $id]));
}

if ($act === 'new' || $old) {
    $p = $old ?? ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'is_published' => 1];
    admin_header($p['id'] ? 'แก้ไขหน้าเว็บ' : 'เพิ่มหน้าเว็บ', 'pages');
    ?>
    <form method="post">
    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/pages.php')) ?>">‹ กลับไปรายการหน้าเว็บ</a><h1><?= $p['id'] ? e($p['title']) : 'เพิ่มหน้าเว็บ' ?></h1></div>
      <div class="acts"><?= ai_button('page', ['title' => '#t', 'body' => '#body']) ?><?php if ($p['id']): ?><a class="btn ghost" href="<?= e(page_url($p['slug'])) ?>" target="_blank" rel="noopener">ดูหน้าเว็บ</a><?php endif; ?><button class="btn" type="submit">บันทึก</button></div>
    </div>
    <div class="cols">
      <section class="card wide" style="display:block">
        <label class="f" for="t">ชื่อหน้า</label><input id="t" class="title" name="title" value="<?= e($p['title']) ?>" required>
        <div style="font-weight:600;margin:18px 0 6px">เนื้อหา</div>
        <div class="editor">
          <div class="toolbar" role="toolbar" aria-label="จัดรูปแบบ">
            <button type="button" data-target="#body" data-insert="&#10;&#10;## {}&#10;" data-sample="หัวข้อย่อย">หัวข้อย่อย</button>
            <button type="button" data-target="#body" data-insert="&#10;- {}" data-sample="รายการ">• รายการ</button>
            <button type="button" data-target="#body" data-insert="{}" data-sample="https://">ใส่ลิงก์</button>
          </div>
          <label for="body" class="sr">เนื้อหา</label>
          <textarea id="body" name="body" class="body"><?= e($p['body']) ?></textarea>
        </div>
      </section>
      <aside class="narrow"><section class="card">
        <label class="f" for="s">ชื่อ URL</label><input id="s" name="slug" value="<?= e($p['slug']) ?>" required>
        <div class="hint"><?= e(url('page.php')) ?>?p=<b>ชื่อนี้</b></div>
        <label class="chk top"><input type="checkbox" name="is_published" value="1" <?= $p['is_published'] ? 'checked' : '' ?>> เผยแพร่</label>
        <?php if ($p['id']): ?><p style="margin:12px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบหน้านี้</button></p><?php endif; ?>
      </section></aside>
    </div>
    </form>
    <?php if ($p['id']): ?><form id="del-form" method="post" data-confirm="ลบหน้านี้ถาวร?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"></form><?php endif;
    admin_footer();
    exit;
}

$rows = Database::all('SELECT * FROM pages ORDER BY title');
admin_header('หน้าเว็บ', 'pages');
?>
<div class="head">
  <div><h1>หน้าเว็บ</h1><p>เนื้อหาคงที่ที่เชื่อมจากเมนูและส่วนท้ายเว็บไซต์</p></div>
  <a class="btn big" href="<?= e(url('admin/pages.php', ['act' => 'new'])) ?>">+ เพิ่มหน้าเว็บ</a>
</div>
<section class="tbl"><div class="tw"><table>
  <thead><tr><th>ชื่อหน้า</th><th>URL</th><th>แก้ไขล่าสุด</th><th>สถานะ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): ?>
    <tr class="row"><td><b><?= e($p['title']) ?></b></td><td><a class="small" href="<?= e(page_url($p['slug'])) ?>" target="_blank" rel="noopener"><?= e($p['slug']) ?></a></td>
      <td class="small mute"><?= e(thai_date($p['updated_at'], 'time')) ?></td>
      <td><?= $p['is_published'] ? '<span class="pill ok">เผยแพร่</span>' : '<span class="pill mute">ซ่อน</span>' ?></td>
      <td class="r"><a class="btn sm ghost" href="<?= e(url('admin/pages.php', ['act' => 'edit', 'id' => $p['id']])) ?>">แก้ไข</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div></section>
<?php admin_footer();
