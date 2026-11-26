<?php
declare(strict_types=1);

/**
 * ตัวติดตั้ง — รองรับการใช้งานซ้ำ (reinstall-safe)
 *
 * ขั้นตอน:
 *   1) ตรวจระบบ        : เวอร์ชัน PHP ≥ 8.2, extension ที่จำเป็น, สิทธิ์อ่าน/เขียนโฟลเดอร์
 *   2) ตั้งค่าฐานข้อมูล  : ทดสอบเชื่อมต่อ MariaDB ≥ 10, สร้างฐานข้อมูลอัตโนมัติ, บันทึก config.php
 *   3) Migrations       : สร้าง/ปรับปรุงโครงสร้างตาราง (รันเฉพาะรายการที่ยังไม่ได้ทำ)
 *   4) ผู้ดูแลระบบ     : ตั้งค่าชื่อผู้ใช้-รหัสผ่าน admin (สร้างใหม่ หรืออัปเดตบัญชีเดิม)
 */

define('BASE_PATH', __DIR__);

error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Migrate.php';

$configFile = BASE_PATH . '/config/config.php';
$config     = is_file($configFile) ? require $configFile : null;
$dbCfg      = ($config['db'] ?? null) ?: [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'rvc_web',
    'user' => 'root',
    'pass' => '',
];
$installed  = Database::installed($dbCfg);

$phpReq     = '8.2.0';
$reqExt     = ['pdo_mysql', 'mbstring', 'json', 'fileinfo'];
$recExt     = ['openssl'];
$needDirs   = [
    'config'          => 'config.php (การตั้งค่า)',
    'data/uploads'    => 'ไฟล์อัปโหลด',
    'runtime/sessions'=> 'session',
];

$checks = null;    // ผลตรวจระบบ
$dbMsg  = null;    // ['ok'=>bool,'text'=>string]
$migMsg = null;    // ผลรัน migration
$adminMsg = null;  // ['ok'=>bool,'text'=>string]
$errors = [];

/* ----------------------------- การกระทำ (POST) ----------------------------- */
$action = $_POST['action'] ?? '';

if ($action === 'check') {
    $checks = [];
    $checks[] = ['ok' => version_compare(PHP_VERSION, $phpReq, '>='), 'level' => 'req',
        'name' => 'เวอร์ชัน PHP ' . $phpReq . ' ขึ้นไป', 'detail' => 'พบ ' . PHP_VERSION];
    foreach ($reqExt as $ext) {
        $checks[] = ['ok' => extension_loaded($ext), 'level' => 'req',
            'name' => "ส่วนขยาย {$ext}", 'detail' => extension_loaded($ext) ? 'ติดตั้งแล้ว' : 'ไม่พบ — ต้องติดตั้งก่อน'];
    }
    foreach ($recExt as $ext) {
        $checks[] = ['ok' => extension_loaded($ext), 'level' => 'rec',
            'name' => "ส่วนขยาย {$ext} (แนะนำ)", 'detail' => extension_loaded($ext) ? 'ติดตั้งแล้ว' : 'ไม่พบ (ไม่บังคับ)'];
    }
    foreach ($needDirs as $rel => $desc) {
        $path = BASE_PATH . '/' . $rel;
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $ok = is_dir($path) && is_writable($path);
        $checks[] = ['ok' => $ok, 'level' => 'req',
            'name' => "สิทธิ์เขียนโฟลเดอร์ {$rel}", 'detail' => ($ok ? 'อ่าน/เขียนได้' : 'ไม่มีสิทธิ์เขียน — แก้สิทธิ์ในเซิร์ฟเวอร์') . ' (' . $desc . ')'];
    }
    $checks[] = ['ok' => is_writable(BASE_PATH . '/config'), 'level' => 'req',
        'name' => 'สิทธิ์เขียนไฟล์ config/config.php', 'detail' => is_writable(BASE_PATH . '/config') ? 'บันทึกการตั้งค่าได้' : 'ไม่มีสิทธิ์เขียน'];
}

