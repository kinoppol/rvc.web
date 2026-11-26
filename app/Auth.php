<?php
declare(strict_types=1);

/** การเข้าสู่ระบบผู้ดูแล — session + password_hash + จำกัดการเดารหัสผ่าน */
final class Auth
{
    public const ROLES = ['admin' => 'ผู้ดูแลระบบ', 'editor' => 'ผู้เขียนข่าว'];
    private const MAX_ATTEMPTS = 5;      // ครั้ง
    private const LOCK_MINUTES = 15;     // นาที

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('rvcweb_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_url() . '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '7200');
        session_start();
    }

    /** @return true|string true เมื่อสำเร็จ หรือข้อความข้อผิดพลาด */
    public static function attempt(string $username, string $password): true|string
    {
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $fails = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > NOW() - INTERVAL ? MINUTE',
            [$ip, self::LOCK_MINUTES]
        );
        if ($fails >= self::MAX_ATTEMPTS) {
            return 'เข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ ' . self::LOCK_MINUTES . ' นาทีแล้วลองใหม่';
        }
        $u = Database::one('SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1', [$username]);
        // ใช้ password_verify เสมอ (แม้ไม่พบผู้ใช้) เพื่อไม่ให้เดาชื่อผู้ใช้จากเวลาตอบสนอง
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        if (!password_verify($password, $hash) || !$u) {
            Database::run('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())', [$ip, mb_substr($username, 0, 60)]);
            return 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        Database::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $u['id'];
        self::$loaded = false;
        return true;
    }

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $uid = (int) ($_SESSION['uid'] ?? 0);
        if ($uid <= 0) {
            return self::$user = null;
        }
        try {
            self::$user = Database::one(
                'SELECT u.id, u.username, u.display_name, u.role, u.unit_id, un.name AS unit_name
                   FROM users u LEFT JOIN units un ON un.id = u.unit_id
                  WHERE u.id = ? AND u.is_active = 1', [$uid]);
        } catch (Throwable) {
            // ตาราง units อาจยังไม่มี (ก่อนรัน migration ครบ)
            self::$user = Database::one('SELECT id, username, display_name, role, NULL AS unit_id, NULL AS unit_name FROM users WHERE id = ? AND is_active = 1', [$uid]);
        }
        return self::$user;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$user = null;
    }

    /** บังคับให้เข้าสู่ระบบ (และตรวจบทบาทถ้าระบุ) */
    public static function require(?string $role = null): array
    {
        $u = self::user();
        if ($u === null) {
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
            redirect(url('admin/login.php'));
        }
        if ($role !== null && $u['role'] !== $role && $u['role'] !== 'admin') {
            abort(403, 'เมนูนี้สำหรับผู้ดูแลระบบเท่านั้น');
        }
        return $u;
    }
}
