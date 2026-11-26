<?php
declare(strict_types=1);

/** ตัวเชื่อมต่อ MariaDB ผ่าน PDO (singleton) พร้อมฟังก์ชัน query ที่ใช้บ่อย */
final class Database
{
    private static ?PDO $pdo = null;
    private static array $cfg = [];

    public static function configure(array $cfg): void
    {
        self::$cfg = $cfg;
        self::$pdo = null;
    }

    /** สร้างการเชื่อมต่อจากค่าที่กำหนด (ใช้ทั้งในแอปและตัวติดตั้ง) */
    public static function connect(array $c, bool $withDb = true): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'] ?? '127.0.0.1', (int) ($c['port'] ?? 3306));
        if ($withDb) {
            $dsn .= ';dbname=' . self::safeName((string) ($c['name'] ?? ''));
        }
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . date('P') . "'");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            if (!self::$cfg) {
                throw new RuntimeException('ยังไม่ได้ตั้งค่าฐานข้อมูล — กรุณารัน install.php');
            }
            self::$pdo = self::connect(self::$cfg);
        }
        return self::$pdo;
    }

    /** ชื่อฐานข้อมูล/ตารางที่อนุญาต: ตัวอักษร ตัวเลข _ $ เท่านั้น */
    public static function safeName(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_$]{1,64}$/', $name)) {
            throw new InvalidArgumentException('ชื่อฐานข้อมูลไม่ถูกต้อง');
        }
        return $name;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** INSERT จาก array (ชื่อคอลัมน์มาจากโค้ดเท่านั้น ห้ามรับจากผู้ใช้) */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', self::safeName($table),
            implode(',', array_map(fn($c) => '`' . self::safeName($c) . '`', $cols)),
            implode(',', array_fill(0, count($cols), '?')));
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, int $id): void
    {
        $set = implode(',', array_map(fn($c) => '`' . self::safeName($c) . '` = ?', array_keys($data)));
        self::run(sprintf('UPDATE `%s` SET %s WHERE id = ?', self::safeName($table), $set), [...array_values($data), $id]);
    }

    public static function delete(string $table, int $id): void
    {
        self::run(sprintf('DELETE FROM `%s` WHERE id = ?', self::safeName($table)), [$id]);
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $st->execute([$table]);
        return (int) $st->fetchColumn() > 0;
    }
}
