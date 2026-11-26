<?php
declare(strict_types=1);

/**
 * ระบบ migration ของฐานข้อมูล
 * ไฟล์ migration อยู่ในโฟลเดอร์ /migrations — ชื่อไฟล์ เช่น 0001_core.php
 * แต่ละไฟล์ต้องคืนค่า:
 *   [
 *     'name' => 'คำอธิบาย',
 *     'up'   => function (PDO $db, int &$sql) { ... },
 *     'down' => function (PDO $db, int &$sql) { ... },
 *   ]
 *
 * หมายเหตุ: คำสั่ง DDL (CREATE/ALTER/DROP TABLE) ใน MySQL/MariaDB ยืนยัน
 *  (commit) ให้อัตโนมัติและย้อนกลับไม่ได้ จึงไม่ห่อ migration ด้วย
 * transaction — ระบบจำเวอร์ชันที่ติดตั้งสำเร็จในตาราง `migrations`
 * จึงรันซ้ำได้ปลอดภัย (รันเฉพาะรายการที่ยังไม่ทำเท่านั้น)
 */
final class Migrate
{
    public static function ensureTable(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS migrations (
                version VARCHAR(64) NOT NULL PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** รายการ migration ทั้งหมดในโฟลเดอร์ (เรียงตามเลขเวอร์ชัน) */
    public static function files(): array
    {
        $out = [];
        foreach (glob(BASE_PATH . '/migrations/*.php') ?: [] as $f) {
            $ver = substr(basename($f), 0, -4);
            $def = require $f;
            if (!is_array($def)) {
                continue;
            }
            $def += ['name' => $ver];
            $out[$ver] = $def;
        }
        ksort($out);
        return $out;
    }

    /** รายการพร้อมสถานะติดตั้ง (applied = วันที่ติดตั้ง หรือ null) */
    public static function status(PDO $db): array
    {
        self::ensureTable($db);
        $applied = [];
        foreach ($db->query('SELECT version, applied_at FROM migrations')->fetchAll() as $r) {
            $applied[$r['version']] = $r['applied_at'];
        }
        $list = [];
        foreach (self::files() as $ver => $def) {
            $list[] = [
                'version' => $ver,
                'name'    => (string) $def['name'],
                'applied' => $applied[$ver] ?? null,
            ];
        }
        return $list;
    }

    public static function pendingCount(PDO $db): int
    {
        return count(array_filter(self::status($db), fn (array $r) => $r['applied'] === null));
    }

    /**
     * รัน migration หนึ่งรายการ
     * คืนค่า ['ok'=>bool, 'sql'=>int, 'error'=>?string]
     */
    public static function run(PDO $db, string $version): array
    {
        $defs = self::files();
        if (!isset($defs[$version])) {
            return ['ok' => false, 'sql' => 0, 'error' => "ไม่พบ migration '{$version}'"];
        }
        self::ensureTable($db);
        $st = $db->prepare('SELECT version FROM migrations WHERE version = ?');
        $st->execute([$version]);
        if ($st->fetch()) {
            return ['ok' => false, 'sql' => 0, 'error' => "migration '{$version}' ติดตั้งไปแล้ว"];
        }

        $n = 0;
        try {
            $defs[$version]['up']($db, $n);
        } catch (Throwable $ex) {
            return ['ok' => false, 'sql' => $n, 'error' => $ex->getMessage()];
        }
        try {
            $ins = $db->prepare('INSERT INTO migrations (version, name, applied_at) VALUES (?, ?, NOW())');
            $ins->execute([$version, (string) $defs[$version]['name']]);
            return ['ok' => true, 'sql' => $n, 'error' => null];
        } catch (Throwable $ex) {
            return ['ok' => false, 'sql' => $n, 'error' => 'SQL ผ่าน แต่บันทึกสถานะไม่ได้: ' . $ex->getMessage()];
        }
    }

    /** ถอย migration หนึ่งรายการ (เรียก down) */
    public static function rollback(PDO $db, string $version): array
    {
        $defs = self::files();
        if (!isset($defs[$version])) {
            return ['ok' => false, 'sql' => 0, 'error' => "ไม่พบ migration '{$version}'"];
        }
        self::ensureTable($db);
        $st = $db->prepare('SELECT version FROM migrations WHERE version = ?');
        $st->execute([$version]);
        if (!$st->fetch()) {
            return ['ok' => false, 'sql' => 0, 'error' => "migration '{$version}' ยังไม่ได้ติดตั้ง"];
        }

        $n = 0;
        try {
            $defs[$version]['down']($db, $n);
        } catch (Throwable $ex) {
            return ['ok' => false, 'sql' => $n, 'error' => $ex->getMessage()];
        }
        try {
            $del = $db->prepare('DELETE FROM migrations WHERE version = ?');
            $del->execute([$version]);
            return ['ok' => true, 'sql' => $n, 'error' => null];
        } catch (Throwable $ex) {
            return ['ok' => false, 'sql' => $n, 'error' => 'SQL ผ่าน แต่บันทึกสถานะไม่ได้: ' . $ex->getMessage()];
        }
    }
}
