<?php
declare(strict_types=1);

require __DIR__ . '/../core/bootstrap.php';
require_installed();

/* ---------- เข้าสู่ระบบ / แผงควบคุม ---------- */
if (Auth::user() === null) {
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $u = trim((string) ($_POST['username'] ?? ''));
        $p = (string) ($_POST['password'] ?? '');
        if ($u === '' || $p === '') {
            $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
        } elseif (Auth::attempt($u, $p)) {
            redirect(url('admin/index.php'));
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>เข้าสู่ระบบผู้ดูแล</title>
    <link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>">
    </head>
    <body>
    <div class="login-wrap">
      <div class="login-box">
        <h1>เข้าสู่ระบบผู้ดูแล</h1>
        <p class="sub"><?= e(Settings::get('site_name', 'เว็บไซต์')) ?></p>
        <?php if ($error !== ''): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <label>ชื่อผู้ใช้</label>
          <input type="text" name="username" required autofocus>
          <label>รหัสผ่าน</label>
          <input type="password" name="password" required>
          <button type="submit" style="width:100%;margin-top:18px">เข้าสู่ระบบ</button>
        </form>
        <p class="muted" style="margin-top:16px"><a href="<?= e(url('index.php')) ?>">← กลับหน้าเว็บ</a> ·
        ลืมรหัสผ่าน? ใช้ขั้นตอนที่ 4 ของ <a href="<?= e(url('install.php')) ?>">install.php</a></p>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

/* ---------- แผงควบคุม ---------- */
Auth::require();
Csrf::require();

$db  = Database::pdo();
$cnt = static fn (string $t): int => (int) $db->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
$newsCount = $cnt('news');
$unitCount = $cnt('units');
$popupCount = (int) $db->query('SELECT COUNT(*) FROM popups WHERE enabled = 1 AND starts_at <= NOW() AND ends_at >= NOW()')->fetchColumn();

Migrate::ensureTable($db);
$applied = (int) $db->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
$total   = count(Migrate::files());
$pending = $total - $applied;
$last    = $db->query('SELECT version, name, applied_at FROM migrations ORDER BY applied_at DESC LIMIT 1')->fetch() ?: null;

$adminTitle = 'แผงควบคุม';
$navActive  = 'dashboard';
include __DIR__ . '/_layout.php';
?>
<h1>แผงควบคุม</h1>
<p class="sub">ภาพรวมข้อมูลและสถานะของระบบ</p>

<div class="stat-grid">
  <div class="stat"><b><?= $newsCount ?></b><span>ข่าวสารทั้งหมด</span></div>
  <div class="stat"><b><?= $unitCount ?></b><span>หน่วยงาน</span></div>
  <div class="stat"><b><?= $popupCount ?></b><span>ป๊อปอัพกำลังแสดง</span></div>
  <div class="stat"><b><?= $applied ?>/<?= $total ?></b><span>Migrations ติดตั้งแล้ว</span></div>
</div>

<div class="panel" style="margin-top:18px">
  <h2>สถานะฐานข้อมูล</h2>
  <table class="list">
    <tr><th>เวอร์ชันฐานข้อมูล</th><th>Migration ล่าสุด</th><th>รอติดตั้ง</th></tr>
    <tr>
      <td><?= e((string) $db->query('SELECT VERSION()')->fetchColumn()) ?></td>
      <td><?= $last ? e($last['version'] . ' — ' . $last['name']) . ' <span class="muted">(' . e(fmt_dt($last['applied_at'])) . ')</span>' : '—' ?></td>
      <td>
        <?php if ($pending > 0): ?>
          <span class="badge amber"><?= $pending ?> รายการ — ไปจัดการ</span>
          <a class="btn sm" style="background:#7a5b00" href="<?= e(url('admin/migrations.php')) ?>">Migrations</a>
        <?php else: ?>
          <span class="badge green">เป็นปัจจุบัน</span>
        <?php endif; ?>
      </td>
    </tr>
  </table>
</div>

<div class="panel">
  <h2>ลิงก์ด่วน</h2>
  <div class="btnrow" style="margin-top:0">
    <a class="btn" href="<?= e(url('admin/news.php')) ?>">＋ เขียนข่าว</a>
    <a class="btn" href="<?= e(url('admin/popups.php')) ?>">ตั้งป๊อปอัพ</a>
    <a class="btn" href="<?= e(url('admin/units.php')) ?>">จัดการหน่วยงาน</a>
    <a class="btn" href="<?= e(url('install.php')) ?>">ตรวจระบบ / install</a>
  </div>
</div>
<?php include __DIR__ . '/_layout_end.php'; ?>
