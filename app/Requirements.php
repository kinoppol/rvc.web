<?php
declare(strict_types=1);

/** ตรวจความพร้อมของเซิร์ฟเวอร์: เวอร์ชัน PHP, ส่วนขยาย (แพ็กเกจ), สิทธิ์อ่าน/เขียนไฟล์ */
final class Requirements
{
    public const PHP_MIN     = '8.2.0';
    public const MARIADB_MIN = '10.0.0';

    /** ส่วนขยายที่ต้องมี => เหตุผล */
    public const REQUIRED_EXT = [
        'pdo'       => 'เชื่อมต่อฐานข้อมูล',
        'pdo_mysql' => 'ไดรเวอร์ MariaDB/MySQL',
        'mbstring'  => 'จัดการข้อความภาษาไทย (UTF-8)',
        'json'      => 'อ่าน/เขียนข้อมูล JSON',
        'fileinfo'  => 'ตรวจชนิดไฟล์ที่อัปโหลด',
        'session'   => 'การเข้าสู่ระบบ',
        'openssl'   => 'สุ่มค่าความปลอดภัย / HTTPS',
    ];

    /** ส่วนขยายที่แนะนำ (ไม่บังคับ) */
    public const RECOMMENDED_EXT = [
        'gd'       => 'ย่อขนาดรูปภาพ',
        'intl'     => 'จัดรูปแบบภาษา/วันที่',
        'zip'      => 'สำรอง/นำเข้าไฟล์',
        'curl'     => 'เชื่อมต่อบริการภายนอก',
        'Zend OPcache' => 'เพิ่มความเร็ว PHP',
    ];

    /** โฟลเดอร์ที่ต้องเขียนได้ => หน้าที่ */
    public const WRITABLE = [
        'config'           => 'บันทึกไฟล์ตั้งค่า config.php',
        'storage'          => 'ข้อมูลระบบ (ล็อกการติดตั้ง)',
        'storage/sessions' => 'เก็บ session ผู้ใช้',
        'storage/logs'     => 'บันทึกข้อผิดพลาด',
        'uploads'          => 'รูปภาพที่อัปโหลด',
        'migrations'       => 'สร้างไฟล์ migration ใหม่จากหน้า admin (ไม่บังคับ)',
    ];

    /** โฟลเดอร์ที่ไม่บังคับให้เขียนได้ */
    private const OPTIONAL_WRITABLE = ['migrations'];

    /** โฟลเดอร์ที่ต้องอ่านได้ */
    public const READABLE = ['app', 'migrations', 'admin', 'assets'];

