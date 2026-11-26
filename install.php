<?php
declare(strict_types=1);

/*
 * ตัวติดตั้งเว็บไซต์ — รองรับการติดตั้งซ้ำ
 *
 *   1) ตรวจระบบ        PHP 8.2+, แพ็กเกจ (ส่วนขยาย PHP), สิทธิ์อ่าน/เขียนไฟล์
 *   2) ฐานข้อมูล       เชื่อมต่อ MariaDB 10+, สร้างฐานข้อมูล, บันทึก config/config.php
 *   3) โครงสร้างข้อมูล  รัน migrations ที่ยังไม่ได้รัน (อัปเกรดโดยเก็บข้อมูลเดิม) หรือล้างแล้วติดตั้งใหม่
 *   4) ผู้ดูแลระบบ     ตั้งชื่อผู้ใช้และรหัสผ่านของ admin (สร้างใหม่ หรือรีเซ็ตบัญชีเดิม)
 *   5) เสร็จสิ้น        ล็อกตัวติดตั้ง (storage/installed.lock)
 *
 * เมื่อติดตั้งเสร็จแล้ว การเปิดหน้านี้อีกครั้งต้องเข้าสู่ระบบเป็นผู้ดูแลก่อน
 * ถ้าเข้าระบบไม่ได้ (ลืมรหัสผ่าน / ฐานข้อมูลเปลี่ยน) ให้ลบไฟล์ storage/installed.lock บนเซิร์ฟเวอร์
 */

define('BASE_PATH', __DIR__);
const APP_VERSION = '1.0.0';

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    http_response_code(500);
    exit('ต้องใช้ PHP 8.2 ขึ้นไป (ปัจจุบัน ' . PHP_VERSION . ')');
}

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Database.php';
require BASE_PATH . '/app/Auth.php';
require BASE_PATH . '/app/Csrf.php';
require BASE_PATH . '/app/Migrator.php';
require BASE_PATH . '/app/Requirements.php';
require BASE_PATH . '/app/content.php';

$configFile = BASE_PATH . '/config/config.php';
$lockFile   = BASE_PATH . '/storage/installed.lock';
$config     = is_file($configFile) ? (require $configFile) : null;
$config     = is_array($config) ? $config : null;
if ($config) {
    $GLOBALS['config'] = $config;
    date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');
}

Auth::startSession();

/** เชื่อมต่อฐานข้อมูลตาม config ปัจจุบัน (null ถ้าไม่ได้) */
function inst_pdo(?array $config, ?string &$error = null): ?PDO
{
    if (empty($config['db'])) {
        return null;
    }
    try {
        return Database::connect($config['db']);
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        return null;
    }
}

$dbError = null;
$pdo     = inst_pdo($config, $dbError);
if ($pdo && $config) {
    Database::configure($config['db']);
}

