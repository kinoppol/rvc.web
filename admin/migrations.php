<?php
declare(strict_types=1);

/* เมนู Migrations — จัดการการปรับปรุงโครงสร้างฐานข้อมูล (เฉพาะผู้ดูแลระบบ) */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

$mig = new Migrator(Database::pdo(), BASE_PATH . '/migrations', $me['username']);

if (is_post()) {
    $act  = input('act');
    $name = input('name');
    $back = url('admin/migrations.php');
    $show = function (array $res, string $okMsg, string $verb): void {
        if ($res['ok']) {
            flash('ok', $res['ran'] ? $okMsg . "\n" . implode("\n", array_map(fn($n) => '✔ ' . $n, $res['ran'])) : 'ไม่มีรายการที่ต้อง' . $verb);
        } else {
            $done = $res['ran'] ? "\nสำเร็จก่อนหน้า: " . implode(', ', $res['ran']) : '';
            flash('err', $verb . 'ไม่สำเร็จ — ' . $res['error'] . $done);
        }
    };
    switch ($act) {
        case 'up_all':
            $show($mig->migrate(), 'รัน migration สำเร็จ', 'รัน');
            break;
        case 'up_one':
            $show($mig->migrate($name), 'รัน migration สำเร็จ', 'รัน');
            break;
        case 'down_batch':
            if (input('confirm') !== 'ROLLBACK') {
                flash('err', 'พิมพ์ ROLLBACK เพื่อยืนยันการย้อนกลับ');
                break;
            }
            $show($mig->rollback(), 'ย้อนกลับ batch ล่าสุดสำเร็จ', 'ย้อนกลับ');
            break;
        case 'down_one':
            $show($mig->rollback($name), 'ย้อนกลับสำเร็จ', 'ย้อนกลับ');
            break;
        case 'accept':
            $mig->acceptChecksum($name)
                ? flash('ok', "ยอมรับการแก้ไขไฟล์ {$name} แล้ว (อัปเดต checksum)")
                : flash('err', 'ไม่พบรายการ');
            break;
        case 'forget':
            $mig->forget($name);
            flash('ok', "ลบบันทึกของ {$name} แล้ว");
            break;
        case 'create':
            try {
                $file = $mig->create(input('slug'), input('description') ?: input('slug'));
                flash('ok', "สร้างไฟล์ migrations/{$file}.php แล้ว — เขียนคำสั่งใน up()/down() แล้วกลับมากดรัน");
            } catch (Throwable $ex) {
                flash('err', 'สร้างไฟล์ไม่สำเร็จ — ' . $ex->getMessage());
            }
            break;
    }
    redirect($back);
}