if ($action === 'db') {
    $dbCfg = [
        'host' => trim((string) ($_POST['db_host'] ?? $dbCfg['host'])),
        'port' => (int) ($_POST['db_port'] ?? $dbCfg['port']),
        'name' => trim((string) ($_POST['db_name'] ?? $dbCfg['name'])),
        'user' => trim((string) ($_POST['db_user'] ?? $dbCfg['user'])),
        'pass' => (string) ($_POST['db_pass'] ?? $dbCfg['pass']),
    ];
    if ($dbCfg['host'] === '' || $dbCfg['name'] === '' || !preg_match('/^[A-Za-z0-9_$]+$/', $dbCfg['name'])
        || $dbCfg['port'] < 1 || $dbCfg['port'] > 65535) {
        $dbMsg = ['ok' => false, 'text' => 'กรุณากรอกข้อมูลการเชื่อมต่อให้ครบถ้วน (ชื่อฐานข้อมูลใช้ตัวอักษร/ตัวเลขเท่านั้น)'];
    } else {
        $res = Database::test($dbCfg, ($_POST['create_db'] ?? '') === '1');
        $dbMsg = ['ok' => (bool) $res['ok'], 'text' => $res['message']];
        if ($res['ok']) {
            $newCfg = [
                'db'          => $dbCfg,
                'site'        => $config['site'] ?? ['name' => 'เว็บไซต์'],
                'installed_at'=> $config['installed_at'] ?? date('Y-m-d H:i:s'),
            ];
            $code = "<?php\n//.ไฟล์ที่สร้างโดย install.php — " . date('Y-m-d H:i:s') . "\n//.แก้ไขได้ตรงๆ หรือรัน install.php ซ้ำเพื่อตั้งค่าใหม่\nreturn " . var_export($newCfg, true) . ";\n";
            $tmp  = $configFile . '.tmp';
            if (@file_put_contents($tmp, $code) !== false && @rename($tmp, $configFile)) {
                $dbMsg['text'] .= ' — บันทึก config.php แล้ว';
            } else {
                $dbMsg = ['ok' => false, 'text' => 'เชื่อมต่อสำเร็จแต่บันทึก config.php ไม่ได้ — ตรวจสิทธิ์เขียนของโฟลเดอร์ config/'];
            }
        }
    }
}

if ($action === 'migrate') {
    $pdo = null;
    try {
        $safe = preg_replace('/[^A-Za-z0-9_$]/', '', $dbCfg['name'] ?? '');
        $pdo  = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) $dbCfg['host'], (int) $dbCfg['port'], $safe),
            (string) $dbCfg['user'], (string) $dbCfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ran   = 0;
        $lines = [];
        foreach (Migrate::status($pdo) as $r) {
            if ($r['applied'] !== null) {
                continue;
            }
            $res = Migrate::run($pdo, $r['version']);
            if ($res['ok']) {
                $ran++;
                $lines[] = "✔ {$r['version']} — {$r['name']}";
            } else {
                $lines[] = "✘ {$r['version']} — {$r['name']}: {$res['error']}";
                $migMsg  = ['ok' => false, 'text' => implode("\n", $lines)];
                break;
            }
        }
        if ($migMsg === null) {
            $text = $ran > 0
                ? 'ติดตั้ง/ปรับปรุงโครงสร้างฐานข้อมูลสำเร็จ ' . $ran . ' รายการ:' . "\n" . implode("\n", $lines)
                : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว — ไม่มีการเปลี่ยนแปลง';
            $migMsg = ['ok' => true, 'text' => $text];
        }
    } catch (Throwable $ex) {
        $migMsg = ['ok' => false, 'text' => 'เชื่อมต่อฐานข้อมูลเพื่อรัน migration ไม่สำเร็จ: ' . $ex->getMessage()];
    }
    $installed = $pdo ? Database::installed($dbCfg) : $installed;
}

