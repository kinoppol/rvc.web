<?php
declare(strict_types=1);

/** ป้องกัน CSRF สำหรับฟอร์ม POST ทั้งหมด */
final class Csrf
{
    public static function token(): string
    {
        Auth::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    /** hidden input พร้อมใช้ */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::token()) . '">';
    }

    /** ตรวจสอบ token ในฟอร์ม POST — เรียกทุกหน้า admin หลัง Auth::require() */
    public static function require(): void
    {
        Auth::start();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $t = $_POST['csrf'] ?? '';
        if (!is_string($t) || $t === '' || !hash_equals(self::token(), $t)) {
            http_response_code(400);
            exit('Invalid CSRF token — กรุณากดส่งฟอร์มใหม่อีกครั้ง');
        }
    }
}
