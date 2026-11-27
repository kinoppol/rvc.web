<?php
declare(strict_types=1);

/* ป๊อปอัพประกาศ / เทศกาล — ตั้งเวลาแสดงและซ่อนอัตโนมัติ (ผู้เข้าชมเห็นวันละ 1 ครั้ง) */

require __DIR__ . '/_init.php';
$me = Auth::require();
Csrf::verify();

$id  = input_int('id');
$act = input('act');
$old = $id ? Database::one('SELECT * FROM popups WHERE id = ?', [$id]) : null;
if ($id && !$old) {
    abort(404, 'ไม่พบป๊อปอัพนี้');
}

if (is_post()) {
    if ($act === 'delete' && $old) {
        Database::delete('popups', $id);
        delete_upload($old['image']);
        flash('ok', 'ลบป๊อปอัพแล้ว');
        redirect(url('admin/popups.php'));
    }
    if ($act === 'toggle' && $old) {
        Database::update('popups', ['is_enabled' => $old['is_enabled'] ? 0 : 1], $id);
        flash('ok', $old['is_enabled'] ? 'ปิดใช้งานป๊อปอัพแล้ว' : 'เปิดใช้งานป๊อปอัพแล้ว');
        redirect(url('admin/popups.php'));
    }

    $draft = input('intent') === 'draft';
    $data = [
        'title'      => mb_substr(input('title'), 0, 255),
        'body'       => mb_substr(input('body'), 0, 500),
        'link_url'   => mb_substr(input('link_url'), 0, 500),
        'start_at'   => dt_from_input(input('start_at')),
        'end_at'     => dt_from_input(input('end_at')),
        'placement'  => array_key_exists(input('placement'), POPUP_PLACEMENTS) ? input('placement') : 'home',
        'is_enabled' => (int) !empty($_POST['is_enabled']),
        'is_draft'   => (int) $draft,
    ];
    $_SESSION['_popup_form'] = $_POST;
    $errors = [];
    if ($data['title'] === '') {
        $errors[] = 'กรุณากรอกหัวข้อ';
    }
    if (!$data['start_at'] || !$data['end_at']) {
        $errors[] = 'กรุณาระบุเวลาเริ่มแสดงและหยุดแสดง';
    } elseif ($data['end_at'] <= $data['start_at']) {
        $errors[] = 'เวลาหยุดแสดงต้องอยู่หลังเวลาเริ่มแสดง';
    }
    if ($data['link_url'] !== '' && !preg_match('~^(https?://|/)~i', $data['link_url'])) {
        $errors[] = 'ลิงก์ต้องขึ้นต้นด้วย http://, https:// หรือ /';
    }
    try {
        $img = handle_image_upload('image');
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
        $img = null;
    }
    if ($errors) {
        if ($img) {
            delete_upload($img);
        }
        flash('err', implode("\n", $errors));
        redirect(url('admin/popups.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    if ($img) {
        $data['image'] = $img;
    } elseif (!empty($_POST['remove_image'])) {
        $data['image'] = null;
    }
    if ($old) {
        Database::update('popups', $data, $id);
        if (array_key_exists('image', $data)) {
            delete_upload($old['image']);
        }
    } else {
        $id = Database::insert('popups', $data + ['created_by' => $me['id']]);
    }
    unset($_SESSION['_popup_form']);
    flash('ok', $draft ? 'บันทึกฉบับร่างแล้ว' : 'บันทึกแล้ว ป๊อปอัพจะเริ่มแสดงตามเวลาที่ตั้งไว้');
    redirect(url('admin/popups.php', ['act' => 'edit', 'id' => $id]));
}

$editing = $act === 'new' || $act === 'edit' || $old;
$p = $old ?? [
    'id' => 0, 'title' => '', 'body' => '', 'image' => null, 'link_url' => '', 'placement' => 'home', 'is_enabled' => 1, 'is_draft' => 0,
    'start_at' => date('Y-m-d 00:00:00'), 'end_at' => date('Y-m-d 23:59:00', strtotime('+6 days')),
];
if (!empty($_SESSION['_popup_form'])) {
    $f = $_SESSION['_popup_form'];
    $p = array_merge($p, array_intersect_key($f, array_flip(['title', 'body', 'link_url', 'placement'])), [
        'start_at' => dt_from_input((string) ($f['start_at'] ?? '')) ?? $p['start_at'],
        'end_at'   => dt_from_input((string) ($f['end_at'] ?? '')) ?? $p['end_at'],
        'is_enabled' => (int) !empty($f['is_enabled']),
    ]);
    unset($_SESSION['_popup_form']);
}
$rows = Database::all('SELECT * FROM popups ORDER BY end_at < NOW(), start_at DESC LIMIT 100');
$recentNews = Database::all('SELECT n.id, n.title FROM news n WHERE ' . NEWS_PUBLIC . ' ORDER BY n.published_at DESC LIMIT 15');

admin_header('ป๊อปอัพ', 'popups', 'ป๊อปอัพ › ' . ($editing ? ($p['id'] ? 'แก้ไข' : 'สร้างใหม่') : 'ทั้งหมด'));
?>
<?php if ($editing): ?>
<form method="post" enctype="multipart/form-data" id="popup-form">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<div class="head">
  <div><h1>ตั้งป๊อปอัพประกาศ / เทศกาล</h1>
  <p>กรอก 3 ขั้น แล้วกด “บันทึกและตั้งเวลา” ระบบจะแสดงและซ่อนให้เองตามเวลาที่กำหนด</p></div>
  <div class="acts">
    <?= ai_button('popup', ['title' => '#pt', 'body' => '#pb']) ?>
    <a class="btn ghost" href="<?= e(url('admin/popups.php')) ?>">ยกเลิก</a>
    <button class="btn ghost" type="submit" name="intent" value="draft">บันทึกฉบับร่าง</button>
    <button class="btn" type="submit" name="intent" value="save">บันทึกและตั้งเวลา</button>
  </div>
</div>
<div class="cols">
  <div class="wide">
    <section class="card">
      <h2><span class="num">1</span>เนื้อหาป๊อปอัพ</h2>
      <label class="f" for="pt">หัวข้อ</label>
      <input id="pt" name="title" value="<?= e($p['title']) ?>" maxlength="255" placeholder="เช่น วันพ่อแห่งชาติ 5 ธันวาคม" required>
      <label class="f" for="pb">ข้อความประกอบ <span class="opt">(ไม่เกิน 2 บรรทัด)</span></label>
      <input id="pb" name="body" value="<?= e($p['body']) ?>" maxlength="500">
      <div style="font-weight:600;margin:16px 0 6px">ภาพป๊อปอัพ</div>
      <div class="drop">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1f5aa6" stroke-width="1.8" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>
        <div style="margin-top:6px"><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#pv-img" aria-label="เลือกรูปภาพ"></div>
        <div class="hint">JPG หรือ PNG แนะนำ 1200 × 900 px · ไม่เกิน 5 MB</div>
        <?php if ($p['image']): ?><label class="chk" style="justify-content:center"><input type="checkbox" name="remove_image" value="1"> ลบรูปเดิม</label><?php endif; ?>
      </div>
      <label class="f" for="pl">ลิงก์เมื่อกดปุ่ม “อ่านรายละเอียด” <span class="opt">(ไม่บังคับ)</span></label>
      <input id="pl" name="link_url" value="<?= e($p['link_url']) ?>" list="news-links" placeholder="เลือกข่าวในเว็บไซต์ หรือวางลิงก์">
      <datalist id="news-links"><?php foreach ($recentNews as $n): ?><option value="<?= e(news_url($n)) ?>"><?= e($n['title']) ?></option><?php endforeach; ?></datalist>
    </section>

    <section class="card">
      <h2><span class="num">2</span>ช่วงเวลาที่แสดง</h2>
      <div class="grid2">
        <div><label class="f" for="sd">เริ่มแสดง</label><input id="sd" type="datetime-local" name="start_at" value="<?= e(dt_to_input($p['start_at'])) ?>" required></div>
        <div><label class="f" for="ed">หยุดแสดง</label><input id="ed" type="datetime-local" name="end_at" value="<?= e(dt_to_input($p['end_at'])) ?>" required></div>
      </div>
      <div class="quick"><span class="small mute">ตั้งเร็ว:</span>
        <button type="button" class="chip" data-range="1" data-start="#sd" data-end="#ed">วันนี้ทั้งวัน</button>
        <button type="button" class="chip" data-range="7" data-start="#sd" data-end="#ed">7 วัน</button>
        <button type="button" class="chip" data-range="30" data-start="#sd" data-end="#ed">30 วัน</button></div>
      <div class="note"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1f5aa6" stroke-width="1.8" aria-hidden="true" style="flex:none;margin-top:2px"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <div><b>ความถี่: แสดงวันละ 1 ครั้งต่อผู้เข้าชม</b><br>ผู้เข้าชมแต่ละคนจะเห็นป๊อปอัพนี้ครั้งแรกที่เข้าเว็บในแต่ละวัน เมื่อปิดแล้วจะไม่แสดงซ้ำจนถึงวันถัดไป</div></div>
    </section>

    <section class="card">
      <h2><span class="num">3</span>ตำแหน่งและการเปิดใช้</h2>
      <label class="f" for="wh">แสดงที่</label>
      <select id="wh" name="placement"><?php foreach (POPUP_PLACEMENTS as $k => $v): ?><option value="<?= $k ?>" <?= $p['placement'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      <label class="chk" style="margin-top:12px;font-weight:600"><input type="checkbox" id="en" name="is_enabled" value="1" <?= $p['is_enabled'] ? 'checked' : '' ?> style="accent-color:var(--ok)"> เปิดใช้งานป๊อปอัพนี้</label>
      <?php if ($p['id']): ?><p style="margin:12px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบป๊อปอัพนี้</button></p><?php endif; ?>
    </section>
  </div>

  <aside class="narrow sticky">
    <div class="tbl">
      <div class="pv-head"><b>ตัวอย่างที่ผู้เข้าชมจะเห็น</b><span class="pill" id="pv-st">—</span></div>
      <div class="pv-stage"><div class="pv-box">
        <div class="pv-img" id="pv-img" style="<?= $p['image'] ? 'background-image:url(' . e(upload_url($p['image'])) . ')' : '' ?>"><?php if (!$p['image']): ?><div><div style="width:44px;height:2px;margin:0 auto 12px;background:#f2c14e"></div><div class="hd" style="font-size:18px">ภาพที่อัปโหลด</div></div><?php endif; ?></div>
        <div class="pv-t" id="pv-t"></div><div class="pv-b" id="pv-b"></div>
        <div class="pv-a"><span>ปิดหน้าต่าง</span><span class="p">อ่านรายละเอียด</span></div>
      </div></div>
      <div class="pv-sum" id="pv-sum"></div>
    </div>
  </aside>
</div>
</form>
<?php if ($p['id']): ?><form id="del-form" method="post" data-confirm="ลบป๊อปอัพนี้ถาวร?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"></form><?php endif; ?>
<div style="height:28px"></div>
<?php else: ?>
<div class="head">
  <div><h1>ป๊อปอัพ</h1><p>ประกาศ/ภาพเทศกาลที่เด้งขึ้นเมื่อเข้าเว็บ แสดงอัตโนมัติตามช่วงเวลา</p></div>
  <a class="btn big" href="<?= e(url('admin/popups.php', ['act' => 'new'])) ?>">+ ตั้งป๊อปอัพใหม่</a>
</div>
<?php endif; ?>

<section class="tbl">
  <div class="ttl">ป๊อปอัพทั้งหมด</div>
  <div class="tw"><table style="min-width:640px">
    <thead><tr><th>หัวข้อ</th><th>ช่วงเวลา</th><th>แสดงที่</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">ยังไม่มีป๊อปอัพ</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr class="row"><td><b><?= e($r['title']) ?></b></td><td class="mute"><?= e(date_range($r['start_at'], $r['end_at'])) ?></td>
        <td class="small mute"><?= e(POPUP_PLACEMENTS[$r['placement']] ?? '') ?></td><td><?= status_pill(popup_state($r)) ?></td>
        <td class="r"><div class="acts-cell">
          <?php if (!$r['is_draft']): ?><form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="linkbtn" type="submit"><?= $r['is_enabled'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button></form><?php endif; ?>
          <a class="btn sm ghost" href="<?= e(url('admin/popups.php', ['act' => 'edit', 'id' => $r['id']])) ?>">แก้ไข</a></div></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php admin_footer();
