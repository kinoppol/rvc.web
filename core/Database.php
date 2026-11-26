<?php
declare(strict_types=1);

/**
 * การเชื่อมต่อฐานข้อมูลผ่าน PDO
 * ค่า connection ถูกตั้งจาก config.php (โดย bootstrap) หรือโดยตรงจาก install.php
 */
final class Database
{
    private static ?array $cfg = null;
    private static ?PDO $pdo = null;

    public static function setConfig(array $cfg): void
    {
        self::$cfg = $cfg;
        self::$pdo = null;
    }

    public static function config(): ?array
    {
        return self::$cfg;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = self::$cfg;
            if (!is_array($c)) {
                throw new RuntimeException('ยังไม่ได้ตั้งค่าฐานข้อมูล');
            }
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['host'], (int) ($c['port'] ?? 3306), $c['name']);
            self::$pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /**
     * ทดสอบการเชื่อมต่อ (ไม่ใช้ dbname ในครั้งแรก) และตรวจสอบเวอร์ชัน
     * คืนค่า ['ok'=>bool, 'message'=>string, 'version'=>?string]
     */
    public static function test(array $cfg, bool $createDb = false): array
    {
        $host = trim((string) ($cfg['host'] ?? '127.0.0.1'));
        $port = (int) ($cfg['port'] ?? 3306);
        $name = trim((string) ($cfg['name'] ?? ''));
        $user = trim((string) ($cfg['user'] ?? ''));
        $pass = (string) ($cfg['pass'] ?? '');

        try {
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $versionOk = self::versionOk($version);

            // ตรวจสอบว่าฐานข้อมูลมีหรือยัง
            $st = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
            $st->execute([$name]);
            $exists = (bool) $st->fetchColumn();

            if (!$exists) {
                if (!$createDb) {
                    return [
                        'ok'      => false,
                        'version' => $version,
                        'message' => "เชื่อมต่อสำเร็จ แต่ไม่พบฐานข้อมูล '{$name}' — ติ๊กช่อง \"สร้างฐานข้อมูลให้อัตโนมัติ\" หรือสร้างล่วงหน้าด้วยสิทธิ์ผู้ดูแล",
                    ];
                }
                $safe = preg_replace('/[^A-Za-z0-9_$]/', '', $name);
                if ($safe === '') {
                    return ['ok' => false, 'version' => $version, 'message' => 'ชื่อฐานข้อมูลไม่ถูกต้อง'];
                }
                $pdo->exec('CREATE DATABASE `' . $safe . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $exists = true;
            }

            if (!$versionOk) {
                return [
                    'ok'      => false,
                    'version' => $version,
                    'message' => "เวอร์ชัน {$version} ไม่ตรงตามข้อกำหนด — ระบบต้องการ MariaDB 10.x ขึ้นไป (พบเวอร์ชันปัจจุบันไม่ผ่านเกณฑ์)",
                ];
            }

            return [
                'ok'      => true,
                'version' => $version,
                'message' => "เชื่อมต่อสำเร็จ (เวอร์ชัน {$version}) — ฐานข้อมูล '{$name}' พร้อมใช้งาน",
            ];
        } catch (Throwable $ex) {
            return ['ok' => false, 'message' => 'เชื่อมต่อไม่สำเร็จ: ' . $ex->getMessage()];
        }
    }

    /** ต้องเป็น MariaDB 10.x ขึ้นไป (หรือเวอร์ชัน 10/11 ขึ้นไป) */
    public static function versionOk(string $version): bool
    {
        if (preg_match('/^(\d+)(\.(\d+))?/', $version, $m)) {
            $major = (int) $m[1];
            return $major >= 10;
        }
        return false;
    }

    /** ตรวจสอบว่าโครงสร้างตารางหลักติดตั้งแล้วหรือยัง */
    public static function installed(array $cfg): bool
    {
        try {
            $safe = preg_replace('/[^A-Za-z0-9_$]/', '', (string) ($cfg['name'] ?? ''));
            $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                (string) ($cfg['host'] ?? '127.0.0.1'), (int) ($cfg['port'] ?? 3306), $safe),
                (string) ($cfg['user'] ?? ''), (string) ($cfg['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $n = (int) $pdo->query(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'users'"
            )->fetchColumn();
            return $n > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
