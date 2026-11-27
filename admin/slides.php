<?php
declare(strict_types=1);

/* ภาพสไลด์หน้าแรก — แสดงต่อจากฉากเปิดในส่วนบนของหน้าแรก เลื่อนเปลี่ยนอัตโนมัติ */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

$id  = input_int('id');
$act = input('act');
$old = $id ? Database::one('SELECT * FROM slides WHERE id = ?', [$id]) : null;
if ($id && !$old) {
    abort(404, 'ไม่พบภาพสไลด์นี้');
}

if (is_post()) {
    if ($act === 'import') {
        set_time_limit(300);
        try {
            $r = LegacySite::importSlides((int) $me['id']);
            flash($r['failed'] ? 'err' : 'ok', 'นำเข้าภาพสไลด์จากเว็บเดิม ' . $r['added'] . ' ภาพ'
                . ($r['skipped'] ? ' (ข้าม ' . $r['skipped'] . ' ภาพที่เคยนำเข้าแล้ว)' : '')
                . ($r['failed'] ? "\nนำเข้าไม่สำเร็จ:\n- " . implode("\n- ", $r['failed']) : ''));
        } catch (Throwable $ex) {
            flash('err', 'ดึงภาพสไลด์จากเว็บเดิมไม่สำเร็จ: ' . $ex->getMessage());
        }
        redirect(url('admin/slides.php'));
    }
    if ($act === 'delete' && $old) {
        Database::delete('slides', $id);
        if (!str_starts_with((string) $old['image'], 'uploads/legacy/')) {   // สำเนาจากเว็บเดิมอาจใช้ร่วมกับข่าว — เก็บไว้
            delete_upload($old['image']);
        }
        flash('ok', 'ลบภาพสไลด์ "' . $old['title'] . '" แล้ว');
        redirect(url('admin/slides.php'));
    }
    if ($act === 'toggle' && $old) {
        Database::update('slides', ['is_enabled' => $old['is_enabled'] ? 0 : 1], $id);
        flash('ok', $old['is_enabled'] ? 'ปิดใช้งานภาพสไลด์แล้ว' : 'เปิดใช้งานภาพสไลด์แล้ว');
        redirect(url('admin/slides.php'));
    }
    if (($act === 'up' || $act === 'down') && $old) {
        // จัดลำดับใหม่ให้ต่อเนื่อง แล้วสลับกับรายการข้างเคียง
        $ids = array_map('intval', array_column(Database::all('SELECT id FROM slides ORDER BY sort, id'), 'id'));
        $i = array_search($id, $ids, true);
        $j = $act === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }
        foreach ($ids as $n => $sid) {
            Database::update('slides', ['sort' => $n + 1], $sid);
        }
        redirect(url('admin/slides.php'));
    }

    // บันทึก
    $data = [
        'title'      => mb_substr(input('title'), 0, 255),
        'link_url'   => mb_substr(input('link_url'), 0, 500),
        'start_at'   => dt_from_input(input('start_at')),
        'end_at'     => dt_from_input(input('end_at')),
        'is_enabled' => (int) !empty($_POST['is_enabled']),
    ];
    $_SESSION['_slide_form'] = $_POST;
    $errors = [];
    if ($data['title'] === '') {
        $errors[] = 'กรุณากรอกชื่อภาพ (ใช้เป็นคำอธิบายภาพสำหรับผู้พิการทางสายตา)';
    }
    if ($data['start_at'] && $data['end_at'] && $data['end_at'] <= $data['start_at']) {
        $errors[] = 'เวลาหยุดแสดงต้องอยู่หลังเวลาเริ่มแสดง';
    }
    if ($data['link_url'] !== '' && !preg_match('~^(https?://|/)~i', $data['link_url'])) {
        $errors[] = 'ลิงก์ต้องขึ้นต้นด้วย http://, https:// หรือ /';
    }
    try {
        $img = handle_image_upload('image', 8 * 1048576);
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
        $img = null;
    }
    if (!$img && !$old && !$errors) {
        $errors[] = 'กรุณาเลือกไฟล์ภาพสไลด์';
    }
    if ($errors) {
        if ($img) {
            delete_upload($img);
        }
        flash('err', implode("\n", $errors));
        redirect(url('admin/slides.php', ['act' => $id ? 'edit' : 'new', 'id' => $id ?: null]));
    }
    if ($img) {
        $data['image'] = $img;
    }
    if ($old) {
        Database::update('slides', $data, $id);
        if ($img && !str_starts_with((string) $old['image'], 'uploads/legacy/')) {
            delete_upload($old['image']);
        }
    } else {
        $id = Database::insert('slides', $data + ['sort' => (int) Database::value('SELECT COALESCE(MAX(sort), 0) + 1 FROM slides'), 'created_by' => $me['id']]);
    }
    unset($_SESSION['_slide_form']);
    flash('ok', 'บันทึกภาพสไลด์แล้ว');
    redirect(url('admin/slides.php'));
}

