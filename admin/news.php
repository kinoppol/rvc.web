<?php
declare(strict_types=1);

/** จัดการข่าว: รายการ / เขียน-แก้ไข / ลบ + อัปโหลดรูป */

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::require();
Csrf::require();

$db  = Database::pdo();
$cat = news_categories();

/* ---------------- POST ---------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $op = (string) ($_POST['op'] ?? '');

    if ($op === 'save') {
        $id      = (int) ($_POST['id'] ?? 0);
        $title   = trim((string) ($_POST['title'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $body    = trim((string) ($_POST['body'] ?? ''));
        $c       = (string) ($_POST['category'] ?? $cat[0]);
        $unitId  = (int) ($_POST['unit_id'] ?? 0);
        $isLead  = ($_POST['is_lead'] ?? '') === '1' ? 1 : 0;
        $when    = (string) ($_POST['published_at'] ?? '');
        $when    = $when !== '' && strtotime($when) !== false ? date('Y-m-d H:i:s', strtotime($when)) : date('Y-m-d H:i:s');

        if ($title === '') {
            flash('error', 'กรุณากรอกหัวข้อข่าว');
            redirect(url('admin/news.php' . ($id ? '&edit=' . $id : '')));
        }

        // อัปโหลดรูป (ถ้ามี)
        $oldImage = (string) ($_POST['old_image'] ?? '');
        $newImage = $oldImage;
        if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $f = $_FILES['image'];
            $fin = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $fin->file($f['tmp_name']);
            $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($map[$mime]) || $f['size'] > 5 * 1024 * 1024) {
                flash('error', 'รูปไม่ถูกต้อง — ใช้ JPEG/PNG/WebP/GIF ไม่เกิน 5MB');
            } else {
                $dir = BASE_PATH . '/data/uploads';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                $newName = 'n' . date('Ymd') . '_' . bin2hex(random_bytes(5)) . '.' . $map[$mime];
                if (move_uploaded_file($f['tmp_name'], $dir . '/' . $newName)) {
                    if ($oldImage !== '' && is_file($dir . '/' . $oldImage)) {
                        @unlink($dir . '/' . $oldImage);
                    }
                    $newImage = $newName;
                } else {
                    flash('error', 'บันทึกไฟล์รูปไม่สำเร็จ — ตรวจสิทธิ์เขียน data/uploads');
                }
            }
        }
        // ลบรูปเดิม
        if (($_POST['del_image'] ?? '') === '1' && $oldImage !== '') {
            $dir = BASE_PATH . '/data/uploads';
            if (is_file($dir . '/' . $oldImage)) {
                @unlink($dir . '/' . $oldImage);
            }
            $newImage = '';
        }

        try {
            if ($id > 0) {
                $st = $db->prepare(
                    'UPDATE news SET title=?, summary=?, body=?, category=?, unit_id=?, image_path=?, is_lead=?, published_at=?, updated_at=NOW() WHERE id=?'
                );
                $st->execute([$title, mb_substr($summary, 0, 500), $body, $c, $unitId ?: null, $newImage, $isLead, $when, $id]);
            } else {
                $st = $db->prepare(
                    'INSERT INTO news (title, summary, body, category, unit_id, image_path, is_lead, published_at, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?, ?, NOW(), NOW())'
                );
                $st->execute([$title, mb_substr($summary, 0, 500), $body, $c, $unitId ?: null, $newImage, $isLead, $when]);
                $id = (int) $db->lastInsertId();
            }
            if ($isLead) {
                $db->prepare('UPDATE news SET is_lead = 0 WHERE id <> ?')->execute([$id]);
            }
            flash('ok', 'บันทึกข่าวแล้ว');
            redirect(url('admin/news.php'));
        } catch (Throwable $ex) {
            flash('error', 'บันทึกไม่สำเร็จ: ' . $ex->getMessage());
            redirect(url('admin/news.php?edit=' . $id));
        }
    }

    if ($op === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $db->prepare('SELECT image_path FROM news WHERE id = ?');
        $st->execute([$id]);
        $img = (string) ($st->fetchColumn() ?: '');
        $db->prepare('DELETE FROM news WHERE id = ?')->execute([$id]);
        if ($img !== '') {
            $dir = BASE_PATH . '/data/uploads';
            if (is_file($dir . '/' . $img)) {
                @unlink($dir . '/' . $img);
            }
        }
        flash('ok', 'ลบข่าวแล้ว');
        redirect(url('admin/news.php'));
    }
}

/* ---------------- แสดง ---------------- */
if (isset($_GET['edit']) || (($_GET['form'] ?? '') === '1')) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = null;
    if ($editId > 0) {
        $st = $db->prepare('SELECT * FROM news WHERE id = ?');
        $st->execute([$editId]);
        $row = $st->fetch() ?: null;
    }
    $units = $db->query('SELECT id, name FROM units ORDER BY name')->fetchAll();
    $adminTitle = $row ? 'แก้ไขข่าว' : 'เขียนข่าว';
    $navActive  = 'news';
    include __DIR__ . '/_layout.php';
    ?>
    <h1><?= e($adminTitle) ?></h1>
    <p class="sub">พิมพ์หรือวางข้อความจาก Word ได้เลย ระบบจัดย่อหน้าให้อัตโนมัติ</p>
    <form method="post" enctype="multipart/form-data" class="panel">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
      <input type="hidden" name="old_image" value="<?= e((string) ($row['image_path'] ?? '')) ?>">
      <label>หัวข้อข่าว *</label>
      <input type="text" name="title" required value="<?= e((string) ($row['title'] ?? '')) ?>"
             placeholder="เช่น นักศึกษาแผนกการโรงแรมฝึกงานโรงแรมระดับสี่ดาว">
      <label>สรุปข่าว (แสดงบนหน้าแรก)</label>
      <input type="text" name="summary" value="<?= e((string) ($row['summary'] ?? '')) ?>" maxlength="500">
      <div class="row">
        <div>
          <label>หมวดหมู่</label>
          <select name="category">
            <?php foreach ($cat as $c): ?>
              <option value="<?= e($c) ?>" <?= e((string) ($row['category'] ?? $cat[0])) === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>หน่วยงาน</label>
          <select name="unit_id">
            <option value="0">ทุกหน่วยงาน / ไม่ระบุ</option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= (int) ($row['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>เผยแพร่เมื่อ</label>
          <input type="datetime-local" name="published_at"
                 value="<?= e(($row['published_at'] ?? '') !== '' ? date('Y-m-d\TH:i', strtotime((string) $row['published_at'])) : date('Y-m-d\TH:i')) ?>">
        </div>
      </div>
      <label>เนื้อหาข่าว</label>
      <textarea name="body" placeholder="พิมพ์หรือวางข้อความจาก Word ได้เลย"><?= e((string) ($row['body'] ?? '')) ?></textarea>
      <div class="row">
        <div>
          <label>รูปประกอบ (JPEG/PNG/WebP/GIF ≤ 5MB)</label>
          <input type="file" name="image" accept="image/*">
          <?php if (!empty($row['image_path'])): ?>
            <p class="muted">รูปปัจจุบัน: <a href="<?= e(url('data/uploads/' . $row['image_path'])) ?>" target="_blank">ดูรูป</a>
            <label style="display:inline;font-weight:400;margin-left:10px"><input type="checkbox" name="del_image" value="1" style="width:auto;min-height:auto"> ลบรูป</label></p>
          <?php endif; ?>
        </div>
        <div>
          <label style="margin-top:34px">การเผยแพร่</label>
          <label style="font-weight:400;display:flex;gap:8px;align-items:center">
            <input type="checkbox" name="is_lead" value="1" style="width:auto;min-height:auto"
              <?= !empty($row['is_lead']) ? 'checked' : '' ?>> แสดงเป็นข่าวเด่นบนหน้าแรก
          </label>
        </div>
      </div>
      <div class="btnrow">
        <button type="submit">บันทึกข่าว</button>
        <a class="btn" style="background:#7a879c" href="<?= e(url('admin/news.php')) ?>">ยกเลิก</a>
      </div>
    </form>
    <?php
    include __DIR__ . '/_layout_end.php';
    exit;
}

/* รายการข่าว */
$q  = trim((string) ($_GET['q'] ?? ''));
$c  = trim((string) ($_GET['cat'] ?? ''));
$sql = 'SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id';
$args = [];
if ($q !== '') { $sql .= ' WHERE n.title LIKE ?'; $args[] = '%' . $q . '%'; }
if ($c !== '' && in_array($c, $cat, true)) { $sql .= ($q !== '' ? ' AND' : ' WHERE') . ' n.category = ?'; $args[] = $c; }
$sql .= ' ORDER BY n.published_at DESC';
$st = $db->prepare($sql);
$st->execute($args);
$news = $st->fetchAll();

$adminTitle = 'ข่าวสาร';
$navActive  = 'news';
include __DIR__ . '/_layout.php';
?>
<h1>ข่าวและประกาศ</h1>
<p class="sub">จัดการข่าวบนหน้าเว็บ — มี <?= count($news) ?> รายการ</p>

<div class="panel">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="ค้นหาจากหัวข้อข่าว" style="flex:1 1 220px">
    <select name="cat" style="flex:0 1 200px">
      <option value="">ทุกหมวด</option>
      <?php foreach ($cat as $cc): ?>
        <option value="<?= e($cc) ?>" <?= $c === $cc ? 'selected' : '' ?>><?= e($cc) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" style="flex:0">ค้นหา</button>
    <a class="btn" href="<?= e(url('admin/news.php?form=1')) ?>">＋ เขียนข่าว</a>
  </form>

  <table class="list">
    <tr><th>หัวข้อ</th><th>หมวด</th><th>หน่วยงาน</th><th>เผยแพร่</th><th style="width:180px">จัดการ</th></tr>
    <?php foreach ($news as $n): ?>
    <tr>
      <td>
        <?php if ($n['is_lead']): ?><span class="badge blue">เด่น</span> <?php endif; ?>
        <a href="<?= e(url('news_view.php?id=' . $n['id'])) ?>" target="_blank"><?= e($n['title']) ?></a>
        <?php if ($n['published_at'] > date('Y-m-d H:i:s')): ?><span class="badge amber">ยังไม่ถึงเวลาเผยแพร่</span><?php endif; ?>
      </td>
      <td><?= e($n['category']) ?></td>
      <td><?= e($n['unit_name'] ?? '—') ?></td>
      <td class="muted"><?= e(fmt_dt($n['published_at'])) ?></td>
      <td>
        <a class="btn sm" style="background:#38445a" href="<?= e(url('admin/news.php?edit=' . $n['id'])) ?>">แก้ไข</a>
        <form method="post" style="display:inline" onsubmit="return confirm('ลบข่าวนี้?');">
          <?= Csrf::field() ?>
          <input type="hidden" name="op" value="delete">
          <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
          <button class="sm warn" type="submit">ลบ</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$news): ?><div class="empty" style="color:#7a879c;padding:20px">ไม่พบข่าว</div><?php endif; ?>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
