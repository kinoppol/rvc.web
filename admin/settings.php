<?php
declare(strict_types=1);

/* ตั้งค่าเว็บไซต์ — ชื่อ สารจากผู้อำนวยการ ตัวเลขสถิติ ข้อมูลติดต่อ ลิงก์ด่วน */

require __DIR__ . '/_init.php';
Auth::require('admin');
Csrf::verify();

if (is_post()) {
    $errors = [];
    foreach (Settings::FIELDS as $fields) {
        foreach ($fields as $key => [$label, , $type]) {
            if ($type === 'image') {
                try {
                    $img = handle_image_upload($key);
                    if ($img) {
                        delete_upload(Settings::get($key));
                        Settings::set($key, $img);
                    } elseif (!empty($_POST['remove_' . $key])) {
                        delete_upload(Settings::get($key));
                        Settings::set($key, '');
                    }
                } catch (RuntimeException $ex) {
                    $errors[] = $label . ': ' . $ex->getMessage();
                }
                continue;
            }
            $v = input($key);
            if ($type === 'url' && $v !== '' && !preg_match('~^https?://~i', $v)) {
                $errors[] = $label . ': ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://';
                continue;
            }
            Settings::set($key, mb_substr($v, 0, 2000));
        }
    }
    $errors ? flash('err', implode("\n", $errors)) : flash('ok', 'บันทึกการตั้งค่าแล้ว');
    redirect(url('admin/settings.php'));
}

admin_header('ตั้งค่าเว็บไซต์', 'settings', 'ผู้ดูแลระบบ › ตั้งค่าเว็บไซต์');
?>
<form method="post" enctype="multipart/form-data">
<?= Csrf::field() ?>
<div class="head">
  <div><h1>ตั้งค่าเว็บไซต์</h1><p>ข้อมูลที่แสดงในหน้าแรก ส่วนหัว และส่วนท้ายของเว็บไซต์</p></div>
  <div class="acts"><button class="btn" type="submit">บันทึกการตั้งค่า</button></div>
</div>
<div style="display:grid;gap:20px;max-width:880px">
<?php foreach (Settings::FIELDS as $group => $fields): ?>
  <section class="card">
    <h2><?= e($group) ?></h2>
    <?php foreach ($fields as $key => [$label, $def, $type]): $v = Settings::get($key); ?>
      <label class="f" for="<?= e($key) ?>"><?= e($label) ?></label>
      <?php if ($type === 'textarea'): ?>
        <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" style="min-height:90px" placeholder="<?= e($def) ?>"><?= e($v) ?></textarea>
      <?php elseif ($type === 'image'): ?>
        <div class="drop" style="text-align:left;display:flex;gap:16px;align-items:center">
          <img id="img-<?= e($key) ?>" src="<?= e(upload_url($v) ?? '') ?>" alt="" style="width:96px;max-height:120px;margin:0;object-fit:cover" <?= $v ? '' : 'hidden' ?>>
          <div><input type="file" id="<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*" data-preview="#img-<?= e($key) ?>">
            <?php if ($v): ?><label class="chk"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> ลบรูป</label><?php endif; ?></div>
        </div>
      <?php else: ?>
        <input id="<?= e($key) ?>" name="<?= e($key) ?>" type="<?= $type === 'url' ? 'url' : 'text' ?>" value="<?= e($v) ?>" placeholder="<?= e($def) ?>">
      <?php endif; ?>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
  <div><button class="btn" type="submit">บันทึกการตั้งค่า</button></div>
</div>
</form>
<?php admin_footer();
