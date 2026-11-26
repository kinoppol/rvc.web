<?php
declare(strict_types=1);

/**
 * ตัวจัดการ migrations — ปรับปรุงโครงสร้างฐานข้อมูลตามไฟล์ใน migrations/
 *
 * ไฟล์ migration แต่ละไฟล์ชื่อ  YYYYMMDD_HHMMSS_คำอธิบาย.php  และ return object ที่มี
 *   public string $description;  public function up(PDO $db): void;  public function down(PDO $db): void;
 *
 * หมายเหตุ: คำสั่ง DDL ของ MariaDB (CREATE/ALTER/DROP) commit อัตโนมัติ ย้อนกลับด้วย transaction ไม่ได้
 * ดังนั้นแต่ละ migration ควรเขียนให้รันซ้ำได้ปลอดภัย (IF NOT EXISTS / IF EXISTS)
 */
final class Migrator
{
    public const TABLE     = 'schema_migrations';
    public const LOG_TABLE = 'schema_migration_log';
    private const LOCK     = 'rvcweb_migrate';

    public function __construct(private PDO $db, private string $dir, private string $runBy = 'system')
    {
    }

    /** สร้างตารางบันทึกสถานะ (รันซ้ำได้) */
    public function ensureTables(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            description VARCHAR(255) NOT NULL DEFAULT \'\',
            batch INT UNSIGNED NOT NULL,
            checksum CHAR(40) NOT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            applied_by VARCHAR(60) NOT NULL DEFAULT \'\',
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->db->exec('CREATE TABLE IF NOT EXISTS `' . self::LOG_TABLE . '` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL,
            action ENUM(\'up\',\'down\') NOT NULL,
            ok TINYINT(1) NOT NULL,
            message TEXT NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            run_by VARCHAR(60) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL,
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /** รายชื่อไฟล์ migration เรียงตามชื่อ  [name => path] */
    public function files(): array
    {
        $out = [];
        foreach (glob($this->dir . '/*.php') ?: [] as $path) {
            $name = basename($path, '.php');
            if (preg_match('/^\d{8}_\d{6}_[a-z0-9_]+$/', $name)) {
                $out[$name] = $path;
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /** ตารางที่ระบบบันทึกว่ารันแล้ว  [name => row] */
    public function applied(): array
    {
        $this->ensureTables();
        $rows = $this->db->query('SELECT * FROM `' . self::TABLE . '` ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, null, 'migration');
    }

    /**
     * สถานะรวมของทุก migration
     * state: applied | pending | modified (ไฟล์ถูกแก้หลังรัน) | missing (มีในฐานข้อมูลแต่ไม่พบไฟล์)
     */
    public function status(): array
    {
        $files   = $this->files();
        $applied = $this->applied();
        $list    = [];
        foreach ($files as $name => $path) {
            $row = $applied[$name] ?? null;
            $sum = sha1_file($path) ?: '';
            $list[$name] = [
                'name'        => $name,
                'description' => $row['description'] ?? $this->describe($path),
                'state'       => $row === null ? 'pending' : ($row['checksum'] !== $sum ? 'modified' : 'applied'),
                'batch'       => $row ? (int) $row['batch'] : null,
                'applied_at'  => $row['applied_at'] ?? null,
                'applied_by'  => $row['applied_by'] ?? null,
                'duration_ms' => $row ? (int) $row['duration_ms'] : null,
            ];
        }
        foreach ($applied as $name => $row) {
            if (!isset($files[$name])) {
                $list[$name] = [
                    'name' => $name, 'description' => $row['description'], 'state' => 'missing',
                    'batch' => (int) $row['batch'], 'applied_at' => $row['applied_at'],
                    'applied_by' => $row['applied_by'], 'duration_ms' => (int) $row['duration_ms'],
                ];
            }
        }
        ksort($list, SORT_STRING);
        return array_values($list);
    }

    public function pending(): array
    {
        return array_values(array_filter($this->status(), fn($r) => $r['state'] === 'pending'));
    }

    /**
     * รัน migration ที่ยังไม่ได้รัน (ทั้งหมด หรือเฉพาะชื่อที่ระบุ) ใน batch ใหม่
     * @return array{ok: bool, ran: list<string>, error: ?string}
     */
    public function migrate(?string $only = null): array
    {
        return $this->locked(function () use ($only): array {
            $files   = $this->files();
            $applied = $this->applied();
            $todo    = array_diff_key($files, $applied);
            if ($only !== null) {
                $todo = isset($todo[$only]) ? [$only => $todo[$only]] : [];
            }
            $batch = (int) $this->db->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM `' . self::TABLE . '`')->fetchColumn();
            $ran   = [];
            foreach ($todo as $name => $path) {
                $t0 = microtime(true);
                try {
                    $m = $this->load($path);
                    $m->up($this->db);
                    $ms = (int) round((microtime(true) - $t0) * 1000);
                    $st = $this->db->prepare('INSERT INTO `' . self::TABLE . '` (migration, description, batch, checksum, duration_ms, applied_by, applied_at) VALUES (?,?,?,?,?,?,NOW())');
                    $st->execute([$name, mb_substr((string) ($m->description ?? ''), 0, 255), $batch, sha1_file($path), $ms, $this->runBy]);
                    $this->log($name, 'up', true, null, $ms);
                    $ran[] = $name;
                } catch (Throwable $ex) {
                    $this->log($name, 'up', false, $ex->getMessage(), (int) round((microtime(true) - $t0) * 1000));
                    return ['ok' => false, 'ran' => $ran, 'error' => $name . ': ' . $ex->getMessage()];
                }
            }
            return ['ok' => true, 'ran' => $ran, 'error' => null];
        });
    }

    /** ย้อนกลับ batch ล่าสุด (หรือ migration ที่ระบุ) โดยเรียก down() เรียงจากใหม่ไปเก่า */
    public function rollback(?string $only = null): array
    {
        return $this->locked(function () use ($only): array {
            $files   = $this->files();
            $applied = $this->applied();
            if ($only !== null) {
                $targets = isset($applied[$only]) ? [$only] : [];
            } else {
                $last    = $applied ? max(array_map(fn($r) => (int) $r['batch'], $applied)) : 0;
                $targets = array_keys(array_filter($applied, fn($r) => (int) $r['batch'] === $last));
                rsort($targets, SORT_STRING);
            }
            $done = [];
            foreach ($targets as $name) {
                $t0 = microtime(true);
                try {
                    if (!isset($files[$name])) {
                        throw new RuntimeException('ไม่พบไฟล์ migration จึงย้อนกลับไม่ได้');
                    }
                    $this->load($files[$name])->down($this->db);
                    $this->db->prepare('DELETE FROM `' . self::TABLE . '` WHERE migration = ?')->execute([$name]);
                    $this->log($name, 'down', true, null, (int) round((microtime(true) - $t0) * 1000));
                    $done[] = $name;
                } catch (Throwable $ex) {
                    $this->log($name, 'down', false, $ex->getMessage(), (int) round((microtime(true) - $t0) * 1000));
                    return ['ok' => false, 'ran' => $done, 'error' => $name . ': ' . $ex->getMessage()];
                }
            }
            return ['ok' => true, 'ran' => $done, 'error' => null];
        });
    }

    /** ยอมรับไฟล์ที่ถูกแก้ไข (อัปเดต checksum ให้ตรงกับไฟล์ปัจจุบัน) */
    public function acceptChecksum(string $name): bool
    {
        $files = $this->files();
        if (!isset($files[$name])) {
            return false;
        }
        $st = $this->db->prepare('UPDATE `' . self::TABLE . '` SET checksum = ? WHERE migration = ?');
        $st->execute([sha1_file($files[$name]), $name]);
        return $st->rowCount() > 0;
    }

    /** ลบบันทึกของ migration ที่ไฟล์หายไปแล้ว */
    public function forget(string $name): void
    {
        $this->db->prepare('DELETE FROM `' . self::TABLE . '` WHERE migration = ?')->execute([$name]);
        $this->log($name, 'down', true, 'ลบบันทึก (ไฟล์ไม่มีแล้ว) โดยไม่รัน down()', 0);
    }

    public function history(int $limit = 30): array
    {
        $this->ensureTables();
        return $this->db->query('SELECT * FROM `' . self::LOG_TABLE . '` ORDER BY id DESC LIMIT ' . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC);
    }

    /** สร้างไฟล์ migration ใหม่จากแม่แบบ — คืนชื่อไฟล์ */
    public function create(string $slug, string $description): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($slug)) ?? '', '_');
        if ($slug === '') {
            throw new InvalidArgumentException('ชื่อ migration ใช้ได้เฉพาะ a-z, 0-9 และ _');
        }
        if (!is_writable($this->dir)) {
            throw new RuntimeException('โฟลเดอร์ migrations/ ไม่มีสิทธิ์เขียน');
        }
        $name = date('Ymd_His') . '_' . $slug;
        $desc = var_export($description, true);
        $code = <<<PHP
        <?php
        declare(strict_types=1);

        /** {$description} */
        return new class {
            public string \$description = {$desc};

            public function up(PDO \$db): void
            {
                // ตัวอย่าง: \$db->exec("ALTER TABLE news ADD COLUMN IF NOT EXISTS subtitle VARCHAR(255) NULL AFTER title");
            }

            public function down(PDO \$db): void
            {
                // ตัวอย่าง: \$db->exec("ALTER TABLE news DROP COLUMN IF EXISTS subtitle");
            }
        };

        PHP;
        if (file_put_contents($this->dir . '/' . $name . '.php', $code, LOCK_EX) === false) {
            throw new RuntimeException('เขียนไฟล์ไม่สำเร็จ');
        }
        return $name;
    }

    private function load(string $path): object
    {
        $m = (static fn(string $p) => require $p)($path);
        if (!is_object($m) || !method_exists($m, 'up') || !method_exists($m, 'down')) {
            throw new RuntimeException('ไฟล์ต้อง return object ที่มีเมธอด up() และ down()');
        }
        return $m;
    }

    /** อ่านคำอธิบายจาก docblock บรรทัดแรก โดยไม่ต้องรันไฟล์ */
    private function describe(string $path): string
    {
        $src = (string) file_get_contents($path, false, null, 0, 4096);
        if (preg_match('/\$description\s*=\s*([\'"])(.*?)\1/u', $src, $m)) {
            return stripcslashes($m[2]);
        }
        return '';
    }

    private function log(string $name, string $action, bool $ok, ?string $msg, int $ms): void
    {
        try {
            $st = $this->db->prepare('INSERT INTO `' . self::LOG_TABLE . '` (migration, action, ok, message, duration_ms, run_by, created_at) VALUES (?,?,?,?,?,?,NOW())');
            $st->execute([$name, $action, $ok ? 1 : 0, $msg, $ms, $this->runBy]);
        } catch (Throwable) {
            // ไม่ให้การบันทึกประวัติทำให้งานหลักล้ม
        }
    }

    /** กันการรันพร้อมกันหลายคน (GET_LOCK ของ MariaDB) */
    private function locked(callable $fn): array
    {
        $this->ensureTables();
        $got = (int) $this->db->query("SELECT GET_LOCK('" . self::LOCK . "', 10)")->fetchColumn();
        if ($got !== 1) {
            return ['ok' => false, 'ran' => [], 'error' => 'มีผู้อื่นกำลังรัน migration อยู่ กรุณาลองใหม่ภายหลัง'];
        }
        try {
            return $fn();
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . self::LOCK . "')");
        }
    }
}
