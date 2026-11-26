<?php
declare(strict_types=1);

/**
 * เมนู Migrations — ผู้ดูแลจัดการการปรับปรุงโครงสร้างฐานข้อมูล
 * รันรายการที่ยังไม่ทำ (เรียงตามเวอร์ชัน) หรือถอยรายการทีละตัว
 */

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::require();
Csrf::require();

$db = Database::pdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action  = (string) ($_POST['action'] ?? '');
    $version = trim((string) ($_POST['version'] ?? ''));

    if ($action === 'run_all') {
        $done = 0;
        $failed = null;
        foreach (Migrate::status($db) as $r) {
            if ($r['applied'] !== null) {
                continue;
            }
            $res = Migrate::run($db, $r['version']);
            if ($res['ok']) {
                $done++;
            } else {
                $failed = $r['version'] . ' — ' . $r['name'] . ': ' . $res['error'];
                break;
            }
        }
        flash($failed === null ? 'ok' : 'error', $failed === null
            ? ($done > 0 ? "ติดตั้ง migration สำเร็จ $done รายการ — โครงสร้างเป็นปัจจุบันแล้ว" : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว — ไม่มีการเปลี่ยนแปลง')
            : 'การติดตั้งหยุดที่ ' . $failed);
    } elseif ($action === 'run' && $version !== '') {
        $res = Migrate::run($db, $version);
        flash($res['ok'] ? 'ok' : 'error', $res['ok']
            ? "ติดตั้ง migration '{$version}' สำเร็จ ({$res['sql']} คำสั่ง)"
            : 'ไม่สามารถติดตั้งได้: ' . $res['error']);
    } elseif ($action === 'rollback' && $version !== '') {
        $res = Migrate::rollback($db, $version);
        flash($res['ok'] ? 'ok' : 'error', $res['ok']
            ? "ถอย migration '{$version}' แล้ว — ระวัง: ข้อมูลในตารางที่เกี่ยวข้องอาจสูญหาย"
            : 'ไม่สามารถถอยได้: ' . $res['error']);
    }
    redirect(url('admin/migrations.php'));
}

$list = Migrate::status($db);
$pending = count(array_filter($list, fn (array $r) => $r['applied'] === null));

$adminTitle = 'Migrations';
$navActive  = 'migrations';
include __DIR__ . '/_layout.php';
?>
<h1>Migrations — โครงสร้างฐานข้อมูล</h1>
<p class="sub">
   จัดการการปรับปรุงโครงสร้างตาราง: รันรายการรอติดตั้ง (เรียงตามเวอร์ชัน) หรือถอยรายการที่ติดตั้งแล้ว<br>
   DDL ใน MySQL/MariaDB ยืนยันคำสั่งทันที (commit) — รายการที่สำเร็จจะบันทึกรวมอยู่ในตาราง
   <code>migrations</code> จึงรันซ้ำได้ปลอดภัย (รันเฉพาะรายการที่ยังไม่ได้ทำเท่านั้น)
</p>
<?php if ($pending > 0): ?>
<div class="flash amber" style="background:#fdf3d8;border:1px solid #ecd9a0;color:#5c4600">
  มี migration รอติดตั้ง <b><?= $pending ?></b> รายการ — กดปุ่มด้านล่างเพื่อรัน
</div>
<?php endif; ?>

<div class="panel">
  <h2>รายการ migration (<?= count($list) ?> รายการ · ติดตั้งแล้ว <?= count($list) - $pending ?>)</h2>
  <table class="list">
    <tr><th>เวอร์ชัน</th><th>คำอธิบาย</th><th>สถานะ</th><th style="width:210px">การจัดการ</th></tr>
    <?php foreach ($list as $r): ?>
    <tr>
      <td><code><?= e($r['version']) ?></code></td>
      <td><?= e($r['name']) ?></td>
      <td>
        <?php if ($r['applied'] !== null): ?>
          <span class="badge green">ติดตั้งแล้ว · <?= e(fmt_dt($r['applied'])) ?></span>
        <?php else: ?>
          <span class="badge amber">รอติดตั้ง</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($r['applied'] === null): ?>
          <form method="post" style="margin:0">
            <?= Csrf::field() ?>
            <input type="hidden" name="version" value="<?= e($r['version']) ?>">
            <input type="hidden" name="action" value="run">
            <button class="sm" type="submit">ติดตั้ง</button>
          </form>
        <?php else: ?>
          <form method="post" style="margin:0" onsubmit="return confirm('ถอย migration <?= e($r['version']) ?> นี้? ข้อมูลในตารางที่เกี่ยวข้องอาจสูญหาย');">
            <?= Csrf::field() ?>
            <input type="hidden" name="version" value="<?= e($r['version']) ?>">
            <input type="hidden" name="action" value="rollback">
            <button class="sm warn" type="submit">ถอย (rollback)</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php if ($pending > 0): ?>
  <form method="post" style="margin-top:16px">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="run_all">
    <button type="submit">▶ ติดตั้งรายการรอทั้งหมด (<?= $pending ?> รายการ)</button>
  </form>
  <?php endif; ?>
</div>

<div class="panel">
  <h2>วิธีเพิ่ม migration ใหม่</h2>
  <p style="margin:0;font-size:14px;color:#33405a">
    สร้างไฟล์ใหม่ในโฟลเดอร์ <code>/migrations</code> ชื่อ <code>NNNN_ชื่อ.php</code> (เช่น <code>0004_gallery.php</code>)
    โดยคืนค่าอาร์เรย์ที่มี <code>name</code>, <code>up</code> (SQL สร้าง/ปรับโครงสร้าง) และ <code>down</code> (วิธีถอยกลับ)<br>
    แล้วเปิดหน้านี้และกดติดตั้ง — ระบบจำหมายเลขเวอร์ชันในตาราง <code>migrations</code> จึงรันซ้ำได้ปลอดภัย
  </p>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