    /**
     * @return list<array{group:string,name:string,ok:bool,required:bool,detail:string}>
     */
    public static function check(bool $createDirs = true): array
    {
        $r = [];
        $r[] = [
            'group' => 'PHP', 'name' => 'PHP เวอร์ชัน ' . self::PHP_MIN . ' ขึ้นไป',
            'ok' => version_compare(PHP_VERSION, self::PHP_MIN, '>='), 'required' => true,
            'detail' => 'ปัจจุบัน ' . PHP_VERSION . ' (' . PHP_OS_FAMILY . ', ' . PHP_SAPI . ')',
        ];
        foreach (self::REQUIRED_EXT as $ext => $why) {
            $ok = extension_loaded($ext);
            $r[] = ['group' => 'แพ็กเกจที่ต้องมี', 'name' => $ext, 'ok' => $ok, 'required' => true,
                'detail' => $why . ($ok ? ' · ' . (phpversion($ext) ?: 'พร้อมใช้') : ' · ไม่พบ — เปิดใช้ใน php.ini')];
        }
        foreach (self::RECOMMENDED_EXT as $ext => $why) {
            $ok = extension_loaded($ext);
            $r[] = ['group' => 'แพ็กเกจแนะนำ', 'name' => $ext, 'ok' => $ok, 'required' => false,
                'detail' => $why . ($ok ? '' : ' · ไม่พบ (ใช้งานได้ แต่บางความสามารถจะถูกปิด)')];
        }
        $uploadMax = self::iniBytes((string) ini_get('upload_max_filesize'));
        $r[] = ['group' => 'ค่า php.ini', 'name' => 'file_uploads', 'ok' => (bool) ini_get('file_uploads'), 'required' => true,
            'detail' => ini_get('file_uploads') ? 'เปิดอยู่' : 'ปิดอยู่ — ต้องเปิดเพื่ออัปโหลดรูป'];
        $r[] = ['group' => 'ค่า php.ini', 'name' => 'upload_max_filesize ≥ 2M', 'ok' => $uploadMax >= 2 * 1048576, 'required' => false,
            'detail' => 'ปัจจุบัน ' . ini_get('upload_max_filesize') . ' · post_max_size ' . ini_get('post_max_size')];

        foreach (self::WRITABLE as $dir => $why) {
            $path = BASE_PATH . '/' . $dir;
            if ($createDirs && !is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $ok = is_dir($path) && self::reallyWritable($path);
            $required = !in_array($dir, self::OPTIONAL_WRITABLE, true);
            $r[] = ['group' => 'สิทธิ์เขียนไฟล์', 'name' => $dir . '/', 'ok' => $ok, 'required' => $required,
                'detail' => $why . ' · ' . (is_dir($path) ? self::perms($path) : 'ไม่พบโฟลเดอร์') . ($ok ? '' : ' — ให้สิทธิ์เขียนแก่ผู้ใช้ของเว็บเซิร์ฟเวอร์')];
        }
        foreach (self::READABLE as $dir) {
            $path = BASE_PATH . '/' . $dir;
            $ok   = is_dir($path) && is_readable($path);
            $r[] = ['group' => 'สิทธิ์อ่านไฟล์', 'name' => $dir . '/', 'ok' => $ok, 'required' => true,
                'detail' => $ok ? 'อ่านได้ · ' . self::perms($path) : 'อ่านไม่ได้หรือไม่พบโฟลเดอร์'];
        }
        $cfg = BASE_PATH . '/config/config.php';
        if (is_file($cfg)) {
            $ok = is_readable($cfg);
            $r[] = ['group' => 'สิทธิ์อ่านไฟล์', 'name' => 'config/config.php', 'ok' => $ok, 'required' => true,
                'detail' => ($ok ? 'อ่านได้' : 'อ่านไม่ได้') . ' · ' . self::perms($cfg) . (is_writable($cfg) ? ' · เขียนทับได้' : ' · อ่านอย่างเดียว')];
        }
        return $r;
    }

    public static function passes(array $results): bool
    {
        foreach ($results as $r) {
            if ($r['required'] && !$r['ok']) {
                return false;
            }
        }
        return true;
    }

    /** ตรวจเวอร์ชันฐานข้อมูล: คืน [ok, ข้อความ] */
    public static function checkServer(PDO $pdo): array
    {
        $ver = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($ver, 'mariadb') !== false;
        preg_match('/^(\d+\.\d+\.\d+)/', $ver, $m);
        $num = $m[1] ?? '0.0.0';
        if ($isMaria) {
            $ok = version_compare($num, self::MARIADB_MIN, '>=');
            return [$ok, 'MariaDB ' . $num . ($ok ? '' : ' — ต้องการ ' . self::MARIADB_MIN . ' ขึ้นไป')];
        }
        $ok = version_compare($num, '8.0.0', '>=');
        return [$ok, 'MySQL ' . $num . ($ok ? ' (แนะนำ MariaDB 10 ขึ้นไป)' : ' — ต้องการ MariaDB 10+ หรือ MySQL 8+')];
    }

    /** is_writable บน Windows ไม่แม่นยำ — ลองเขียนไฟล์จริง */
    private static function reallyWritable(string $dir): bool
    {
        $probe = $dir . '/.probe_' . bin2hex(random_bytes(4));
        if (@file_put_contents($probe, 'x') === false) {
            return false;
        }
        @unlink($probe);
        return true;
    }

    private static function perms(string $path): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'Windows' : substr(sprintf('%o', (int) @fileperms($path)), -4);
    }

    private static function iniBytes(string $v): int
    {
        $n = (int) $v;
        return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
    }
}
