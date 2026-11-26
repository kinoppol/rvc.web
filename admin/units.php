<?php
declare(strict_types=1);

/** จัดการหน่วยงาน (แผนกวิชา/หน่วยงานย่อย) */

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::require();
Csrf::require();

$db = Database::pdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', 'กรุณากรอกชื่อหน่วยงาน');
            redirect(url('admin/units.php' . ($id ? '&edit=' . $id : '')));
        }
        $data = [
            $name,
            trim((string) ($_POST['about'] ?? '')),
            trim((string) ($_POST['contact_name'] ?? '')),
            trim((string) ($_POST['contact_phone'] ?? '')),
            trim((string) ($_POST['contact_email'] ?? '')),
            (int) ($_POST['sort_order'] ?? 0),
        ];
        try {
            if ($id > 0) {
                $db->prepare(
                    'UPDATE units SET name=?, about=?, contact_name=?, contact_phone=?, contact_email=?, sort_order=?, updated_at=NOW() WHERE id=?'
                )->execute([...$data, $id]);
            } else {
                $db->prepare(
                    'INSERT INTO units (name, about, contact_name, contact_phone, contact_email, sort_order, created_at, updated_at)
                     VALUES (?,?,?,?,?,?, NOW(), NOW())'
                )->execute($data);
            }
            flash('ok', 'บันทึกหน่วยงานแล้ว');
            redirect(url('admin/units.php'));
        } catch (Throwable $ex) {
            flash('error', 'บันทึกไม่สำเร็จ: ' . $ex->getMessage());
        }
    }
    if ($op === 'delete') {
        $db->prepare('DELETE FROM units WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'ลบหน่วยงานแล้ว (ข่าวของหน่วยงานนี้จะกลายเป็นข่าวทั่วไป)');
        redirect(url('admin/units.php'));
    }
}

if (isset($_GET['edit']) || (($_GET['form'] ?? '') === '1')) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = null;
    if ($editId > 0) {
        $st = $db->prepare('SELECT * FROM units WHERE id = ?');
        $st->execute([$editId]);
        $row = $st->fetch() ?: null;
    }
    $adminTitle = $row ? 'แก้ไขหน่วยงาน' : 'เพิ่มหน่วยงาน';
    $navActive  = 'units';
    include __DIR__ . '/_layout.php';
    ?>
    <h1><?= e($adminTitle) ?></h1>
    <p class="sub">ข้อมูลหน่วยงานย่อยที่จะแสดงบนหน้าเว็บ</p>
    <form method="post" class="panel">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
      <label>ชื่อหน่วยงาน *</label>
      <input type="text" name="name" required value="<?= e((string) ($row['name'] ?? '')) ?>">
      <label>เกี่ยวกับหน่วยงาน</label>
      <textarea name="about" style="min-height:100px"><?= e((string) ($row['about'] ?? '')) ?></textarea>
      <div class="row">
        <div><label>ผู้ติดต่อ</label><input type="text" name="contact_name" value="<?= e((string) ($row['contact_name'] ?? '')) ?>"></div>
        <div><label>โทรศัพท์</label><input type="text" name="contact_phone" value="<?= e((string) ($row['contact_phone'] ?? '')) ?>"></div>
      </div>
      <div class="row">
        <div><label>อีเมล</label><input type="text" name="contact_email" value="<?= e((string) ($row['contact_email'] ?? '')) ?>"></div>
        <div><label>ลำดับจัดเรียง (น้อย = ก่อน)</label><input type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>"></div>
      </div>
      <div class="btnrow">
        <button type="submit">บันทึก</button>
        <a class="btn" style="background:#7a879c" href="<?= e(url('admin/units.php')) ?>">ยกเลิก</a>
      </div>
    </form>
    <?php
    include __DIR__ . '/_layout_end.php';
    exit;
}

$units = $db->query(
    'SELECT u.*, (SELECT COUNT(*) FROM news n WHERE n.unit_id = u.id) AS news_count FROM units u ORDER BY u.sort_order, u.name'
)->fetchAll();

$adminTitle = 'หน่วยงาน';
$navActive  = 'units';
include __DIR__ . '/_layout.php';
?>
<h1>หน่วยงาน</h1>
<p class="sub">จัดการหน่วยงาน/แผนกวิชาบนหน้าเว็บ — มี <?= count($units) ?> รายการ</p>
<div class="panel">
  <a class="btn" href="<?= e(url('admin/units.php?form=1')) ?>">＋ เพิ่มหน่วยงาน</a>
  <table class="list" style="margin-top:14px">
    <tr><th>ชื่อ</th><th>ติดต่อ</th><th>ข่าว</th><th style="width:190px">จัดการ</th></tr>
    <?php foreach ($units as $u): ?>
    <tr>
      <td><a href="<?= e(url('unit.php?id=' . $u['id'])) ?>" target="_blank"><?= e($u['name']) ?></a></td>
      <td class="muted">
        <?= $u['contact_name'] !== '' ? e($u['contact_name']) : '—' ?>
        <?= $u['contact_phone'] !== '' ? ' · ' . e($u['contact_phone']) : '' ?>
      </td>
      <td><?= (int) $u['news_count'] ?></td>
      <td>
        <a class="btn sm" style="background:#38445a" href="<?= e(url('admin/units.php?edit=' . $u['id'])) ?>">แก้ไข</a>
        <form method="post" style="display:inline" onsubmit="return confirm('ลบหน่วยงานนี้? ข่าวที่เกี่ยวข้องจะกลายเป็นข่าวทั่วไป (ไม่ถูกลบ)');">
          <?= Csrf::field() ?>
          <input type="hidden" name="op" value="delete">
          <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
          <button class="sm warn" type="submit">ลบ</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$units): ?><div class="empty" style="color:#7a879c;padding:20px">ยังไม่มีหน่วยงาน</div><?php endif; ?>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
