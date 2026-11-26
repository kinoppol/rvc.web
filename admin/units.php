<?php
declare(strict_types=1);

/* เว็บไซต์ย่อยหน่วยงาน — แผนกวิชา ฝ่าย และงาน (เทมเพลตเดียวใช้ได้ทุกหน่วยงาน) */

require __DIR__ . '/_init.php';
Auth::require('admin');
Csrf::verify();

$id  = input_int('id');
$act = input('act');
$old = $id ? Database::one('SELECT * FROM units WHERE id = ?', [$id]) : null;
if ($id && !$old) {
    abort(404, 'ไม่พบหน่วยงานนี้');
}
$divisions = Database::all("SELECT id, name FROM units WHERE kind = 'division' ORDER BY sort, name");

if (is_post()) {
    if ($act === 'delete' && $old) {
        $children = (int) Database::value('SELECT COUNT(*) FROM units WHERE parent_id = ?', [$id]);
        if ($children > 0) {
            flash('err', 'ลบไม่ได้ — ยังมีงานในสังกัด ' . $children . ' รายการ ย้ายหรือลบงานเหล่านั้นก่อน');
            redirect(url('admin/units.php', ['act' => 'edit', 'id' => $id]));
        }
        Database::run('UPDATE users SET unit_id = NULL WHERE unit_id = ?', [$id]);
        Database::delete('units', $id);
        delete_upload($old['cover']);
        flash('ok', 'ลบ "' . unit_title($old) . '" แล้ว (ข่าวของหน่วยงานนี้ยังอยู่ แต่ไม่ผูกกับหน่วยงาน)');
        redirect(url('admin/units.php'));
    }
    $kind = array_key_exists(input('kind'), UNIT_KINDS) ? input('kind') : 'department';
    $data = [
        'name'      => mb_substr(input('name'), 0, 160),
        'name_en'   => mb_substr(strtoupper(input('name_en')), 0, 160),
        'slug'      => slugify(input('slug')),
        'kind'      => $kind,
        'group_key' => $kind === 'department' && isset(UNIT_GROUPS[input('group_key')]) ? input('group_key') : 'org',
        'parent_id' => $kind === 'work' ? (input_int('parent_id') ?: null) : null,
        'intro'     => input('intro'),
        'contact'   => input('contact'),
        'sort'      => input_int('sort'),
        'is_active' => (int) !empty($_POST['is_active']),
    ];
    $_SESSION['_unit_form'] = $_POST;
    $errors = [];
    if ($data['name'] === '') {
        $errors[] = 'กรุณากรอกชื่อหน่วยงาน';
    }
    if ($data['slug'] === '') {
        $errors[] = 'กรุณากรอกชื่อ URL (ภาษาอังกฤษ a-z, 0-9, -)';
    } elseif (Database::value('SELECT id FROM units WHERE slug = ? AND id <> ?', [$data['slug'], $id])) {
        $errors[] = 'ชื่อ URL "' . $data['slug'] . '" ถูกใช้แล้ว';
    }
    if ($kind === 'work' && !$data['parent_id']) {
        $errors[] = 'งานต้องเลือกฝ่ายที่สังกัด';
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
        redirect(url('admin/units.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    if ($cover) {
        $data['cover'] = $cover;
    } elseif (!empty($_POST['remove_cover'])) {
        $data['cover'] = null;
    }
    if ($old) {
        Database::update('units', $data, $id);
        if (array_key_exists('cover', $data)) {
            delete_upload($old['cover']);
        }
    } else {
        $id = Database::insert('units', $data);
    }
    unset($_SESSION['_unit_form']);
    flash('ok', 'บันทึก "' . unit_title($data) . '" แล้ว');
    redirect(url('admin/units.php', ['act' => 'edit', 'id' => $id]));
}

if ($act === 'new' || $old) {
    $u = $old ?? ['id' => 0, 'name' => '', 'name_en' => '', 'slug' => '', 'kind' => input('kind') ?: 'department', 'group_key' => 'biz',
        'parent_id' => null, 'intro' => '', 'contact' => '', 'cover' => null, 'sort' => 0, 'is_active' => 1];
    if (!empty($_SESSION['_unit_form'])) {
        $u = array_merge($u, array_intersect_key($_SESSION['_unit_form'], array_flip(['name', 'name_en', 'slug', 'kind', 'group_key', 'parent_id', 'intro', 'contact', 'sort'])));
        unset($_SESSION['_unit_form']);
    }
    admin_header($u['id'] ? 'แก้ไขหน่วยงาน' : 'เพิ่มหน่วยงาน', 'units');
    ?>
    <form method="post" enctype="multipart/form-data">
    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/units.php')) ?>">‹ กลับไปรายการหน่วยงาน</a><h1><?= $u['id'] ? e(unit_title($u)) : 'เพิ่มหน่วยงาน' ?></h1></div>
      <div class="acts"><?php if ($u['id']): ?><a class="btn ghost" href="<?= e(unit_url($u)) ?>" target="_blank" rel="noopener">ดูหน้าเว็บ</a><?php endif; ?>
        <button class="btn" type="submit">บันทึก</button></div>
    </div>
    <div class="cols">
      <section class="card wide" style="display:block">
        <div class="grid2">
          <div><label class="f" for="kind">ประเภท</label><select id="kind" name="kind"><?php foreach (UNIT_KINDS as $k => $v): ?><option value="<?= $k ?>" <?= $u['kind'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div><label class="f" for="nm">ชื่อ <span class="opt">(แผนกวิชาไม่ต้องใส่คำว่า "แผนกวิชา")</span></label><input id="nm" name="name" value="<?= e($u['name']) ?>" required></div>
        </div>
        <div class="grid2">
          <div><label class="f" for="en">ชื่อภาษาอังกฤษ</label><input id="en" name="name_en" value="<?= e($u['name_en']) ?>" placeholder="DEPARTMENT OF ..."></div>
          <div><label class="f" for="sl">ชื่อ URL</label><input id="sl" name="slug" value="<?= e($u['slug']) ?>" pattern="[A-Za-z0-9\- ]+" placeholder="hotel" required>
            <div class="hint"><?= e(url('unit.php')) ?>?u=<b>ชื่อนี้</b></div></div>
        </div>
        <div class="grid2">
          <div><label class="f" for="gk">กลุ่มสาขา / สีประจำ <span class="opt">(เฉพาะแผนกวิชา)</span></label>
            <select id="gk" name="group_key"><?php foreach (UNIT_GROUPS as $k => [$lbl]): ?><option value="<?= $k ?>" <?= $u['group_key'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
          <div><label class="f" for="pa">สังกัดฝ่าย <span class="opt">(เฉพาะงาน)</span></label>
            <select id="pa" name="parent_id"><option value="">—</option><?php foreach ($divisions as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $u['parent_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <label class="f" for="in">เกี่ยวกับหน่วยงาน <span class="opt">(คำแนะนำ 3–4 บรรทัด)</span></label>
        <textarea id="in" name="intro"><?= e($u['intro']) ?></textarea>
        <label class="f" for="ct">ข้อมูลติดต่อ <span class="opt">(อาคาร ชั้น โทร อีเมล — บรรทัดละรายการ)</span></label>
        <textarea id="ct" name="contact" style="min-height:90px"><?= e($u['contact']) ?></textarea>
      </section>
      <aside class="narrow">
        <section class="card">
          <h2>ภาพหน้าปก</h2>
          <div class="drop"><img id="cv" src="<?= e(upload_url($u['cover']) ?? '') ?>" alt="" <?= $u['cover'] ? '' : 'hidden' ?>>
            <input type="file" name="cover" accept="image/*" data-preview="#cv" aria-label="ภาพหน้าปก"><div class="hint">แนะนำ 16:10</div>
            <?php if ($u['cover']): ?><label class="chk" style="justify-content:center"><input type="checkbox" name="remove_cover" value="1"> ลบรูป</label><?php endif; ?></div>
        </section>
        <section class="card">
          <label class="f" for="so">ลำดับการแสดง</label><input id="so" type="number" name="sort" value="<?= (int) $u['sort'] ?>">
          <label class="chk top"><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>> แสดงบนเว็บไซต์</label>
          <?php if ($u['id']): ?><p style="margin:12px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบหน่วยงานนี้</button></p><?php endif; ?>
        </section>
      </aside>
    </div>
    </form>
    <?php if ($u['id']): ?><form id="del-form" method="post" data-confirm="ลบหน่วยงานนี้?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form><?php endif;
    admin_footer();
    exit;
}

$rows = Database::all("SELECT u.*, p.name AS parent_name,
        (SELECT COUNT(*) FROM news n WHERE n.unit_id = u.id) AS news_count,
        (SELECT COUNT(*) FROM users s WHERE s.unit_id = u.id) AS user_count
    FROM units u LEFT JOIN units p ON p.id = u.parent_id
    ORDER BY FIELD(u.kind,'department','division','work'), COALESCE(p.sort, u.sort), u.parent_id IS NOT NULL, u.sort, u.name");
admin_header('เว็บไซต์ย่อยหน่วยงาน', 'units');
?>
<div class="head">
  <div><h1>เว็บไซต์ย่อยหน่วยงาน</h1><p>แต่ละหน่วยงานมีหน้าเว็บของตัวเอง สีประจำกลุ่ม ไอคอน และเมนูปรับตามประเภทอัตโนมัติ</p></div>
  <div class="acts"><a class="btn big" href="<?= e(url('admin/units.php', ['act' => 'new'])) ?>">+ เพิ่มหน่วยงาน</a></div>
</div>
<section class="tbl"><div class="tw"><table style="min-width:720px">
  <thead><tr><th>หน่วยงาน</th><th>ประเภท</th><th>URL</th><th class="r">ข่าว</th><th class="r">ผู้ใช้</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $u): $g = unit_group($u['group_key']); ?>
    <tr class="row">
      <td><div class="ncell"><span style="width:10px;height:28px;border-radius:6px 6px 0 0;background:<?= e($g['color']) ?>;flex:none"></span>
        <div><b><?= e(unit_title($u)) ?></b><?= $u['is_active'] ? '' : ' <span class="pill mute">ซ่อน</span>' ?>
        <div class="small mute"><?= e($u['parent_name'] ?? ($u['kind'] === 'department' ? $g['label'] : '')) ?></div></div></div></td>
      <td class="mute"><?= e(UNIT_KINDS[$u['kind']] ?? $u['kind']) ?></td>
      <td><a class="small" href="<?= e(unit_url($u)) ?>" target="_blank" rel="noopener"><?= e($u['slug']) ?></a></td>
      <td class="r"><?= (int) $u['news_count'] ?></td><td class="r"><?= (int) $u['user_count'] ?></td>
      <td class="r"><a class="btn sm ghost" href="<?= e(url('admin/units.php', ['act' => 'edit', 'id' => $u['id']])) ?>">แก้ไข</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></section>
<?php admin_footer();
