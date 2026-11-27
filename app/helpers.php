<?php
declare(strict_types=1);

/* ฟังก์ชันช่วยทั่วไป — ไม่ขึ้นกับฐานข้อมูล ใช้ได้ทั้งตัวติดตั้งและแอป */

/** escape สำหรับแสดงผล HTML */
function e(string|int|float|null $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL ฐานของแอป (รองรับการวางในโฟลเดอร์ย่อย เช่น /rvc.web) */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = $GLOBALS['config']['app']['base_url'] ?? '';
    if ($cfg !== '') {
        return $base = rtrim($cfg, '/');
    }
    $file   = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root   = str_replace('\\', '/', BASE_PATH);
    $rel    = ltrim(substr($file, strlen($root)), '/');           // เช่น admin/news.php
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($rel !== '' && str_ends_with(strtolower($script), strtolower($rel))) {
        return $base = rtrim(substr($script, 0, -strlen($rel)), '/');
    }
    return $base = rtrim(dirname($script), '/');
}

function url(string $path = '', array $query = []): string
{
    $u = base_url() . '/' . ltrim($path, '/');
    $query = array_filter($query, fn($v) => $v !== null && $v !== '');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

/** URL ของไฟล์อัปโหลด (เก็บ path แบบ relative เช่น uploads/2026/11/abc.jpg) */
function upload_url(?string $path): ?string
{
    return $path ? url($path) : null;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

/** ข้อความแจ้งเตือนครั้งเดียว (ต้องเริ่ม session ก่อน) */
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function abort(int $code, string $msg = ''): never
{
    http_response_code($code);
    $title = match ($code) { 403 => 'ไม่มีสิทธิ์เข้าถึง', 404 => 'ไม่พบหน้าที่ต้องการ', default => 'เกิดข้อผิดพลาด' };
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        . e($title) . '</title><style>body{font-family:system-ui,Tahoma,sans-serif;background:#f4f7fb;color:#13213a;margin:0;padding:64px 16px}'
        . '.b{max-width:520px;margin:auto;background:#fff;border:1px solid #d5ddea;border-radius:4px;padding:28px}a{color:#1f5aa6;font-weight:600}</style></head>'
        . '<body><div class="b"><h1 style="margin-top:0">' . $code . ' · ' . e($title) . '</h1><p>' . e($msg ?: 'กรุณาตรวจสอบลิงก์อีกครั้ง') . '</p>'
        . '<p><a href="' . e(url()) . '">← กลับหน้าแรก</a></p></div></body></html>';
    exit;
}

/* ------------------------------ วันที่ภาษาไทย ------------------------------ */

const TH_MONTHS       = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
const TH_MONTHS_SHORT = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
const TH_DAYS         = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];

/**
 * @param string $fmt short = 26 พ.ย. 69 · medium = 26 พ.ย. 2569 · long = 26 พฤศจิกายน 2569
 *                    full = วันพฤหัสบดีที่ 26 พฤศจิกายน 2569 · time = 26 พ.ย. 2569 เวลา 08.00 น.
 */
function thai_date(string|int|null $dt, string $fmt = 'medium'): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $ts = is_int($dt) ? $dt : strtotime($dt);
    if ($ts === false) {
        return '—';
    }
    $d = (int) date('j', $ts);
    $m = (int) date('n', $ts) - 1;
    $y = (int) date('Y', $ts) + 543;
    return match ($fmt) {
        'short' => $d . ' ' . TH_MONTHS_SHORT[$m] . ' ' . substr((string) $y, -2),
        'long'  => $d . ' ' . TH_MONTHS[$m] . ' ' . $y,
        'full'  => 'วัน' . TH_DAYS[(int) date('w', $ts)] . 'ที่ ' . $d . ' ' . TH_MONTHS[$m] . ' ' . $y,
        'time'  => $d . ' ' . TH_MONTHS_SHORT[$m] . ' ' . $y . ' เวลา ' . date('H.i', $ts) . ' น.',
        default => $d . ' ' . TH_MONTHS_SHORT[$m] . ' ' . $y,
    };
}

/** แปลงค่าจาก <input type="datetime-local"> เป็นรูปแบบฐานข้อมูล */
function dt_from_input(string $v): ?string
{
    if ($v === '') {
        return null;
    }
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

function dt_to_input(?string $v): string
{
    return $v ? date('Y-m-d\TH:i', (int) strtotime($v)) : '';
}

/* ------------------------------ ข้อความ ------------------------------ */

function str_limit(string $s, int $len): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len)) . '…' : $s;
}

/**
 * แปลงข้อความธรรมดาเป็น HTML ที่ปลอดภัย
 * ย่อหน้าคั่นด้วยบรรทัดว่าง · บรรทัดขึ้นต้น "## " = หัวข้อย่อย · "- " = รายการ · ลิงก์ http(s) กดได้
 * ลิงก์มีชื่อ: [ข้อความ](https://...) หรือ [ข้อความ](uploads/...) สำหรับไฟล์ที่เก็บในเว็บนี้
 * รูปภาพ: ![คำอธิบาย](uploads/....jpg) — บรรทัดรูปที่อยู่ติดกันแสดงเป็นแกลเลอรี
 */