if ($action === 'admin') {
    $username  = trim((string) ($_POST['admin_user'] ?? ''));
    $password  = (string) ($_POST['admin_pass'] ?? '');
    $password2 = (string) ($_POST['admin_pass2'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username)) {
        $adminMsg = ['ok' => false, 'text' => 'ชื่อผู้ใช้ต้องเป็น 3–60 ตัวอักษร (อักษร/ตัวเลข/_ . -)'];
    } elseif (strlen($password) < 8) {
        $adminMsg = ['ok' => false, 'text' => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร'];
    } elseif ($password !== $password2) {
        $adminMsg = ['ok' => false, 'text' => 'รหัสผ่านกับยืนยันรหัสผ่านไม่ตรงกัน'];
    } else {
        try {
            $safe = preg_replace('/[^A-Za-z0-9_$]/', '', $dbCfg['name'] ?? '');
            $pdo  = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    (string) $dbCfg['host'], (int) $dbCfg['port'], $safe),
                (string) $dbCfg['user'], (string) $dbCfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st   = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $st->execute([$username]);
            $existing = $st->fetch();
            if ($existing) {
                $up = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $up->execute([$hash, $existing['id']]);
                $adminMsg = ['ok' => true, 'text' => "อัปเดตรหัสผ่านของผู้ดูแล '{$username}' แล้ว"];
            } else {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, display_name, role, created_at, updated_at) VALUES (?,?,?,\'admin\',NOW(),NOW())');
                $ins->execute([$username, $hash, $username]);
                $adminMsg = ['ok' => true, 'text' => "สร้างบัญชีผู้ดูแล '{$username}' สำเร็จ"];
                seed_demo_data($pdo);
            }
        } catch (Throwable $ex) {
            $adminMsg = ['ok' => false, 'text' => 'บันทึกผู้ดูแลระบบไม่สำเร็จ: ' . $ex->getMessage()];
        }
    }
    $installed = $pdo ?? null ? Database::installed($dbCfg) : $installed;
}

