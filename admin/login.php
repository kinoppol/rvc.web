<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Frame-Options: DENY');
if (Auth::user()) {
    redirect(url('admin/index.php'));
}
Csrf::verify();

$error = null;
$username = '';
if (is_post()) {
    $username = input('username');
    $res = Auth::attempt($username, (string) ($_POST['password'] ?? ''));
    if ($res === true) {
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        // ไปได้เฉพาะ path ภายในเว็บนี้
        $base = base_url() . '/';
        redirect(is_string($next) && str_starts_with($next, $base) && !str_starts_with($next, '//') ? $next : url('admin/index.php'));
    }
    $error = $res;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>เข้าสู่ระบบ · ระบบจัดการเว็บไซต์</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anuphan:wght@500;600&family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>?v=6">
<link rel="icon" type="image/png" href="<?= e(url('assets/seal.png')) ?>">
</head>
<body class="login">
<main class="login-box">
  <div class="brand"><img class="seal" src="<?= e(url('assets/seal.png')) ?>" alt="ตราสำนักงานคณะกรรมการการอาชีวศึกษา"><div class="hd"><?= e(Settings::get('site_name')) ?><br><span>ระบบจัดการเว็บไซต์</span></div></div>
  <form method="post" class="card">
    <h1>เข้าสู่ระบบ</h1>
    <?php if ($error): ?><div class="msg err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php foreach (take_flashes() as $f): ?><div class="msg <?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?= Csrf::field() ?>
    <label class="f" for="u">ชื่อผู้ใช้</label>
    <input id="u" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
    <label class="f" for="p">รหัสผ่าน</label>
    <input id="p" type="password" name="password" autocomplete="current-password" required>
    <button class="btn block" type="submit">เข้าสู่ระบบ</button>
    <p class="mute small" style="margin:14px 0 0"><a href="<?= e(url()) ?>">← กลับหน้าเว็บไซต์</a></p>
  </form>
</main>
</body>
</html>