function text_to_html(string $text): string
{
    $text   = str_replace(["\r\n", "\r"], "\n", trim($text));
    $blocks = preg_split('/\n{2,}/', $text) ?: [];
    $out    = '';
    $imgRe  = '~^!\[([^\]\n]{0,300})\]\((uploads/[A-Za-z0-9/_.\-]+\.(?:jpe?g|png|webp|gif))\)$~u';
    $link   = function (string $s): string {
        // 1) [ข้อความ](url) — เก็บไว้ชั่วคราวก่อน เพื่อไม่ให้ขั้นที่ 2 ทำลิงก์ซ้อน
        $named = [];
        $s = preg_replace_callback('~\[([^\]\n]{1,300})\]\((https?://[^\s)]+|uploads/[A-Za-z0-9/_.\-]+)\)~u', function ($m) use (&$named) {
            $href = str_starts_with($m[2], 'uploads/') ? e(url(html_entity_decode($m[2]))) : $m[2];
            $ext  = str_starts_with($m[2], 'uploads/') ? '' : ' rel="noopener" target="_blank"';
            $named[] = '<a href="' . $href . '"' . $ext . '>' . $m[1] . '</a>';
            return "\x01" . (count($named) - 1) . "\x02";
        }, $s) ?? $s;
        // 2) URL เปล่า
        $s = preg_replace('~(https?://[^\s<\x01]+[^\s<.,;:!?)\]"\'\x01])~u', '<a href="$1" rel="noopener" target="_blank">$1</a>', $s) ?? $s;
        return preg_replace_callback("~\x01(\d+)\x02~", fn($m) => $named[(int) $m[1]], $s) ?? $s;
    };
    foreach ($blocks as $b) {
        $lines = explode("\n", $b);
        if (count(array_filter($lines, fn($l) => !preg_match($imgRe, trim($l)))) === 0) {
            $out .= '<div class="news-gal">';
            foreach ($lines as $l) {
                preg_match($imgRe, trim($l), $m);
                $src = e(url($m[2]));
                $out .= '<a href="' . $src . '" target="_blank" rel="noopener"><img src="' . $src . '" alt="' . e($m[1]) . '" loading="lazy" decoding="async" data-sk></a>';
            }
            $out .= '</div>';
        } elseif (str_starts_with($b, '## ')) {
            $out .= '<h2>' . $link(e(trim(substr($lines[0], 3)))) . '</h2>';
            $rest = trim(implode("\n", array_slice($lines, 1)));
            if ($rest !== '') {
                $out .= text_to_html($rest);   // ส่วนที่เหลือใต้หัวข้อ (ย่อหน้า/รายการ)
            }
        } elseif (count(array_filter($lines, fn($l) => !str_starts_with(ltrim($l), '- '))) === 0) {
            $out .= '<ul>' . implode('', array_map(fn($l) => '<li>' . $link(e(substr(ltrim($l), 2))) . '</li>', $lines)) . '</ul>';
        } else {
            $out .= '<p>' . nl2br($link(e($b)), false) . '</p>';
        }
    }
    return $out;
}

/** slug ภาษาอังกฤษ/ตัวเลข สำหรับ URL */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

/* ------------------------------ ไฟล์ ------------------------------ */

/**
 * รับไฟล์รูปภาพที่อัปโหลด ตรวจชนิดจากเนื้อไฟล์จริง และตั้งชื่อใหม่แบบสุ่ม
 * @return string|null path แบบ relative (uploads/ปี/เดือน/ชื่อไฟล์) หรือ null ถ้าไม่ได้เลือกไฟล์
 * @throws RuntimeException เมื่อไฟล์ไม่ถูกต้อง
 */
function handle_image_upload(string $field, int $maxBytes = 5_242_880): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(match ($f['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์กำหนด',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่ครบ กรุณาลองใหม่',
            default => 'อัปโหลดไฟล์ไม่สำเร็จ (รหัส ' . (int) $f['error'] . ')',
        });
    }
    if ($f['size'] > $maxBytes) {
        throw new RuntimeException('ไฟล์ใหญ่เกิน ' . round($maxBytes / 1048576) . ' MB');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if ($ext === null || @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, WebP หรือ GIF');
    }
    $sub = 'uploads/' . date('Y/m');
    $dir = BASE_PATH . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new RuntimeException('สร้างโฟลเดอร์อัปโหลดไม่ได้ — ตรวจสิทธิ์เขียนของโฟลเดอร์ uploads/');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ — ตรวจสิทธิ์เขียนของโฟลเดอร์ uploads/');
    }
    return $sub . '/' . $name;
}

/** ลบไฟล์อัปโหลดเดิม (เฉพาะไฟล์ใน uploads/ เท่านั้น) */
function delete_upload(?string $path): void
{
    if ($path && preg_match('~^uploads/\d{4}/\d{2}/[a-f0-9]{24}\.(jpg|png|webp|gif)$~', $path)) {
        @unlink(BASE_PATH . '/' . $path);
    }
}

/** เขียนไฟล์แบบ atomic (เขียนไฟล์ชั่วคราวแล้วเปลี่ยนชื่อ) */
function write_file_atomic(string $file, string $content): bool
{
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($file, true);
    }
    return true;
}

/** บันทึกข้อผิดพลาดลง storage/logs */
function log_error(string $msg): void
{
    @file_put_contents(BASE_PATH . '/storage/logs/app-' . date('Y-m') . '.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/** แบ่งหน้า */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage, 'total' => $total];
}
