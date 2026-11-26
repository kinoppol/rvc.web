<?php
declare(strict_types=1);

/** การเข้าสู่ระบบของผู้ดูแล (session) */
final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $dir = BASE_PATH . '/runtime/sessions';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            session_save_path($dir);
            session_name('rvc_session');
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $st = Database::pdo()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $st->execute([$username]);
        $u = $st->fetch();
        if ($u && password_verify($password, $u['password_hash'])) {
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $up = Database::pdo()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
                $up->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            return true;
        }
        return false;
    }

    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['uid'])) {
            return null;
        }
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $st = Database::pdo()->prepare('SELECT id, username, display_name, role FROM users WHERE id = ?');
        $st->execute([(int) $_SESSION['uid']]);
        $row = $st->fetch();
        return $cache = $row ?: null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** บังคับต้องเข้าสู่ระบบ */
    public static function require(): void
    {
        if (self::user() === null) {
            flash('error', 'กรุณาเข้าสู่ระบบผู้ดูแลก่อนดำเนินการ');
            redirect(url('admin/index.php'));
        }
    }
}