$editing = $act === 'new' || $old;
$s = $old ?? ['id' => 0, 'title' => '', 'image' => null, 'link_url' => '', 'is_enabled' => 1, 'start_at' => null, 'end_at' => null];
if (!empty($_SESSION['_slide_form'])) {
    $f = $_SESSION['_slide_form'];
    $s = array_merge($s, array_intersect_key($f, array_flip(['title', 'link_url'])), [
        'start_at' => dt_from_input((string) ($f['start_at'] ?? '')), 'end_at' => dt_from_input((string) ($f['end_at'] ?? '')),
        'is_enabled' => (int) !empty($f['is_enabled']),
    ]);
    unset($_SESSION['_slide_form']);
}
$rows = Database::all('SELECT * FROM slides ORDER BY sort, id');
$showing = count(active_slides());
$recentNews = Database::all('SELECT n.id, n.title FROM news n WHERE ' . NEWS_PUBLIC . ' ORDER BY n.published_at DESC LIMIT 15');

admin_header('ภาพสไลด์หน้าแรก', 'slides', 'ภาพสไลด์หน้าแรก › ' . ($editing ? ($s['id'] ? 'แก้ไข' : 'เพิ่มใหม่') : 'ทั้งหมด'));
?>
<?php if ($editing): ?>
<form method="post" enctype="multipart/form-data">
<?= Csrf::field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
<div class="head">
  <div><h1><?= $s['id'] ? 'แก้ไขภาพสไลด์' : 'เพิ่มภาพสไลด์' ?></h1>
  <p>ภาพจะแสดงต่อจากฉากเปิดในส่วนบนของหน้าแรก และเลื่อนเปลี่ยนอัตโนมัติ</p></div>
  <div class="acts">
    <a class="btn ghost" href="<?= e(url('admin/slides.php')) ?>">ยกเลิก</a>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</div>
<div class="cols">
  <div class="wide">
    <section class="card">
      <h2><span class="num">1</span>ภาพสไลด์</h2>
      <div class="slide-pv" id="sl-pv" style="<?= $s['image'] ? 'background-image:url(' . e(upload_url($s['image'])) . ')' : '' ?>"><?php if (!$s['image']): ?><span>ตัวอย่างภาพ</span><?php endif; ?></div>
      <div class="drop" style="margin-top:12px">
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#sl-pv" aria-label="เลือกภาพสไลด์" <?= $s['image'] ? '' : 'required' ?>>
        <div class="hint">JPG, PNG หรือ WebP · แนะนำภาพแนวนอนกว้าง 1920 × 710 px (สัดส่วนประมาณ 8 : 3) · ไม่เกิน 8 MB</div>
      </div>
      <label class="f" for="st">ชื่อภาพ / คำอธิบายภาพ</label>
      <input id="st" name="title" value="<?= e($s['title']) ?>" maxlength="255" required placeholder="เช่น ยินดีต้อนรับผู้อำนวยการคนใหม่">
      <div class="hint">ใช้เป็นข้อความแทนภาพสำหรับโปรแกรมอ่านหน้าจอ — ควรสรุปข้อความในภาพ</div>
      <label class="f" for="sl">ลิงก์เมื่อคลิกภาพ <span class="opt">(ไม่บังคับ)</span></label>
      <input id="sl" name="link_url" value="<?= e($s['link_url']) ?>" list="news-links" placeholder="เลือกข่าวในเว็บไซต์ หรือวางลิงก์">
      <datalist id="news-links"><?php foreach ($recentNews as $n): ?><option value="<?= e(news_url($n)) ?>"><?= e($n['title']) ?></option><?php endforeach; ?></datalist>
    </section>
  </div>
  <aside class="narrow">
    <section class="card">
      <h2><span class="num">2</span>ช่วงเวลาและการแสดง</h2>
      <label class="f" for="sd">เริ่มแสดง <span class="opt">(เว้นว่าง = ทันที)</span></label>
      <input id="sd" type="datetime-local" name="start_at" value="<?= e($s['start_at'] ? dt_to_input($s['start_at']) : '') ?>">
      <label class="f" for="ed">หยุดแสดง <span class="opt">(เว้นว่าง = ไม่มีกำหนด)</span></label>
      <input id="ed" type="datetime-local" name="end_at" value="<?= e($s['end_at'] ? dt_to_input($s['end_at']) : '') ?>">
      <label class="chk" style="margin-top:14px;font-weight:600"><input type="checkbox" name="is_enabled" value="1" <?= $s['is_enabled'] ? 'checked' : '' ?> style="accent-color:var(--ok)"> เปิดใช้งานภาพนี้</label>
      <?php if ($s['id']): ?><p style="margin:14px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบภาพสไลด์นี้</button></p><?php endif; ?>
    </section>
  </aside>
