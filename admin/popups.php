<?php
declare(strict_types=1);

/** ตั้งป๊อปอัพประกาศ / เทศกาล — ช่วงเวลา ตำแหน่ง และเปิด/ปิด */

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::require();
Csrf::require();

$db = Database::pdo();
$placements = [
    'home' => 'หน้าแรกเท่านั้น',
    'main' => 'ทุกหน้าของเว็บไซต์หลัก (รวมหน้าข่าว)',
    'all'  => 'ทุกหน้า + เว็บไซต์ย่อยทุกหน่วยงาน',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $op = (string) ($_POST['op'] ?? '');
    if ($op === 'save') {
        $id       = (int) ($_POST['id'] ?? 0);
        $title    = trim((string) ($_POST['title'] ?? ''));
        $body     = trim((string) ($_POST['body'] ?? ''));
        $link     = trim((string) ($_POST['link_url'] ?? ''));
        $starts   = trim((string) ($_POST['starts_at'] ?? ''));
        $ends     = trim((string) ($_POST['ends_at'] ?? ''));
        $place    = (string) ($_POST['placement'] ?? 'home');
        $enabled  = ($_POST['enabled'] ?? '') === '1' ? 1 : 0;

        $starts = $starts !== '' && strtotime($starts) !== false ? date('Y-m-d H:i:s', strtotime($starts)) : date('Y-m-d H:i:s');
        $ends   = $ends !== '' && strtotime($ends) !== false ? date('Y-m-d H:i:s', strtotime($ends)) : date('Y-m-d H:i:s', strtotime('+7 days'));

        if ($title === '') {
            flash('error', 'กรุณากรอกหัวข้อป๊อปอัพ');
            redirect(url('admin/popups.php' . ($id ? '&edit=' . $id : '')));
        }
        if ($ends <= $starts) {
            flash('error', 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่มแสดง');
            redirect(url('admin/popups.php' . ($id ? '&edit=' . $id : '')));
        }
        if (!isset($placements[$place])) {
            $place = 'home';
        }
        try {
            if ($id > 0) {
                $db->prepare(
                    'UPDATE popups SET title=?, body=?, link_url=?, starts_at=?, ends_at=?, placement=?, enabled=?, updated_at=NOW() WHERE id=?'
                )->execute([$title, $body, $link, $starts, $ends, $place, $enabled, $id]);
            } else {
                $db->prepare(
                    'INSERT INTO popups (title, body, link_url, starts_at, ends_at, placement, enabled, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?, NOW(), NOW())'
                )->execute([$title, $body, $link, $starts, $ends, $place, $enabled]);
            }
            flash('ok', 'บันทึกป๊อปอัพแล้ว');
            redirect(url('admin/popups.php'));
        } catch (Throwable $ex) {
            flash('error', 'บันทึกไม่สำเร็จ: ' . $ex->getMessage());
        }
    }
    if ($op === 'delete') {
        $db->prepare('DELETE FROM popups WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'ลบป๊อปอัพแล้ว');
        redirect(url('admin/popups.php'));
    }
    if ($op === 'toggle') {
        $db->prepare('UPDATE popups SET enabled = 1 - enabled, updated_at = NOW() WHERE id = ?')
            ->execute([(int) ($_POST['id'] ?? 0)]);
        flash('ok', 'เปลี่ยนสถานะแล้ว');
        redirect(url('admin/popups.php'));
    }
}

/* ---------- ฟอร์ม ---------- */
if (isset($_GET['edit']) || (($_GET['form'] ?? '') === '1')) {
    $editId = (int) ($_GET['edit'] ?? 0);
    $row = null;
    if ($editId > 0) {
        $st = $db->prepare('SELECT * FROM popups WHERE id = ?');
        $st->execute([$editId]);
        $row = $st->fetch() ?: null;
    }
    $news = $db->query('SELECT id, title FROM news ORDER BY published_at DESC LIMIT 100')->fetchAll();
    $adminTitle = $row ? 'แก้ไขป๊อปอัพ' : 'ตั้งป๊อปอัพ';
    $navActive  = 'popups';
    include __DIR__ . '/_layout.php';
    ?>
    <h1><?= e($adminTitle) ?> — ป๊อปอัพประกาศ / เทศกาล</h1>
    <p class="sub">ตั้งช่วงเวลาแสดงและตำแหน่ง ระบบจะแสดงอัตโนมัติเมื่อเข้าช่วงเวลาที่กำหนด</p>
    <form method="post" class="panel">
      <?= Csrf::field() ?>
      <input type="hidden" name="op" value="save">
      <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

      <h2>1. เนื้อหาป๊อปอัพ</h2>
      <label>หัวข้อประกาศ *</label>
      <input type="text" name="title" required value="<?= e((string) ($row['title'] ?? '')) ?>" placeholder="เช่น วันพ่อแห่งชาติ 5 ธันวาคม">
      <label>รายละเอียด</label>
      <textarea name="body" style="min-height:90px"><?= e((string) ($row['body'] ?? '')) ?></textarea>
      <label>ลิงก์เป้าหมาย (เลือกข่าวในเว็บไซต์ หรือวางลิงก์)</label>
      <select name="link_news_id" id="link_news_id">
        <option value="">— เลือกข่าวในเว็บไซต์ —</option>
        <?php foreach ($news as $n): ?>
          <option value="<?= e(url('news_view.php?id=' . $n['id'])) ?>" <?= e((string) ($row['link_url'] ?? '')) === url('news_view.php?id=' . $n['id']) ? 'selected' : '' ?>>
            <?= e($n['title']) ?></option>
        <?php endforeach; ?>
        <option value="__custom">วางลิงก์เอง…</option>
      </select>
      <input type="text" name="link_url" value="<?= e((string) ($row['link_url'] ?? '')) ?>"
             placeholder="เช่น https://… หรือ news.php" id="link_url">
      <script>
      (function () {
        var sel = document.getElementById('link_news_id'), url = document.getElementById('link_url');
        if (!sel) return;
        sel.addEventListener('change', function () {
          if (this.value === '__custom') { url.value = ''; url.focus(); }
          else if (this.value !== '') { url.value = this.value; }
        });
      })();
      </script>

      <h2 style="margin-top:22px">2. ช่วงเวลาที่แสดง</h2>
      <div class="row">
        <div><label>เริ่มแสดง</label>
          <input type="datetime-local" name="starts_at"
            value="<?= e($row ? date('Y-m-d\TH:i', strtotime((string) $row['starts_at'])) : date('Y-m-d\TH:i')) ?>"></div>
        <div><label>สิ้นสุด</label>
          <input type="datetime-local" name="ends_at"
            value="<?= e($row ? date('Y-m-d\TH:i', strtotime((string) $row['ends_at'])) : date('Y-m-d\TH:i', strtotime('+7 days'))) ?>"></div>
      </div>

      <h2 style="margin-top:22px">3. ตำแหน่งและการเปิดใช้</h2>
      <div class="row">
        <div><label>แสดงที่</label>
          <select name="placement">
            <?php foreach ($placements as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= e((string) ($row['placement'] ?? 'home')) === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div><label style="margin-top:34px;display:flex;gap:8px;align-items:center;font-weight:400">
          <input type="checkbox" name="enabled" value="1" style="width:auto;min-height:auto" <?= $row === null || !empty($row['enabled']) ? 'checked' : '' ?>>
          เปิดใช้งานทันที</label></div>
      </div>

      <div class="btnrow">
        <button type="submit">บันทึกป๊อปอัพ</button>
        <a class="btn" style="background:#7a879c" href="<?= e(url('admin/popups.php')) ?>">ยกเลิก</a>
      </div>
    </form>
    <?php
    include __DIR__ . '/_layout_end.php';
    exit;
}

/* ---------- รายการ ---------- */
$popups = $db->query('SELECT * FROM popups ORDER BY starts_at DESC')->fetchAll();
$now = date('Y-m-d H:i:s');
$adminTitle = 'ป๊อปอัพประกาศ';
$navActive  = 'popups';
include __DIR__ . '/_layout.php';
?>
<h1>ป๊อปอัพประกาศ / เทศกาล</h1>
<p class="sub">แสดงอัตโนมัติบนหน้าเว็บตามช่วงเวลาที่กำหนด</p>
<div class="panel">
  <a class="btn" href="<?= e(url('admin/popups.php?form=1')) ?>">＋ ตั้งป๊อปอัพใหม่</a>
  <table class="list" style="margin-top:14px">
    <tr><th>หัวข้อ</th><th>ช่วงเวลา</th><th>ตำแหน่ง</th><th>สถานะ</th><th style="width:200px">จัดการ</th></tr>
    <?php foreach ($popups as $p):
        $live = $p['enabled'] === '1' && $p['starts_at'] <= $now && $now <= $p['ends_at']; ?>
    <tr>
      <td><?= e($p['title']) ?></td>
      <td class="muted"><?= e(fmt_dt($p['starts_at'])) ?> – <?= e(fmt_dt($p['ends_at'])) ?></td>
      <td><?= e($placements[$p['placement']] ?? $p['placement']) ?></td>
      <td>
        <?php if ($live): ?><span class="badge green">กำลังแสดง</span>
        <?php elseif ($p['starts_at'] > $now): ?><span class="badge amber">รอแสดง</span>
        <?php elseif ($p['ends_at'] < $now): ?><span class="badge red">หมดอายุ</span>
        <?php else: ?><span class="badge">ปิดใช้งาน</span><?php endif; ?>
      </td>
      <td>
        <a class="btn sm" style="background:#38445a" href="<?= e(url('admin/popups.php?edit=' . $p['id'])) ?>">แก้ไข</a>
        <form method="post" style="display:inline">
          <?= Csrf::field() ?>
          <input type="hidden" name="op" value="toggle">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="sm" type="submit"><?= $p['enabled'] === '1' ? 'ปิด' : 'เปิด' ?></button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('ลบป๊อปอัพนี้?');">
          <?= Csrf::field() ?>
          <input type="hidden" name="op" value="delete">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="sm warn" type="submit">ลบ</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php if (!$popups): ?><div class="empty" style="color:#7a879c;padding:20px">ยังไม่มีป๊อปอัพ</div><?php endif; ?>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
