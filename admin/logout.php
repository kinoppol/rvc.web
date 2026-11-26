<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// ออกจากระบบด้วย POST + CSRF เท่านั้น (กันลิงก์หลอกให้ออกจากระบบ)
if (!is_post()) {
    redirect(url('admin/index.php'));
}
Csrf::verify();
Auth::logout();
Auth::startSession();
flash('ok', 'ออกจากระบบแล้ว');
redirect(url('admin/login.php'));