</div>
</form>
<?php if ($s['id']): ?><form id="del-form" method="post" data-confirm="ลบภาพสไลด์นี้ถาวร?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form><?php endif; ?>
<div style="height:28px"></div>
<?php else: ?>
<div class="head">
  <div><h1>ภาพสไลด์หน้าแรก</h1><p>ส่วนบนของหน้าแรกจะแสดงฉากเปิดของเว็บก่อน แล้วเลื่อนไปยังภาพสไลด์ตามลำดับด้านล่าง · ตอนนี้แสดงอยู่ <?= $showing ?> ภาพ</p></div>
  <div class="acts">
    <form method="post" data-confirm="ดึงภาพสไลด์ที่เปิดใช้อยู่บนเว็บเดิม (<?= e(LegacySite::siteBase()) ?>) มาเพิ่ม? ภาพที่เคยนำเข้าแล้วจะไม่ซ้ำ">
      <?= Csrf::field() ?><input type="hidden" name="act" value="import">
      <button class="btn ghost" type="submit"><?= icon('imp', 18) ?> นำเข้าจากเว็บเดิม</button>
    </form>
    <a class="btn big" href="<?= e(url('admin/slides.php', ['act' => 'new'])) ?>">+ เพิ่มภาพสไลด์</a>
  </div>
</div>
<?php endif; ?>

<section class="tbl">
  <div class="ttl">ลำดับการแสดง</div>
  <div class="tw"><table style="min-width:720px">
    <thead><tr><th style="width:56px">ลำดับ</th><th>ภาพ</th><th>ช่วงเวลา</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <tr class="row"><td class="mute">—</td><td><div class="slide-row"><div class="slide-th intro">ฉากเปิด</div><div><b>ฉากเปิดของเว็บไซต์</b><div class="small mute">ชื่อวิทยาลัยและปุ่มสมัครเรียน (แก้ข้อความได้ที่ตั้งค่าเว็บไซต์) · แสดงเป็นภาพแรกเสมอ</div></div></div></td><td class="mute">—</td><td><?= status_pill(['แสดงเสมอ', '#e8f0fb', '#1f5aa6']) ?></td><td></td></tr>
    <?php if (!$rows): ?><tr><td colspan="5" class="empty">ยังไม่มีภาพสไลด์ — กด “นำเข้าจากเว็บเดิม” หรือ “เพิ่มภาพสไลด์”</td></tr><?php endif; ?>
    <?php foreach ($rows as $i => $r): ?>
      <tr class="row">
        <td><div class="ord">
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="up"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="ordbtn" type="submit" <?= $i === 0 ? 'disabled' : '' ?> aria-label="เลื่อนขึ้น">▲</button></form>
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="down"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="ordbtn" type="submit" <?= $i === count($rows) - 1 ? 'disabled' : '' ?> aria-label="เลื่อนลง">▼</button></form>
        </div></td>
        <td><div class="slide-row"><div class="slide-th" style="background-image:url(<?= e(upload_url($r['image'])) ?>)"></div>
          <div><b><?= e($r['title']) ?></b><?php if ($r['link_url']): ?><div class="small mute">ลิงก์: <?= e(str_limit($r['link_url'], 60)) ?></div><?php endif; ?>
          <?php if ($r['source_ref']): ?><div class="small mute">นำเข้าจากเว็บเดิม</div><?php endif; ?></div></div></td>
        <td class="mute small"><?= $r['start_at'] || $r['end_at'] ? e(($r['start_at'] ? thai_date($r['start_at'], 'short') : 'ทันที') . ' – ' . ($r['end_at'] ? thai_date($r['end_at'], 'short') : 'ไม่มีกำหนด')) : 'ไม่มีกำหนด' ?></td>
        <td><?= status_pill(slide_state($r)) ?></td>
        <td class="r"><div class="acts-cell">
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="linkbtn" type="submit"><?= $r['is_enabled'] ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button></form>
          <a class="btn sm ghost" href="<?= e(url('admin/slides.php', ['act' => 'edit', 'id' => $r['id']])) ?>">แก้ไข</a></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php admin_footer();
