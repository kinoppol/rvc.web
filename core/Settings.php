<?php
declare(strict_types=1);

/** การตั้งค่าของเว็บไซต์ (key/value) */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $rows = Database::pdo()->query('SELECT `key`, value FROM settings')->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['key']] = $r['value'];
            }
            self::$cache = $out;
            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $a = self::all();
        return $a[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $db = Database::pdo();
        $st = $db->prepare(
            'INSERT INTO settings (`key`, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $st->execute([$key, $value]);
        self::$cache = null;
    }
}