/* ------------------------- ล็อกหลังติดตั้งเสร็จ ------------------------- */
$installedInfo = is_file($lockFile) ? (json_decode((string) file_get_contents($lockFile), true) ?: []) : null;
if ($installedInfo !== null) {
    $me = null;
    try {
        $me = $pdo ? Auth::user() : null;
    } catch (Throwable) {
        $me = null;
    }
    if (($me['role'] ?? '') !== 'admin') {
        http_response_code(403);
        $_SESSION['after_login'] = url('install.php');
        ?><!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>ตัวติดตั้งถูกล็อก</title><style>body{font-family:'IBM Plex Sans Thai',Tahoma,sans-serif;background:#f4f7fb;color:#13213a;margin:0;padding:56px 16px}
        .b{max-width:560px;margin:auto;background:#fff;border:1px solid #d5ddea;border-top:4px solid #0b2a55;border-radius:4px;padding:28px}
        a.btn{display:inline-flex;align-items:center;min-height:44px;padding:0 20px;border-radius:3px;background:#0b2a55;color:#fff;font-weight:600;text-decoration:none}
        code{background:#f4f7fb;border:1px solid #d5ddea;padding:1px 6px;border-radius:3px}.m{color:#4a5a74;font-size:14px;line-height:1.7}</style></head>
        <body><div class="b"><h1 style="margin:0 0 8px;font-size:22px">🔒 ระบบติดตั้งเรียบร้อยแล้ว</h1>
        <p>ติดตั้งเมื่อ <?= e(thai_date($installedInfo['installed_at'] ?? null, 'time')) ?> · เวอร์ชัน <?= e($installedInfo['version'] ?? '-') ?></p>
        <p>การใช้ตัวติดตั้งซ้ำ (ตรวจระบบ ตั้งค่าฐานข้อมูล อัปเกรดโครงสร้าง หรือรีเซ็ตรหัสผู้ดูแล) ต้องเข้าสู่ระบบในฐานะผู้ดูแลระบบก่อน</p>
        <p><a class="btn" href="<?= e(url('admin/login.php')) ?>">เข้าสู่ระบบผู้ดูแล</a></p>
        <p class="m">เข้าสู่ระบบไม่ได้ (ลืมรหัสผ่าน หรือย้ายฐานข้อมูล)? ลบไฟล์ <code>storage/installed.lock</code> บนเซิร์ฟเวอร์เพื่อปลดล็อกตัวติดตั้ง
        แล้วกลับมาที่หน้านี้ — ข้อมูลเดิมจะไม่ถูกลบ<?= $dbError ? '<br>สถานะฐานข้อมูล: เชื่อมต่อไม่ได้' : '' ?></p></div></body></html><?php
        exit;
    }
}

Csrf::verify();

/* ------------------------------ สถานะของแต่ละขั้น ------------------------------ */
$checks   = Requirements::check();
$reqOk    = Requirements::passes($checks);
$server   = null;   // [ok, ข้อความ]
$status   = [];     // สถานะ migrations
$hasAdmin = false;
$admins   = [];
$newsCount = 0;

if ($pdo) {
    try {
        $server = Requirements::checkServer($pdo);
        $mig    = new Migrator($pdo, BASE_PATH . '/migrations', 'installer');
        $status = $mig->status();
        if (Database::tableExists($pdo, 'users')) {
            $admins   = $pdo->query("SELECT id, username, display_name, last_login_at FROM users WHERE role = 'admin' ORDER BY id")->fetchAll();
            $hasAdmin = $admins !== [];
        }
        if (Database::tableExists($pdo, 'news')) {
            $newsCount = (int) $pdo->query('SELECT COUNT(*) FROM news')->fetchColumn();
        }
    } catch (Throwable $ex) {
        $dbError = $ex->getMessage();
    }
}
$pending   = array_values(array_filter($status, fn($r) => $r['state'] === 'pending'));
$appliedN  = count(array_filter($status, fn($r) => in_array($r['state'], ['applied', 'modified'], true)));
// ตารางชื่อซ้ำที่มีอยู่ก่อน แต่ไม่ได้สร้างผ่าน migrations ของระบบนี้ (เช่น ระบบเก่า)
$conflicts = [];
if ($pdo && $appliedN === 0) {
    foreach (['users', 'login_attempts', 'settings', 'units', 'news', 'pages', 'popups'] as $t) {
        if (Database::tableExists($pdo, $t)) {
            $conflicts[] = $t;
        }
    }
}
$dbOk      = $pdo !== null && ($server[0] ?? false);
$schemaOk  = $dbOk && $status !== [] && $pending === [];

// ขั้นสูงสุดที่ไปได้
$maxStep = 1;
if ($reqOk) {
    $maxStep = 2;
    if ($dbOk) {
        $maxStep = 3;
        if ($schemaOk) {
            $maxStep = $hasAdmin ? 5 : 4;
        }
    }
}
$step = input_int('step', 0);
if ($step < 1 || $step > $maxStep) {
    $step = $maxStep;
}

function go(int $step): never
{
    redirect(url('install.php', ['step' => $step]));
}

/* ------------------------------ การกระทำ (POST) ------------------------------ */
$action = is_post() ? input('action') : '';

/** บันทึก config.php */
function save_config(array $db, ?array $old): bool
{
    $cfg = [
        'app' => [
            'name'     => $old['app']['name'] ?? 'วิทยาลัยอาชีวศึกษาร้อยเอ็ด',
            'timezone' => $old['app']['timezone'] ?? 'Asia/Bangkok',
            'debug'    => $old['app']['debug'] ?? false,
            'base_url' => $old['app']['base_url'] ?? '',
        ],
        'db' => $db,
        'created_at' => $old['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $code = "<?php\n// สร้างโดย install.php — แก้ไขได้โดยตรง หรือรัน install.php ซ้ำเพื่อตั้งค่าใหม่\n// ห้ามนำไฟล์นี้ขึ้น git (มีรหัสผ่านฐานข้อมูล)\nreturn " . var_export($cfg, true) . ";\n";
    return write_file_atomic(BASE_PATH . '/config/config.php', $code);
}

if ($action === 'save_db' && $reqOk) {
    $db = [
        'host' => input('db_host', '127.0.0.1'),
        'port' => input_int('db_port', 3306),
        'name' => input('db_name'),
        'user' => input('db_user'),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
    ];
    if ($db['pass'] === '' && !empty($_POST['keep_pass']) && $config) {
        $db['pass'] = (string) ($config['db']['pass'] ?? '');
    }
    $_SESSION['_db_form'] = array_diff_key($db, ['pass' => 1]);
    try {
        if ($db['host'] === '' || $db['user'] === '' || $db['port'] < 1 || $db['port'] > 65535) {
            throw new InvalidArgumentException('กรุณากรอกโฮสต์ พอร์ต และชื่อผู้ใช้ให้ถูกต้อง');
        }
        Database::safeName($db['name']);
        $root = Database::connect($db, false);
        [$verOk, $verMsg] = Requirements::checkServer($root);
        if (!$verOk) {
            throw new RuntimeException('เวอร์ชันฐานข้อมูลไม่รองรับ: ' . $verMsg);
        }
        if (!empty($_POST['create_db'])) {
            $root->exec('CREATE DATABASE IF NOT EXISTS `' . $db['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        $test = Database::connect($db);
        $test->exec('CREATE TABLE IF NOT EXISTS `_install_probe` (id INT) ENGINE=InnoDB');
        $test->exec('DROP TABLE `_install_probe`');
        if (!save_config($db, $config)) {
            throw new RuntimeException('เชื่อมต่อได้ แต่บันทึก config/config.php ไม่สำเร็จ — ตรวจสิทธิ์เขียนของโฟลเดอร์ config/');
        }
        unset($_SESSION['_db_form']);
        flash('ok', 'เชื่อมต่อ ' . $verMsg . ' ฐานข้อมูล `' . $db['name'] . '` สำเร็จ และบันทึก config/config.php แล้ว');
        go(3);
    } catch (Throwable $ex) {
        $msg = $ex->getMessage();
        if ($ex instanceof PDOException) {
            $msg = match ((int) ($ex->errorInfo[1] ?? 0)) {
                1045    => 'ชื่อผู้ใช้หรือรหัสผ่านฐานข้อมูลไม่ถูกต้อง',
                1049    => 'ไม่พบฐานข้อมูล `' . $db['name'] . '` — เลือก "สร้างฐานข้อมูลให้อัตโนมัติ" หรือสร้างเองก่อน',
                1044, 1142 => 'ผู้ใช้ฐานข้อมูลไม่มีสิทธิ์เพียงพอ (ต้องการสิทธิ์ CREATE, ALTER, DROP, INDEX, REFERENCES)',
                2002    => 'เชื่อมต่อเซิร์ฟเวอร์ฐานข้อมูลไม่ได้ — ตรวจโฮสต์/พอร์ต และว่า MariaDB ทำงานอยู่',
                default => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $msg,
            };
        }
        flash('err', $msg);
        go(2);
    }
}

if ($action === 'migrate' && $dbOk) {
    $res = (new Migrator($pdo, BASE_PATH . '/migrations', 'installer'))->migrate();
    if ($res['ok']) {
        flash('ok', $res['ran'] ? 'ติดตั้ง/อัปเกรดโครงสร้างฐานข้อมูลสำเร็จ ' . count($res['ran']) . ' รายการ' : 'โครงสร้างฐานข้อมูลเป็นปัจจุบันอยู่แล้ว');
        go($hasAdmin ? 5 : 4);
    }
    flash('err', 'รัน migration ไม่สำเร็จ — ' . $res['error'] . ($res['ran'] ? ' (สำเร็จไปก่อนหน้า ' . count($res['ran']) . ' รายการ)' : ''));
    go(3);
}

if ($action === 'reinstall' && $dbOk) {
    if (input('confirm') !== ($config['db']['name'] ?? "\0")) {
        flash('err', 'พิมพ์ชื่อฐานข้อมูลไม่ตรง — ยกเลิกการล้างข้อมูล');
        go(3);
    }
    $mig = new Migrator($pdo, BASE_PATH . '/migrations', 'installer');
    $guard = 0;
    while ($mig->applied() && $guard++ < 100) {
        $res = $mig->rollback();
        if (!$res['ok']) {
            flash('err', 'ล้างข้อมูลไม่สำเร็จ — ' . $res['error']);
            go(3);
        }
    }
    $pdo->exec('DROP TABLE IF EXISTS `' . Migrator::LOG_TABLE . '`, `' . Migrator::TABLE . '`');
    @unlink($lockFile);
    $res = (new Migrator($pdo, BASE_PATH . '/migrations', 'installer'))->migrate();
    flash($res['ok'] ? 'ok' : 'err', $res['ok']
        ? 'ล้างข้อมูลเดิมและติดตั้งโครงสร้างใหม่ทั้งหมดแล้ว (' . count($res['ran']) . ' migrations) — ตั้งบัญชีผู้ดูแลใหม่ในขั้นถัดไป'
        : 'ติดตั้งโครงสร้างใหม่ไม่สำเร็จ — ' . $res['error']);
    go($res['ok'] ? 4 : 3);
}

if ($action === 'save_admin' && $schemaOk) {
    $username = input('username');
    $display  = input('display_name');
    $pass     = (string) ($_POST['password'] ?? '');
    $pass2    = (string) ($_POST['password_confirm'] ?? '');
    $_SESSION['_admin_form'] = ['username' => $username, 'display_name' => $display];
    $err = match (true) {
        !preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $username) => 'ชื่อผู้ใช้ต้องยาว 3–60 ตัว ใช้ได้เฉพาะ a-z, 0-9, _ . -',
        mb_strlen($pass) < 8                                => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร',
        !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass) => 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข',
        strcasecmp($pass, $username) === 0                  => 'รหัสผ่านต้องไม่เหมือนชื่อผู้ใช้',
        $pass !== $pass2                                    => 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน',
        default                                             => null,
    };
    if ($err !== null) {
        flash('err', $err);
        go(4);
    }
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $st   = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([$username]);
    $id = (int) $st->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE users SET password_hash = ?, role = 'admin', is_active = 1, display_name = IF(? = '', display_name, ?) WHERE id = ?")
            ->execute([$hash, $display, $display, $id]);
        flash('ok', "ตั้งรหัสผ่านใหม่ให้ผู้ดูแล '{$username}' แล้ว");
    } else {
        $pdo->prepare("INSERT INTO users (username, password_hash, display_name, role, is_active) VALUES (?, ?, ?, 'admin', 1)")
            ->execute([$username, $hash, $display ?: $username]);
        $id = (int) $pdo->lastInsertId();
        flash('ok', "สร้างบัญชีผู้ดูแล '{$username}' สำเร็จ");
    }
    if (!empty($_POST['sample_news'])) {
        $n = seed_sample_news($pdo, $id);
        if ($n) {
            flash('ok', "ใส่ข่าวตัวอย่าง {$n} รายการ (ลบได้จากระบบจัดการ)");
        }
    }
    $pdo->prepare('DELETE FROM login_attempts')->execute();
    unset($_SESSION['_admin_form']);
    // เข้าสู่ระบบให้อัตโนมัติ เพื่อใช้ตัวติดตั้ง/ระบบจัดการต่อได้ทันที
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    go(5);
}

if ($action === 'finish' && $schemaOk && $hasAdmin) {
    $info = [
        'installed_at' => $installedInfo['installed_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
        'version'      => APP_VERSION,
        'php'          => PHP_VERSION,
        'database'     => $server[1] ?? '',
    ];
    if (!write_file_atomic($lockFile, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        flash('err', 'เขียนไฟล์ storage/installed.lock ไม่สำเร็จ — ตรวจสิทธิ์เขียนของโฟลเดอร์ storage/');
        go(5);
    }
    flash('ok', 'ติดตั้งเสร็จสมบูรณ์ ยินดีต้อนรับสู่ระบบจัดการเว็บไซต์');
    redirect(url('admin/index.php'));
}

/* ------------------------------ ค่าที่ใช้แสดงผล ------------------------------ */
$flashes   = take_flashes();
$dbForm    = ($_SESSION['_db_form'] ?? null) ?: ($config['db'] ?? []) + ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'rvc_web', 'user' => 'root'];
$adminForm = $_SESSION['_admin_form'] ?? ['username' => 'admin', 'display_name' => 'ผู้ดูแลระบบ'];
$steps     = [1 => 'ตรวจระบบ', 2 => 'ฐานข้อมูล', 3 => 'โครงสร้างข้อมูล', 4 => 'ผู้ดูแลระบบ', 5 => 'เสร็จสิ้น'];
$stepDone  = [1 => $reqOk, 2 => $dbOk, 3 => $schemaOk, 4 => $hasAdmin, 5 => $installedInfo !== null];
$stateLbl  = ['applied' => ['ติดตั้งแล้ว', 'ok'], 'pending' => ['รอติดตั้ง', 'wait'], 'modified' => ['ไฟล์ถูกแก้ไข', 'warn'], 'missing' => ['ไม่พบไฟล์', 'bad']];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>ติดตั้งเว็บไซต์ · ขั้นที่ <?= $step ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anuphan:wght@500;600&family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap">
<style>
:root{--navy:#0b2a55;--navy2:#071d3d;--ink:#13213a;--mute:#4a5a74;--line:#d5ddea;--bg:#f4f7fb;--blue:#1f5aa6;--ok:#1d7a3e;--okbg:#e2f3e8;--bad:#b42318;--badbg:#fbe5e3;--warn:#9a5800;--warnbg:#fcefd9}
*{box-sizing:border-box}
body{margin:0;font:15px/1.6 'IBM Plex Sans Thai',Tahoma,sans-serif;color:var(--ink);background:var(--bg)}
h1,h2,.hd{font-family:Anuphan,'IBM Plex Sans Thai',sans-serif;font-weight:600}
a{color:var(--blue)}
.top{background:var(--navy);color:#c3d0e4}
.top .in{max-width:960px;margin:0 auto;padding:18px 16px;display:flex;align-items:center;gap:14px}
.seal{width:44px;height:44px;border-radius:50%;border:2px solid #f2c14e;display:grid;place-items:center;font-size:11px;font-weight:700;color:#fff;flex:none}
.top h1{margin:0;color:#fff;font-size:20px;line-height:1.3}
.wrap{max-width:960px;margin:0 auto;padding:24px 16px 64px}
.steps{display:flex;gap:4px;margin:0 0 20px;padding:0;list-style:none;overflow-x:auto}
.steps li{flex:1 1 0;min-width:120px}
.steps a,.steps span{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:3px solid var(--line);color:var(--mute);text-decoration:none;font-weight:600;font-size:14px;white-space:nowrap}
.steps .n{width:24px;height:24px;border-radius:50%;display:inline-grid;place-items:center;background:#e9eef6;font-size:13px;flex:none}
.steps .done .n{background:var(--ok);color:#fff}
.steps .cur a,.steps .cur span{border-color:var(--navy);color:var(--ink)}
.steps .cur .n{background:var(--navy);color:#fff}
.steps span{opacity:.55}
.card{background:#fff;border:1px solid var(--line);border-radius:4px;padding:22px 24px;margin-bottom:16px}
.card h2{margin:0 0 4px;font-size:20px}
.sub{margin:0 0 16px;color:var(--mute);font-size:14px}
.msg{padding:12px 16px;border-radius:3px;margin-bottom:12px;font-weight:500;white-space:pre-line}
.msg.ok{background:var(--okbg);color:var(--ok)}.msg.err{background:var(--badbg);color:var(--bad)}.msg.info{background:#e8f0fb;color:#173f75}.msg.warn{background:var(--warnbg);color:var(--warn)}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{text-align:left;padding:9px 10px;border-top:1px solid var(--line);vertical-align:top}
th{background:var(--bg);color:var(--mute);font-weight:600;font-size:13px}
.grp td{background:#fafbfd;font-weight:700;color:var(--navy);font-size:13px;letter-spacing:.02em}
.pill{display:inline-block;padding:1px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.pill.ok{background:var(--okbg);color:var(--ok)}.pill.bad{background:var(--badbg);color:var(--bad)}.pill.warn,.pill.wait{background:var(--warnbg);color:var(--warn)}.pill.mute{background:#e9eef6;color:var(--mute)}
.mute{color:var(--mute)}.small{font-size:13px}
label.f{display:block;font-weight:600;margin:14px 0 6px}
input[type=text],input[type=password],input[type=number]{width:100%;min-height:44px;padding:8px 12px;border:1px solid #8796ad;border-radius:3px;font:inherit;background:#fff}
input:focus{outline:3px solid var(--blue);outline-offset:1px}
.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 16px}
.chk{display:flex;gap:10px;align-items:flex-start;margin-top:14px;cursor:pointer}
.chk input{width:18px;height:18px;margin-top:3px;accent-color:var(--navy)}
.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:20px}
.btn{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 20px;border-radius:3px;border:0;background:var(--navy);color:#fff;font:600 15px 'IBM Plex Sans Thai',sans-serif;cursor:pointer;text-decoration:none}
.btn:hover{background:#174a8b;color:#fff}
.btn.ghost{background:#fff;color:var(--ink);border:1px solid #8796ad}
.btn.danger{background:var(--bad)}
.btn[disabled]{opacity:.45;cursor:not-allowed}
details.danger{border:1px solid #f1c6c1;border-radius:4px;padding:14px 18px;background:#fff8f7;margin-top:16px}
details.danger summary{cursor:pointer;font-weight:700;color:var(--bad)}
code{background:var(--bg);border:1px solid var(--line);border-radius:3px;padding:0 5px;font-size:13px}
.tw{overflow-x:auto}
@media (max-width:600px){.card{padding:18px 16px}.steps li{min-width:auto}.steps .t{display:none}}
</style>
</head>
<body>
<header class="top"><div class="in">
  <div class="seal hd">ตรา</div>
  <div><h1>ติดตั้งเว็บไซต์วิทยาลัย</h1>
  <div class="small">เวอร์ชัน <?= APP_VERSION ?> · PHP <?= e(PHP_VERSION) ?><?= $installedInfo !== null ? ' · ติดตั้งแล้ว — โหมดดูแลระบบ' : '' ?></div></div>
</div></header>

<div class="wrap">
  <ol class="steps" aria-label="ขั้นตอนการติดตั้ง">
    <?php foreach ($steps as $n => $label): $cls = ($n === $step ? 'cur ' : '') . ($stepDone[$n] ? 'done' : ''); ?>
      <li class="<?= $cls ?>">
        <?php if ($n <= $maxStep): ?>
          <a href="<?= e(url('install.php', ['step' => $n])) ?>" <?= $n === $step ? 'aria-current="step"' : '' ?>><span class="n"><?= $stepDone[$n] ? '✓' : $n ?></span><span class="t"><?= e($label) ?></span></a>
        <?php else: ?>
          <span><span class="n"><?= $n ?></span><span class="t"><?= e($label) ?></span></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php foreach ($flashes as $f): ?>
    <div class="msg <?= $f['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>

<?php if ($step === 1): ?>
  <section class="card">
    <h2>1 · ตรวจความพร้อมของเซิร์ฟเวอร์</h2>
    <p class="sub">ตรวจเวอร์ชัน PHP แพ็กเกจ (ส่วนขยาย PHP) ที่จำเป็น และสิทธิ์อ่าน/เขียนโฟลเดอร์ — รายการที่ "จำเป็น" ต้องผ่านทั้งหมดจึงติดตั้งต่อได้</p>
    <div class="tw"><table>
      <thead><tr><th>รายการ</th><th>รายละเอียด</th><th style="width:110px">ผล</th></tr></thead>
      <tbody>
      <?php $g = null; foreach ($checks as $c): if ($c['group'] !== $g): $g = $c['group']; ?>
        <tr class="grp"><td colspan="3"><?= e($g) ?></td></tr>
      <?php endif; ?>
        <tr>
          <td><b><?= e($c['name']) ?></b><?= $c['required'] ? '' : ' <span class="mute small">(ไม่บังคับ)</span>' ?></td>
          <td class="mute"><?= e($c['detail']) ?></td>
          <td><?php if ($c['ok']): ?><span class="pill ok">ผ่าน</span><?php elseif ($c['required']): ?><span class="pill bad">ไม่ผ่าน</span><?php else: ?><span class="pill warn">แนะนำ</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="actions">
      <a class="btn ghost" href="<?= e(url('install.php', ['step' => 1])) ?>">↻ ตรวจอีกครั้ง</a>
      <?php if ($reqOk): ?>
        <a class="btn" href="<?= e(url('install.php', ['step' => 2])) ?>">ถัดไป: ฐานข้อมูล →</a>
      <?php else: ?>
        <span class="msg err" style="margin:0">แก้ไขรายการที่ไม่ผ่านก่อน แล้วกด "ตรวจอีกครั้ง"</span>
      <?php endif; ?>
    </div>
  </section>

<?php elseif ($step === 2): ?>
  <section class="card">
    <h2>2 · เชื่อมต่อฐานข้อมูล MariaDB</h2>
    <p class="sub">ต้องการ MariaDB <?= e(Requirements::MARIADB_MIN) ?> ขึ้นไป · ระบบจะทดสอบการเชื่อมต่อและสิทธิ์ แล้วบันทึกลง <code>config/config.php</code></p>
    <?php if ($config && $dbOk): ?>
      <div class="msg ok">เชื่อมต่ออยู่แล้ว: <?= e($server[1] ?? '') ?> · ฐานข้อมูล <code><?= e($config['db']['name']) ?></code> — แก้ค่าด้านล่างถ้าต้องการย้ายฐานข้อมูล</div>
    <?php elseif ($config && $dbError): ?>
      <div class="msg err">config.php มีอยู่แล้ว แต่เชื่อมต่อไม่ได้: <?= e($dbError) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_db">
      <div class="row">
        <div><label class="f" for="h">โฮสต์</label><input type="text" id="h" name="db_host" value="<?= e($dbForm['host'] ?? '') ?>" required></div>
        <div><label class="f" for="p">พอร์ต</label><input type="number" id="p" name="db_port" value="<?= e($dbForm['port'] ?? 3306) ?>" min="1" max="65535" required></div>
      </div>
      <label class="f" for="n">ชื่อฐานข้อมูล</label>
      <input type="text" id="n" name="db_name" value="<?= e($dbForm['name'] ?? '') ?>" pattern="[A-Za-z0-9_$]{1,64}" required>
      <div class="row">
        <div><label class="f" for="u">ชื่อผู้ใช้ฐานข้อมูล</label><input type="text" id="u" name="db_user" value="<?= e($dbForm['user'] ?? '') ?>" required></div>
        <div><label class="f" for="pw">รหัสผ่านฐานข้อมูล</label><input type="password" id="pw" name="db_pass" autocomplete="new-password"></div>
      </div>
      <?php if ($config): ?>
        <label class="chk"><input type="checkbox" name="keep_pass" value="1" checked><span>ถ้าเว้นรหัสผ่านว่าง ให้ใช้รหัสผ่านเดิมใน config.php</span></label>
      <?php endif; ?>
      <label class="chk"><input type="checkbox" name="create_db" value="1" checked><span>สร้างฐานข้อมูลให้อัตโนมัติถ้ายังไม่มี <span class="mute small">(utf8mb4_unicode_ci — ผู้ใช้ต้องมีสิทธิ์ CREATE)</span></span></label>
      <div class="actions">
        <a class="btn ghost" href="<?= e(url('install.php', ['step' => 1])) ?>">← ย้อนกลับ</a>
        <button class="btn" type="submit">ทดสอบและบันทึก →</button>
      </div>
    </form>
  </section>

<?php elseif ($step === 3): ?>
  <section class="card">
    <h2>3 · โครงสร้างฐานข้อมูล (Migrations)</h2>
    <p class="sub">ฐานข้อมูล <code><?= e($config['db']['name'] ?? '') ?></code> · <?= e($server[1] ?? '') ?></p>
    <?php if ($appliedN > 0 && $pending): ?>
      <div class="msg info">พบการติดตั้งเดิม (<?= $appliedN ?> migrations) — กด "อัปเกรด" เพื่อรันเฉพาะรายการใหม่ ข้อมูลเดิมจะไม่ถูกลบ</div>
    <?php elseif ($appliedN > 0 && !$pending): ?>
      <div class="msg ok">โครงสร้างฐานข้อมูลเป็นปัจจุบันแล้ว</div>
    <?php elseif ($conflicts): ?>
      <div class="msg err">ฐานข้อมูลนี้มีตาราง <?= e(implode(', ', $conflicts)) ?> อยู่แล้ว แต่ไม่ได้สร้างโดยตัวติดตั้งนี้ (อาจเป็นระบบเก่า)
โครงสร้างอาจไม่ตรงกันและทำให้ระบบทำงานผิดพลาด — แนะนำให้ย้อนกลับไปเลือกฐานข้อมูลใหม่ หรือสำรองแล้วลบตารางเหล่านั้นก่อน</div>
    <?php else: ?>
      <div class="msg info">ฐานข้อมูลยังว่าง — กด "ติดตั้งโครงสร้าง" เพื่อสร้างตารางทั้งหมด</div>
    <?php endif; ?>
    <div class="tw"><table>
      <thead><tr><th>Migration</th><th>คำอธิบาย</th><th>สถานะ</th><th>รันเมื่อ</th></tr></thead>
      <tbody>
      <?php foreach ($status as $r): [$lbl, $cls] = $stateLbl[$r['state']]; ?>
        <tr><td><code><?= e($r['name']) ?></code></td><td><?= e($r['description']) ?></td>
          <td><span class="pill <?= $cls ?>"><?= e($lbl) ?></span></td>
          <td class="mute small"><?= $r['applied_at'] ? e(thai_date($r['applied_at'], 'time')) . ' · batch ' . (int) $r['batch'] : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <form method="post" class="actions">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="migrate">
      <a class="btn ghost" href="<?= e(url('install.php', ['step' => 2])) ?>">← ย้อนกลับ</a>
      <?php if ($pending): ?>
        <button class="btn" type="submit"><?= $appliedN > 0 ? 'อัปเกรด ' . count($pending) . ' รายการ' : 'ติดตั้งโครงสร้าง ' . count($pending) . ' รายการ' ?> →</button>
      <?php else: ?>
        <a class="btn" href="<?= e(url('install.php', ['step' => $hasAdmin ? 5 : 4])) ?>">ถัดไป →</a>
      <?php endif; ?>
    </form>

    <?php if ($appliedN > 0): ?>
    <details class="danger">
      <summary>ติดตั้งใหม่ทั้งหมด (ล้างข้อมูลเดิม)</summary>
      <p class="small">ระบบจะเรียก <code>down()</code> ของทุก migration เพื่อลบตารางของเว็บไซต์ (ข่าว หน่วยงาน ผู้ใช้ ป๊อปอัพ ฯลฯ) แล้วติดตั้งใหม่
        <b>ข้อมูลทั้งหมดจะหายและกู้คืนไม่ได้</b> · ไฟล์รูปใน <code>uploads/</code> ไม่ถูกลบ · ตารางอื่นในฐานข้อมูลเดียวกันที่ไม่ใช่ของระบบจะไม่ถูกแตะต้อง</p>
      <form method="post" onsubmit="return confirm('ยืนยันล้างข้อมูลทั้งหมดและติดตั้งใหม่?')">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="reinstall">
        <label class="f" for="cf">พิมพ์ชื่อฐานข้อมูล <code><?= e($config['db']['name'] ?? '') ?></code> เพื่อยืนยัน</label>
        <input type="text" id="cf" name="confirm" autocomplete="off" required>
        <div class="actions"><button class="btn danger" type="submit">ล้างข้อมูลและติดตั้งใหม่</button></div>
      </form>
    </details>
    <?php endif; ?>
  </section>

<?php elseif ($step === 4): ?>
  <section class="card">
    <h2>4 · ตั้งค่าบัญชีผู้ดูแลระบบ (admin)</h2>
    <p class="sub">บัญชีนี้ใช้เข้าระบบจัดการเว็บไซต์ · รหัสผ่านเก็บแบบเข้ารหัส (bcrypt) ไม่สามารถอ่านย้อนกลับได้</p>
    <?php if ($admins): ?>
      <div class="msg info">มีผู้ดูแลอยู่แล้ว: <?= e(implode(', ', array_column($admins, 'username'))) ?>
        — กรอกชื่อผู้ใช้เดิมเพื่อตั้งรหัสผ่านใหม่ หรือกรอกชื่อใหม่เพื่อเพิ่มผู้ดูแลอีกคน</div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_admin">
      <div class="row">
        <div><label class="f" for="un">ชื่อผู้ใช้</label>
          <input type="text" id="un" name="username" value="<?= e($adminForm['username']) ?>" pattern="[A-Za-z0-9_.\-]{3,60}" required>
          <div class="mute small">3–60 ตัว: a-z, 0-9, _ . -</div></div>
        <div><label class="f" for="dn">ชื่อที่แสดง</label>
          <input type="text" id="dn" name="display_name" value="<?= e($adminForm['display_name']) ?>" maxlength="120"></div>
      </div>
      <div class="row">
        <div><label class="f" for="pa">รหัสผ่าน</label>
          <input type="password" id="pa" name="password" minlength="8" autocomplete="new-password" required>
          <div class="mute small">อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข</div></div>
        <div><label class="f" for="pb">ยืนยันรหัสผ่าน</label>
          <input type="password" id="pb" name="password_confirm" minlength="8" autocomplete="new-password" required></div>
      </div>
      <?php if ($newsCount === 0): ?>
        <label class="chk"><input type="checkbox" name="sample_news" value="1" checked><span>ใส่ข่าวตัวอย่างเพื่อดูหน้าตาเว็บไซต์ <span class="mute small">(มีป้าย [ตัวอย่าง] ลบได้ภายหลัง)</span></span></label>
      <?php endif; ?>
      <div class="actions">
        <a class="btn ghost" href="<?= e(url('install.php', ['step' => 3])) ?>">← ย้อนกลับ</a>
        <button class="btn" type="submit">บันทึกผู้ดูแลระบบ →</button>
        <?php if ($hasAdmin): ?><a class="btn ghost" href="<?= e(url('install.php', ['step' => 5])) ?>">ข้าม (ใช้บัญชีเดิม)</a><?php endif; ?>
      </div>
    </form>
  </section>

<?php else: ?>
  <section class="card">
    <h2>5 · พร้อมใช้งาน</h2>
    <p class="sub">ตรวจสรุปแล้วกด "เสร็จสิ้นการติดตั้ง" ระบบจะล็อกตัวติดตั้งไว้ ครั้งต่อไปต้องเข้าสู่ระบบผู้ดูแลก่อนจึงใช้ได้</p>
    <table>
      <tr><th style="width:200px">ระบบ</th><td>PHP <?= e(PHP_VERSION) ?> · <?= e($server[1] ?? '') ?></td></tr>
      <tr><th>ฐานข้อมูล</th><td><code><?= e($config['db']['name'] ?? '') ?></code> @ <?= e(($config['db']['host'] ?? '') . ':' . ($config['db']['port'] ?? '')) ?></td></tr>
      <tr><th>Migrations</th><td><?= $appliedN ?> รายการ — เป็นปัจจุบัน</td></tr>
      <tr><th>ผู้ดูแลระบบ</th><td><?= e(implode(', ', array_column($admins, 'username'))) ?></td></tr>
      <tr><th>สถานะ</th><td><?= $installedInfo !== null ? 'ติดตั้งแล้วเมื่อ ' . e(thai_date($installedInfo['installed_at'] ?? null, 'time')) : 'ยังไม่ล็อกตัวติดตั้ง' ?></td></tr>
    </table>
    <form method="post" class="actions">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="finish">
      <button class="btn" type="submit"><?= $installedInfo !== null ? 'บันทึกและกลับระบบจัดการ' : 'เสร็จสิ้นการติดตั้ง' ?> →</button>
      <a class="btn ghost" href="<?= e(url()) ?>" target="_blank" rel="noopener">ดูหน้าเว็บไซต์</a>
    </form>
    <p class="mute small" style="margin-top:16px">ความปลอดภัย: บนเซิร์ฟเวอร์จริงควรใช้ HTTPS และจำกัดสิทธิ์ผู้ใช้ฐานข้อมูลเฉพาะฐานข้อมูลนี้ · การอัปเกรดโครงสร้างภายหลังทำได้จากเมนู <b>Migrations</b> ในระบบจัดการ</p>
  </section>
<?php endif; ?>
</div>
</body>
</html>
