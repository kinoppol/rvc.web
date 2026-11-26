<?php
declare(strict_types=1);

/* ผู้ใช้และสิทธิ์ — ผู้ดูแลระบบ (ทุกเมนู) / ผู้เขียนข่าว (ข่าวและป๊อปอัพของหน่วยงานตน) */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

$id  = input_int('id');
$act = input('act');
$old = $id ? Database::one('SELECT * FROM users WHERE id = ?', [$id]) : null;
if ($id && !$old) {
    abort(404, 'ไม่พบผู้ใช้นี้');
}
$adminCount = fn() => (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");

if (is_post()) {
    if ($act === 'delete' && $old) {
        if ((int) $old['id'] === (int) $me['id']) {
            flash('err', 'ลบบัญชีของตัวเองไม่ได้');
        } elseif ($old['role'] === 'admin' && $adminCount() <= 1) {
            flash('err', 'ต้องมีผู้ดูแลระบบอย่างน้อย 1 คน');
        } else {
            Database::delete('users', $id);
            flash('ok', 'ลบผู้ใช้ "' . $old['username'] . '" แล้ว');
        }
        redirect(url('admin/users.php'));
    }
    $data = [
        'username'     => input('username'),
        'display_name' => mb_substr(input('display_name'), 0, 120),
        'role'         => array_key_exists(input('role'), Auth::ROLES) ? input('role') : 'editor',
        'unit_id'      => input_int('unit_id') ?: null,
        'is_active'    => (int) !empty($_POST['is_active']),
    ];
    $pass = (string) ($_POST['password'] ?? '');
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,60}$/', $data['username'])) {
        $errors[] = 'ชื่อผู้ใช้ต้องยาว 3–60 ตัว ใช้ได้เฉพาะ a-z, 0-9, _ . -';
    } elseif (Database::value('SELECT id FROM users WHERE username = ? AND id <> ?', [$data['username'], $id])) {
        $errors[] = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว';
    }
    if (!$old || $pass !== '') {
        if (mb_strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
            $errors[] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัว และมีทั้งตัวอักษรและตัวเลข';
        } elseif ($pass !== (string) ($_POST['password_confirm'] ?? '')) {
            $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
        }
    }
    if ($old && $old['role'] === 'admin' && ($data['role'] !== 'admin' || !$data['is_active']) && $adminCount() <= 1) {
        $errors[] = 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน';
    }
    if ($old && (int) $old['id'] === (int) $me['id'] && (!$data['is_active'] || $data['role'] !== 'admin')) {
        $errors[] = 'ไม่สามารถลดสิทธิ์หรือปิดบัญชีของตัวเองได้';
    }
    if ($errors) {
        flash('err', implode("\n", $errors));
        redirect(url('admin/users.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    if ($pass !== '') {
        $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
    }
    $old ? Database::update('users', $data, $id) : ($id = Database::insert('users', $data));
    flash('ok', 'บันทึกผู้ใช้ "' . $data['username'] . '" แล้ว');
    redirect(url('admin/users.php'));
}

$units = Database::all("SELECT id, name, kind FROM units ORDER BY FIELD(kind,'department','division','work'), sort, name");

if ($act === 'new' || $old) {
    $u = $old ?? ['id' => 0, 'username' => '', 'display_name' => '', 'role' => 'editor', 'unit_id' => null, 'is_active' => 1];
    admin_header($u['id'] ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้', 'users');
    ?>
    <form method="post" autocomplete="off">
    <?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/users.php')) ?>">‹ กลับไปรายการผู้ใช้</a><h1><?= $u['id'] ? e($u['username']) : 'เพิ่มผู้ใช้' ?></h1></div>
      <div class="acts"><button class="btn" type="submit">บันทึก</button></div>
    </div>
    <div class="cols">
      <section class="card wide" style="display:block">
        <div class="grid2">
          <div><label class="f" for="un">ชื่อผู้ใช้</label><input id="un" name="username" value="<?= e($u['username']) ?>" pattern="[A-Za-z0-9_.\-]{3,60}" required></div>
          <div><label class="f" for="dn">ชื่อที่แสดง</label><input id="dn" name="display_name" value="<?= e($u['display_name']) ?>"></div>
        </div>
        <div class="grid2">
          <div><label class="f" for="pw">รหัสผ่าน<?= $u['id'] ? ' <span class="opt">(เว้นว่างถ้าไม่เปลี่ยน)</span>' : '' ?></label><input id="pw" type="password" name="password" autocomplete="new-password" <?= $u['id'] ? '' : 'required' ?>></div>
          <div><label class="f" for="pc">ยืนยันรหัสผ่าน</label><input id="pc" type="password" name="password_confirm" autocomplete="new-password"></div>
        </div>
        <p class="hint">อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข</p>
      </section>
      <aside class="narrow"><section class="card">
        <label class="f" for="ro">สิทธิ์</label>
        <select id="ro" name="role"><?php foreach (Auth::ROLES as $k => $v): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <div class="hint">ผู้เขียนข่าว: เขียนข่าวของหน่วยงานตน (ต้องรออนุมัติ) และตั้งป๊อปอัพได้</div>
        <label class="f" for="ut">หน่วยงานที่ดูแล</label>
        <select id="ut" name="unit_id"><option value="">— ไม่ระบุ —</option><?php foreach ($units as $un): ?><option value="<?= (int) $un['id'] ?>" <?= (int) $u['unit_id'] === (int) $un['id'] ? 'selected' : '' ?>><?= e(unit_title($un)) ?></option><?php endforeach; ?></select>
        <label class="chk top"><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?>> เปิดใช้งานบัญชี</label>
        <?php if ($u['id'] && (int) $u['id'] !== (int) $me['id']): ?><p style="margin:12px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบผู้ใช้นี้</button></p><?php endif; ?>
      </section></aside>
    </div>
    </form>
    <?php if ($u['id']): ?><form id="del-form" method="post" data-confirm="ลบผู้ใช้นี้?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form><?php endif;
    admin_footer();
    exit;
}

$rows = Database::all('SELECT s.*, u.name AS unit_name, u.kind FROM users s LEFT JOIN units u ON u.id = s.unit_id ORDER BY s.role, s.username');
admin_header('ผู้ใช้และสิทธิ์', 'users');
?>
<div class="head">
  <div><h1>ผู้ใช้และสิทธิ์</h1><p>กำหนดผู้ดูแลแต่ละหน่วยงานให้เขียนข่าวของตนเองได้</p></div>
  <a class="btn big" href="<?= e(url('admin/users.php', ['act' => 'new'])) ?>">+ เพิ่มผู้ใช้</a>
</div>
<section class="tbl"><div class="tw"><table style="min-width:640px">
  <thead><tr><th>ผู้ใช้</th><th>สิทธิ์</th><th>หน่วยงาน</th><th>เข้าระบบล่าสุด</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $u): ?>
    <tr class="row"><td><b><?= e($u['username']) ?></b><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="pill info">คุณ</span>' : '' ?><?= $u['is_active'] ? '' : ' <span class="pill mute">ปิดใช้งาน</span>' ?>
      <div class="small mute"><?= e($u['display_name']) ?></div></td>
      <td><?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?></td><td class="mute"><?= $u['unit_name'] ? e(unit_title(['name' => $u['unit_name'], 'kind' => $u['kind']])) : '—' ?></td>
      <td class="small mute"><?= e(thai_date($u['last_login_at'], 'time')) ?></td>
      <td class="r"><a class="btn sm ghost" href="<?= e(url('admin/users.php', ['act' => 'edit', 'id' => $u['id']])) ?>">แก้ไข</a></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div></section>
<?php admin_footer();
