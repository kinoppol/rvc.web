<?php
declare(strict_types=1);

/** การตั้งค่าเว็บไซต์ */

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::require();
Csrf::require();

$db = Database::pdo();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ((string) ($_POST['op'] ?? '') === 'save') {
        Settings::set('site_name', trim((string) ($_POST['site_name'] ?? '')));
        Settings::set('site_footer', trim((string) ($_POST['site_footer'] ?? '')));
        Settings::set('principal_title', trim((string) ($_POST['principal_title'] ?? '')));
        Settings::set('principal_message', (string) ($_POST['principal_message'] ?? ''));
        flash('ok', 'บันทึกการตั้งค่าแล้ว');
        redirect(url('admin/settings.php'));
    }
}

$adminTitle = 'การตั้งค่า';
$navActive  = 'settings';
include __DIR__ . '/_layout.php';
?>
<h1>การตั้งค่าเว็บไซต์</h1>
<p class="sub">ข้อมูลพื้นฐานที่ใช้แสดงผลบนหน้าเว็บ</p>
<form method="post" class="panel">
  <?= Csrf::field() ?>
  <input type="hidden" name="op" value="save">
  <label>ชื่อเว็บไซต์</label>
  <input type="text" name="site_name" value="<?= e(Settings::get('site_name')) ?>">
  <label>ข้อความท้ายหน้าเว็บ (footer)</label>
  <input type="text" name="site_footer" value="<?= e(Settings::get('site_footer')) ?>">
  <label>หัวข้อสารจากผู้อำนวยการ</label>
  <input type="text" name="principal_title" value="<?= e(Settings::get('principal_title', 'สารจากผู้อำนวยการ')) ?>">
  <label>เนื้อหาสารจากผู้อำนวยการ (แต่ละบรรทัด = 1 ย่อหน้า)</label>
  <textarea name="principal_message"><?= e(Settings::get('principal_message')) ?></textarea>
  <div class="btnrow">
    <button type="submit">บันทึกการตั้งค่า</button>
  </div>
</form>

<div class="panel">
  <h2>ข้อมูลระบบ</h2>
  <table class="list">
    <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
    <tr><th>ฐานข้อมูล</th><td><?= e((string) $db->query('SELECT VERSION()')->fetchColumn()) ?></td></tr>
    <tr><th>ติดตั้งเมื่อ</th><td><?= e(date('Y-m-d H:i:s', filemtime(BASE_PATH . '/config/config.php'))) ?></td></tr>
  </table>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