$status   = $mig->status();
$pending  = array_filter($status, fn($r) => $r['state'] === 'pending');
$modified = array_filter($status, fn($r) => $r['state'] === 'modified');
$missing  = array_filter($status, fn($r) => $r['state'] === 'missing');
$applied  = array_filter($status, fn($r) => $r['state'] !== 'pending');
$lastBatch = $applied ? max(array_map(fn($r) => (int) $r['batch'], $applied)) : 0;
$history  = $mig->history(25);
$tables   = Database::all("SELECT table_name AS t, table_rows AS r, ROUND((data_length + index_length) / 1024) AS kb
                           FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name");
$server   = Requirements::checkServer(Database::pdo())[1];
$canWrite = is_writable(BASE_PATH . '/migrations');
$states   = ['applied' => ['ติดตั้งแล้ว', 'ok'], 'pending' => ['รอติดตั้ง', 'warn'], 'modified' => ['ไฟล์ถูกแก้หลังรัน', 'bad'], 'missing' => ['ไม่พบไฟล์', 'bad']];

admin_header('Migrations', 'migrations', 'ผู้ดูแลระบบ › Migrations');
?>
<div class="head">
  <div><h1>Migrations · โครงสร้างฐานข้อมูล</h1>
  <p><?= e($server) ?> · ฐานข้อมูล <code><?= e($GLOBALS['config']['db']['name']) ?></code> · ไฟล์อยู่ในโฟลเดอร์ <code>migrations/</code></p></div>
  <div class="acts">
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="up_all">
      <button class="btn big" type="submit" <?= $pending ? '' : 'disabled' ?>>รันที่รอติดตั้ง (<?= count($pending) ?>)</button></form>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="l">ติดตั้งแล้ว</div><div class="v"><?= count($applied) - count($missing) ?></div></div>
  <div class="stat"><div class="l">รอติดตั้ง</div><div class="v" style="color:var(--warn)"><?= count($pending) ?></div></div>
  <div class="stat"><div class="l">batch ล่าสุด</div><div class="v"><?= $lastBatch ?: '—' ?></div></div>
  <div class="stat"><div class="l">ต้องตรวจสอบ</div><div class="v" style="color:<?= ($modified || $missing) ? 'var(--bad)' : 'var(--mute)' ?>"><?= count($modified) + count($missing) ?></div></div>
</div>

<?php if ($modified): ?>
  <div class="msg warn">มีไฟล์ที่ถูกแก้ไขหลังจากรันไปแล้ว — การแก้ไขจะไม่มีผลกับฐานข้อมูล ควรสร้าง migration ใหม่แทนการแก้ไฟล์เดิม
หากตั้งใจแก้ (เช่น แก้คอมเมนต์) กด "ยอมรับ" เพื่ออัปเดต checksum</div>
<?php endif; ?>

<section class="tbl">
  <div class="ttl">รายการ migration ทั้งหมด <span class="mute small" style="font-weight:400">เรียงจากเก่าไปใหม่ · รันตามลำดับชื่อไฟล์</span></div>
  <div class="tw"><table>
    <thead><tr><th>Migration</th><th>คำอธิบาย</th><th>สถานะ</th><th>batch</th><th>รันเมื่อ / โดย</th><th class="r"></th></tr></thead>
    <tbody>
    <?php if (!$status): ?><tr><td colspan="6" class="empty">ยังไม่มีไฟล์ migration</td></tr><?php endif; ?>
    <?php foreach ($status as $r): [$lbl, $cls] = $states[$r['state']]; ?>
      <tr class="row">
        <td><code><?= e($r['name']) ?></code></td>
        <td><?= e($r['description']) ?></td>
        <td><span class="pill <?= $cls ?>"><?= e($lbl) ?></span></td>
        <td><?= $r['batch'] ?? '—' ?></td>
        <td class="small mute nowrap"><?= $r['applied_at'] ? e(thai_date($r['applied_at'], 'time')) . '<br>' . e($r['applied_by']) . ' · ' . (int) $r['duration_ms'] . ' ms' : '—' ?></td>
        <td class="r"><div class="acts-cell">
          <?php if ($r['state'] === 'pending'): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="up_one"><input type="hidden" name="name" value="<?= e($r['name']) ?>">
              <button class="btn sm" type="submit">รัน</button></form>
          <?php elseif ($r['state'] === 'missing'): ?>
            <form method="post" data-confirm="ลบบันทึกของ migration ที่ไม่มีไฟล์? (ไม่แตะตารางในฐานข้อมูล)"><?= Csrf::field() ?><input type="hidden" name="act" value="forget"><input type="hidden" name="name" value="<?= e($r['name']) ?>">
              <button class="btn sm ghost" type="submit">ลบบันทึก</button></form>
          <?php else: ?>
            <?php if ($r['state'] === 'modified'): ?>
              <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="accept"><input type="hidden" name="name" value="<?= e($r['name']) ?>">
                <button class="btn sm ghost" type="submit">ยอมรับ</button></form>
            <?php endif; ?>
            <form method="post" data-confirm="ย้อนกลับ <?= e($r['name']) ?>? คำสั่ง down() อาจลบตาราง/คอลัมน์และข้อมูลในนั้น"><?= Csrf::field() ?><input type="hidden" name="act" value="down_one"><input type="hidden" name="name" value="<?= e($r['name']) ?>">
              <button class="linkbtn danger" type="submit">ย้อนกลับ</button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<div class="cols" style="margin-top:20px">
  <div class="wide">
    <section class="card">
      <h2>สร้าง migration ใหม่</h2>
      <?php if (!$canWrite): ?>
        <div class="msg warn">โฟลเดอร์ <code>migrations/</code> ไม่มีสิทธิ์เขียน — สร้างไฟล์ด้วยตนเองบนเซิร์ฟเวอร์ แล้วกลับมาหน้านี้เพื่อรัน</div>
      <?php endif; ?>
      <form method="post">
        <?= Csrf::field() ?><input type="hidden" name="act" value="create">
        <div class="grid2">
          <div><label class="f" for="slug">ชื่อไฟล์ (a-z, 0-9, _)</label><input id="slug" name="slug" pattern="[A-Za-z0-9_ \-]+" placeholder="add_subtitle_to_news" required></div>
          <div><label class="f" for="desc">คำอธิบาย</label><input id="desc" name="description" placeholder="เพิ่มคอลัมน์ subtitle ในตาราง news"></div>
        </div>
        <p class="hint">ระบบจะสร้างไฟล์ <code>migrations/<?= date('Ymd_His') ?>_ชื่อ.php</code> ที่มีเมธอด <code>up()</code> และ <code>down()</code> ให้เขียนคำสั่ง SQL
          — ควรใช้ <code>IF NOT EXISTS</code>/<code>IF EXISTS</code> เพื่อให้รันซ้ำได้ปลอดภัย</p>
        <button class="btn ghost" type="submit" <?= $canWrite ? '' : 'disabled' ?>>สร้างไฟล์</button>
      </form>
    </section>

    <section class="tbl">
      <div class="ttl">ประวัติการรัน <span class="mute small" style="font-weight:400">25 รายการล่าสุด</span></div>
      <div class="tw"><table>
        <thead><tr><th>เวลา</th><th>Migration</th><th>การทำงาน</th><th>ผล</th><th>โดย</th></tr></thead>
        <tbody>
        <?php if (!$history): ?><tr><td colspan="5" class="empty">ยังไม่มีประวัติ</td></tr><?php endif; ?>
        <?php foreach ($history as $h): ?>
          <tr><td class="small nowrap"><?= e(thai_date($h['created_at'], 'time')) ?></td><td><code><?= e($h['migration']) ?></code>
            <?= $h['message'] ? '<pre class="err">' . e($h['message']) . '</pre>' : '' ?></td>
            <td><?= $h['action'] === 'up' ? 'รัน (up)' : 'ย้อนกลับ (down)' ?></td>
            <td><?= $h['ok'] ? '<span class="pill ok">สำเร็จ</span>' : '<span class="pill bad">ล้มเหลว</span>' ?></td>
            <td class="small"><?= e($h['run_by']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  </div>

  <div class="narrow">
    <section class="card">
      <h2>ย้อนกลับ batch ล่าสุด</h2>
      <p class="small mute" style="margin-top:0">เรียก <code>down()</code> ของทุก migration ใน batch <?= $lastBatch ?: '—' ?> จากใหม่ไปเก่า
        <b style="color:var(--bad)">ข้อมูลในตารางที่ถูกลบจะหายถาวร</b> — สำรองฐานข้อมูลก่อนเสมอ</p>
      <form method="post" data-confirm="ยืนยันย้อนกลับ batch <?= $lastBatch ?>?">
        <?= Csrf::field() ?><input type="hidden" name="act" value="down_batch">
        <label class="f" for="cf">พิมพ์ <code>ROLLBACK</code> เพื่อยืนยัน</label>
        <input id="cf" name="confirm" autocomplete="off" required>
        <button class="btn danger block" type="submit" <?= $lastBatch ? '' : 'disabled' ?>>ย้อนกลับ batch <?= $lastBatch ?: '' ?></button>
      </form>
    </section>
    <section class="tbl">
      <div class="ttl">ตารางในฐานข้อมูล</div>
      <div class="tw"><table>
        <thead><tr><th>ตาราง</th><th class="r">แถว*</th><th class="r">KB</th></tr></thead>
        <tbody><?php foreach ($tables as $t): ?>
          <tr><td><code><?= e($t['t']) ?></code></td><td class="r"><?= number_format((int) $t['r']) ?></td><td class="r"><?= number_format((int) $t['kb']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <p class="small mute" style="padding:0 20px">* ค่าประมาณจาก information_schema</p>
    </section>
  </div>
</div>
<?php admin_footer();
