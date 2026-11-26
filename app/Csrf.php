<?php
declare(strict_types=1);

/** ป้องกัน CSRF สำหรับทุกฟอร์ม POST */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** เรียกที่ต้นหน้าที่รับ POST — ถ้า token ไม่ถูกต้องจะหยุดทำงาน */
    public static function verify(): void
    {
        if (!is_post()) {
            return;
        }
        $t = $_POST['_csrf'] ?? '';
        if (!is_string($t) || $t === '' || !hash_equals(self::token(), $t)) {
            // ฟอร์มที่ใหญ่เกิน post_max_size จะทำให้ $_POST ว่าง
            $tooBig = empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
            abort(400, $tooBig
                ? 'ข้อมูลที่ส่งใหญ่เกินที่เซิร์ฟเวอร์กำหนด (post_max_size) — ลองลดขนาดไฟล์'
                : 'แบบฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณาย้อนกลับแล้วส่งใหม่อีกครั้ง');
        }
    }
}