/** ใส่ข้อมูลตัวอย่างตอนติดตั้งครั้งแรก (ทำเพียงครั้งเดียว) */
function seed_demo_data(PDO $db): void
{
    $st = $db->query("SELECT value FROM settings WHERE `key` = 'seeded'");
    if ($st->fetch()) {
        return;
    }
    $set = function (string $k, string $v) use ($db): void {
        $s = $db->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
        $s->execute([$k, $v]);
    };
    $set('site_name', 'วิทยาลัยอาชีวศึกษาร้อยเอ็ด');
    $set('site_footer', '© วิทยาลัยอาชีวศึกษาร้อยเอ็ด — ข้อมูลในเว็บไซต์นี้เพื่อใช้ประกอบการพัฒนา');
    $set('principal_title', 'สารจากผู้อำนวยการ');
    $set('principal_message', "ขอเรียนเชิญผู้บริหาร ครู คณาจารย์ และนักศึกษา ทุกท่าน ร่วมเป็นส่วนหนึ่งของชุมชนแห่งการเรียนรู้ของวิทยาลัย
เรายึดมั่นในปรัชญาการพัฒนาผู้เรียนให้เก่งวิชา เก่งทักษะ และเก่งชีวิต เพื่อเตรียมพร้อมสู่โลกการทำงานอย่างยั่งยืน");
    $set('seeded', '1');

    $units = [
        ['แผนกวิชาการโรงแรม', 'มุ่งพัฒนาผู้เรียนด้านวิชาชีพโรงแรม งานบริการอาหารและเครื่องดื่ม และการจัดการเชิงพาณิชย์', 'คุณสมร', '0-4523-8000', 'hotel@rvc.ac.th', 1],
        ['แผนกคอมพิวเตอร์ธุรกิจ', 'ผลิตบุคลากรด้านธุรกิจดิจิทัล ระบบสารสนเทศ และการออกแบบสื่อ', 'คุณอนันต์', '0-4523-8001', 'ict@rvc.ac.th', 2],
        ['แผนกบัญชี', 'พัฒนาศักยภาพผู้เรียนด้านวิชาชีพบัญชี การเงิน และระบบ ERP', 'คุณวรา', '0-4523-8002', 'acc@rvc.ac.th', 3],
    ];
    $ui = $db->prepare('INSERT INTO units (name, about, contact_name, contact_phone, contact_email, sort_order, created_at, updated_at) VALUES (?,?,?,?,?, ?,NOW(),NOW())');
    foreach ($units as $u) {
        $ui->execute([ $u[0], $u[1], $u[2], $u[3], $u[4], $u[5] ]);
    }
    $unitIds = $db->query('SELECT id FROM units ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

    $news = [
        ['นักศึกษาแผนกวิชาการโรงแรมผ่านการฝึกงานโรงแรมระดับสี่ดาว', 'กิจกรรม', $unitIds[0] ?? null, 1],
        ['เปิดรับสมัครนักเรียนประจำปีการศึกษา 2569 ทุกสาขาวิชา', 'ประชาสัมพันธ์', null, 0],
        ['ประกวดทักษะการบัญชีระดับอาชีวศึกษา — คว้ารางวัลรองชนะเลิศ', 'จัดซื้อจัดจ้าง', $unitIds[2] ?? null, 0],
    ];
    $ni = $db->prepare('INSERT INTO news (title, summary, body, category, unit_id, is_lead, published_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())');
    $days = [2, 5, 9];
    foreach ($news as $i => $n) {
        $when = date('Y-m-d H:i:s', strtotime("-{$days[$i]} days"));
        $unitId = $n[2] ?? null;
        $body = $n[0] . "\n\n" . 'รายละเอียดของข่าวนี้อยู่ในฐานข้อมูล — สามารถแก้ไขได้จากเมนูข่าวสารของระบบผู้ดูแล';
        $ni->execute([$n[0], $n[0], $body, $n[1], $unitId, $n[3], $when]);
    }

    $pi = $db->prepare('INSERT INTO popups (title, body, link_url, starts_at, ends_at, placement, enabled, created_at, updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())');
    $pi->execute([
        'วันพ่อแห่งชาติ 5 ธันวาคม',
        "ขอเชิญร่วมกิจกรรมเฉลิมพระเกียรติ วันพ่อแห่งชาติ\nพิธีลงนามสักการะพระบรมรูป และรับคำอวยพร",
        'news.php',
        date('Y-m-d H:i:s', time() - 3600),
        date('Y-m-d H:i:s', time() + 7 * 86400),
        'all',
        1,
    ]);
}

$allChecksOk = $checks !== null && !array_filter($checks, fn (array $c) => !$c['ok'] && $c['level'] === 'req');

/* ----------------------------- แสดงหน้าติดตั้ง ----------------------------- */
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>ตัวติดตั้ง — เว็บแอปพลิเคชัน</title>
<style>
  :root { --blue:#1f5aa6; --blue2:#0b2a55; --ok:#1c7c3f; --err:#b3261e; --line:#8796ad; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:'Leelawadee UI', Tahoma, sans-serif; background:#eef1f5; color:#1c2430; }
  .wrap { max-width: 860px; margin: 0 auto; padding: 28px 16px 60px; }
  header.top { background: linear-gradient(120deg, #0b2a55, #1f5aa6); color:#fff; padding: 26px 28px; border-radius: 8px; margin-bottom: 22px; }
  header.top h1 { margin:0; font-size: 24px; font-weight: 600; }
  header.top p { margin:6px 0 0; opacity:.85; font-size:14px; }
  .step { background:#fff; border:1px solid #d7dee8; border-radius:8px; margin-bottom:16px; overflow:hidden; }
  .step > h2 { display:flex; gap:12px; align-items:center; margin:0; padding:14px 18px; font-size:17px; font-weight:600; border-bottom:1px solid #e5eaf2; background:#f7f9fc; }
  .step > h2 .num { flex:0 0 30px; width:30px; height:30px; border-radius:50%; background:var(--blue); color:#fff; display:grid; place-items:center; font-size:15px; }
  .step > h2 .done { margin-left:auto; color:var(--ok); font-size:14px; font-weight:400; }
  .step .body { padding: 16px 18px 18px; }
  table.chk { border-collapse: collapse; width: 100%; font-size: 14px; }
  table.chk th, table.chk td { border:1px solid #e0e6ee; padding: 7px 10px; text-align:left; }
  table.chk th { background:#f2f5f9; font-weight:600; }
  .ok { color: var(--ok); font-weight: 600; }
  .bad { color: var(--err); font-weight: 600; }
  .rec { color:#8a6d1a; font-weight: 600; }
  input, select, textarea { font: inherit; font-size: 15px; }
  input[type=text], input[type=password], input[type=number] { width:100%; min-height:44px; padding:8px 12px; border:1px solid var(--line); border-radius:3px; }
  label { display:block; font-size:13.5px; font-weight:600; margin: 12px 0 4px; color:#38445a; }
  .row { display:flex; gap:14px; flex-wrap:wrap; }
  .row > div { flex:1 1 160px; }
  button { font:inherit; font-size:15px; font-weight:600; color:#fff; background:var(--blue); border:0; border-radius:4px; padding:10px 22px; cursor:pointer; margin-top:14px; }
  button:hover { background:#174a8d; }
  button.warn { background:#7a5b00; }
  button.warn:hover { background:#5f4700; }
  .msg { margin-top: 14px; padding: 10px 14px; border-radius: 4px; font-size: 14.5px; white-space: pre-line; }
  .msg.ok { background:#e7f4ec; border:1px solid #b7dfc4; color:#14532d; }
  .msg.err { background:#fdecea; border:1px solid #f3c1bc; color:#7c1d18; }
  .note { font-size: 13px; color:#5b6b84; margin: 8px 0 0; }
  .banner { background:#e7f4ec; border:1px solid #b7dfc4; color:#14532d; padding: 16px 18px; border-radius: 8px; margin-bottom: 18px; font-size: 15px; }
  .banner a { color: inherit; font-weight: 700; }
  .migrate-table { width:100%; border-collapse:collapse; font-size:14px; margin-top:10px; }
  .migrate-table th, .migrate-table td { border:1px solid #e0e6ee; padding:7px 10px; text-align:left; }
  .migrate-table th { background:#f2f5f9; }
  .badge { font-size:12px; padding:2px 8px; border-radius:10px; }
  .badge.applied { background:#e7f4ec; color:#14532d; }
  .badge.pending { background:#fdf3d8; color:#7a5b00; }
  a { color: var(--blue); }
</style>
</head>
<body>
<div class="wrap">

  <header class="top">
    <h1>ตัวติดตั้งเว็บแอปพลิเคชัน</h1>
    <p>PHP <?= e(PHP_VERSION) ?> · รองรับการใช้งานซ้ำ — รันหน้านี้ได้ทุกครั้งที่ต้องการตรวจสอบหรือปรับปรุงระบบ</p>
  </header>

  <?php if ($installed && $adminMsg === null && isset($config) && $migMsg === null && $dbMsg === null): ?>
  <div class="banner">
    ✅ ระบบติดตั้งแล้วและเชื่อมต่อฐานข้อมูลได้ (ติดตั้งล่าสุด: <?= e($config['installed_at'] ?? '-') ?>)<br>
    สามารถกดแต่ละขั้นตอนเพื่อตรวจสอบ/ดำเนินการซ้ำได้ตามต้องการ — หน้าเว็บ: <a href="<?= e(url('index.php')) ?>">เปิดหน้าหลัก</a> ·
    ผู้ดูแล: <a href="<?= e(url('admin/index.php')) ?>">เข้าสู่ระบบ</a>
  </div>
  <?php endif; ?>

  <!-- 1) ตรวจระบบ -->
  <div class="step">
    <h2><span class="num">1</span> ตรวจระบบและสิทธิ์ไฟล์ <?php if ($allChecksOk): ?><span class="done">✔ ผ่านทั้งหมด</span><?php endif; ?></h2>
    <div class="body">
      <p class="note">ตรวจสอบเวอร์ชัน PHP ส่วนขยาย (extension) ที่จำเป็น และสิทธิ์อ่าน/เขียนโฟลเดอร์ที่ต้องใช้ — กดปุ่มเพื่อตรวจทันที</p>
      <?php if ($checks !== null): ?>
      <table class="chk">
        <tr><th>รายการ</th><th>สถานะ</th><th>รายละเอียด</th></tr>
        <?php foreach ($checks as $c): $cls = $c['ok'] ? 'ok' : ($c['level'] === 'req' ? 'bad' : 'rec'); ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td class="<?= $cls ?>"><?= $c['ok'] ? 'ผ่าน' : ($c['level'] === 'req' ? 'ไม่ผ่าน' : 'ไม่ผ่าน (ไม่บังคับ)') ?></td>
          <td><?= e($c['detail']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <p class="note">ยังไม่ได้ตรวจ — กดปุ่มด้านล่าง</p>
      <?php endif; ?>
      <form method="post">
        <input type="hidden" name="action" value="check">
        <button type="submit">ตรวจระบบทันที</button>
      </form>
    </div>
  </div>

  <!-- 2) ฐานข้อมูล -->
  <div class="step">
    <h2><span class="num">2</span> ตั้งค่าการเชื่อมต่อฐานข้อมูล <?php if ($dbMsg !== null && $dbMsg['ok']): ?><span class="done">✔ พร้อมใช้งาน</span><?php endif; ?></h2>
    <div class="body">
      <form method="post" class="dbform">
        <input type="hidden" name="action" value="db">
        <div class="row">
          <div><label>Host</label><input type="text" name="db_host" value="<?= e((string) $dbCfg['host']) ?>"></div>
          <div><label>Port</label><input type="number" name="db_port" value="<?= e((string) $dbCfg['port']) ?>"></div>
        </div>
        <div class="row">
          <div><label>ชื่อฐานข้อมูล</label><input type="text" name="db_name" value="<?= e((string) $dbCfg['name']) ?>"></div>
          <div><label>ผู้ใช้</label><input type="text" name="db_user" value="<?= e((string) $dbCfg['user']) ?>"></div>
        </div>
        <label>รหัสผ่าน (เว้นว่าง = ไม่ใช้รหัสผ่าน)</label>
        <input type="password" name="db_pass" value="" autocomplete="new-password">
        <label style="font-weight:400;margin-top:14px;display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="create_db" value="1" style="width:auto;min-height:auto;"
                 <?= $dbCfg['name'] ? 'checked' : '' ?>>
          สร้างฐานข้อมูลให้อัตโนมัติถ้ายังไม่มี (ต้องเป็นผู้ใช้ที่มีสิทธิ์ CREATE)
        </label>
        <p class="note">ระบบรองรับ MariaDB 10.x ขึ้นไป — จะตรวจสอบเวอร์ชันตอนทดสอบเชื่อมต่อ และบันทึกค่าใน config/config.php (ไฟล์นี้ไม่ถูกเก็บใน git)</p>
        <button type="submit">ทดสอบเชื่อมต่อและบันทึก</button>
      </form>
      <?php if ($dbMsg !== null): ?>
        <div class="msg <?= $dbMsg['ok'] ? 'ok' : 'err' ?>"><?= e($dbMsg['text']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 3) Migrations -->
  <div class="step">
    <h2><span class="num">3</span> โครงสร้างฐานข้อมูล (Migrations) <?php if ($migMsg !== null && $migMsg['ok']): ?><span class="done">✔ เป็นปัจจุบัน</span><?php endif; ?></h2>
    <div class="body">
      <p class="note">สร้าง/ปรับปรุงตารางข้อมูลตามไฟล์ในโฟลเดอร์ <code>/migrations</code> — รันเฉพาะรายการที่ยังไม่ได้ทำเท่านั้น ปลอดภัยเมื่อรันซ้ำ</p>
      <?php
      $migList = null;
      try {
          $safe = preg_replace('/[^A-Za-z0-9_$]/', '', (string) ($dbCfg['name'] ?? ''));
          $pdo2 = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                  (string) $dbCfg['host'], (int) $dbCfg['port'], $safe),
              (string) $dbCfg['user'], (string) $dbCfg['pass'],
              [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
          $migList = Migrate::status($pdo2);
      } catch (Throwable) {
          $migList = null;
      }
      if ($migList !== null): ?>
        <table class="migrate-table">
          <tr><th>เวอร์ชัน</th><th>คำอธิบาย</th><th>สถานะ</th></tr>
          <?php foreach ($migList as $r): ?>
          <tr>
            <td><?= e($r['version']) ?></td>
            <td><?= e($r['name']) ?></td>
            <td>
              <?php if ($r['applied'] !== null): ?>
                <span class="badge applied">ติดตั้งแล้ว · <?= e(fmt_dt($r['applied'])) ?></span>
              <?php else: ?>
                <span class="badge pending">รอติดตั้ง</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </table>
        <form method="post">
          <input type="hidden" name="action" value="migrate">
          <button type="submit">ติดตั้ง/ปรับปรุงโครงสร้างฐานข้อมูล</button>
        </form>
      <?php else: ?>
        <p class="note">ยังแสดงรายการไม่ได้ — กรุณาตั้งค่าการเชื่อมต่อในขั้นตอนที่ 2 ก่อน</p>
      <?php endif; ?>
      <?php if ($migMsg !== null): ?>
        <div class="msg <?= $migMsg['ok'] ? 'ok' : 'err' ?>"><?= e($migMsg['text']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 4) ผู้ดูแลระบบ -->
  <div class="step">
    <h2><span class="num">4</span> บัญชีผู้ดูแลระบบ (admin) <?php if ($adminMsg !== null && $adminMsg['ok']): ?><span class="done">✔ สำเร็จ</span><?php endif; ?></h2>
    <div class="body">
      <p class="note">ตั้งชื่อผู้ใช้และรหัสผ่านสำหรับเข้าสู่ระบบผู้ดูแล — หากชื่อผู้ใช้เดิมอยู่แล้วระบบจะอัปเดตรหัสผ่านให้ (ใช้ซ้ำเพื่อรีเซ็ตรหัสผ่านได้)</p>
      <form method="post">
        <input type="hidden" name="action" value="admin">
        <label>ชื่อผู้ใช้</label>
        <input type="text" name="admin_user" value="admin" pattern="[A-Za-z0-9_.\-]{3,60}">
        <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
        <input type="password" name="admin_pass" autocomplete="new-password">
        <label>ยืนยันรหัสผ่าน</label>
        <input type="password" name="admin_pass2" autocomplete="new-password">
        <button type="submit">บันทึกบัญชีผู้ดูแล</button>
      </form>
      <?php if ($adminMsg !== null): ?>
        <div class="msg <?= $adminMsg['ok'] ? 'ok' : 'err' ?>"><?= e($adminMsg['text']) ?></div>
        <?php if ($adminMsg['ok']): ?>
          <div class="banner" style="margin-top:14px">
            🎉 ติดตั้งเสร็จสมบูรณ์ — เปิดหน้าเว็บ: <a href="<?= e(url('index.php')) ?>">หน้าหลัก</a> ·
            เข้าสู่ระบบผู้ดูแล: <a href="<?= e(url('admin/index.php')) ?>">ระบบจัดการ</a><br>
            <small>แนะนำให้ลบ/จำกัดการเข้าถึง install.php ในสภาพแวดล้อมออนไลน์จริง หรือรันซ้ำเมื่อต้องการตรวจระบบ/รีเซ็ตรหัสผ่าน</small>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
