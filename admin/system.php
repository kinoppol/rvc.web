<?php
declare(strict_types=1);

/* ตรวจสอบระบบ — แพ็กเกจ PHP และสิทธิ์อ่าน/เขียนไฟล์ (ชุดเดียวกับตัวติดตั้ง) */

require __DIR__ . '/_init.php';
Auth::require('admin');

$checks = Requirements::check(false);
$ok     = Requirements::passes($checks);
$server = Requirements::checkServer(Database::pdo());
$lock   = json_decode((string) @file_get_contents(BASE_PATH . '/storage/installed.lock'), true) ?: [];

admin_header('ตรวจสอบระบบ', 'system', 'ผู้ดูแลระบบ › ตรวจสอบระบบ');
?>
<div class="head">
  <div><h1>ตรวจสอบระบบ</h1><p>PHP <?= e(PHP_VERSION) ?> · <?= e($server[1]) ?> · ติดตั้งเมื่อ <?= e(thai_date($lock['installed_at'] ?? null, 'time')) ?> (v<?= e($lock['version'] ?? '-') ?>)</p></div>
  <div class="acts"><a class="btn ghost" href="<?= e(url('admin/system.php')) ?>">↻ ตรวจอีกครั้ง</a><a class="btn navy" href="<?= e(url('install.php')) ?>">เปิดตัวติดตั้ง</a></div>
</div>
<div class="msg <?= $ok && $server[0] ? 'ok' : 'err' ?>"><?= $ok && $server[0] ? 'ระบบพร้อมใช้งาน — รายการที่จำเป็นผ่านทั้งหมด' : 'มีรายการที่จำเป็นไม่ผ่าน บางความสามารถอาจใช้งานไม่ได้' ?></div>
<section class="tbl">
  <div class="tw"><table>
    <thead><tr><th>กลุ่ม</th><th>รายการ</th><th>รายละเอียด</th><th>ผล</th></tr></thead>
    <tbody>
    <tr class="row"><td>ฐานข้อมูล</td><td><b>MariaDB <?= e(Requirements::MARIADB_MIN) ?>+</b></td><td class="mute"><?= e($server[1]) ?></td>
      <td><?= $server[0] ? '<span class="pill ok">ผ่าน</span>' : '<span class="pill bad">ไม่ผ่าน</span>' ?></td></tr>
    <?php foreach ($checks as $c): ?>
      <tr class="row"><td class="small mute nowrap"><?= e($c['group']) ?></td><td><b><?= e($c['name']) ?></b></td><td class="mute small"><?= e($c['detail']) ?></td>
        <td><?= $c['ok'] ? '<span class="pill ok">ผ่าน</span>' : ($c['required'] ? '<span class="pill bad">ไม่ผ่าน</span>' : '<span class="pill warn">แนะนำ</span>') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php admin_footer();
