<?php
declare(strict_types=1);

/* Helper ทั่วไปของแอป */

/** ย่อ HTML escape */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * เส้นทางรากของแอปเทียบกับ document root
 * เช่น '' เมื่อติดตั้งที่ราก หรือ '/college' เมื่ออยู่ในโฟลเดอร์ย่อย
 */
function root_path(): string
{
    static $p = null;
    if ($p === null) {
        $doc  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
        $base = str_replace('\\', '/', BASE_PATH);
        $p    = ($doc !== '' && str_starts_with($base, $doc)) ? substr($base, strlen($doc)) : '';
        $p    = rtrim($p, '/');
    }
    return $p;
}

function url(string $path = ''): string
{
    return root_path() . '/' . ltrim($path, '/');
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** แปลงข้อความล้วน (จาก textarea/Word) เป็น <p> ให้อัตโนมัติ */
function text_to_html(string $text): string
{
    $out = [];
    foreach (preg_split('/\R+/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $out[] = '<p>' . e($line) . '</p>';
    }
    return implode("\n", $out);
}

/** หมวดหมู่ข่าวมาตรฐาน */
function news_categories(): array
{
    return ['ประชาสัมพันธ์', 'กิจกรรม', 'จัดซื้อจัดจ้าง', 'รับสมัครงาน'];
}

/** แสดงวันเวลาภาษาไทยแบบย่อ */
function fmt_dt(string $dt): string
{
    $ts = strtotime($dt);
    return $ts === false ? $dt : date('j M Y H:i', $ts);
}

/**
 * ยืนยันว่าระบบติดตั้งเสร็จแล้ว (มี config + ตาราง) มิฉะนั้นพาไป install.php
 */
function require_installed(): void
{
    if (!is_file(BASE_PATH . '/config/config.php')) {
        redirect(url('install.php'));
    }
    try {
        $n = (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'users'"
        )->fetchColumn();
        if ($n === 0) {
            redirect(url('install.php'));
        }
    } catch (Throwable) {
        redirect(url('install.php'));
    }
}
