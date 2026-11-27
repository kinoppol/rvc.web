<?php
declare(strict_types=1);

/* ข่าวจาก Facebook — เชื่อมเพจของวิทยาลัย แล้วดึงโพสต์มาเป็นข่าวหมวด "ฮอตอัพเดต" (อัตโนมัติ / กดเอง / ผ่านผู้ช่วย AI) */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

$act = input('act');
$back = url('admin/facebook.php');

if (is_post()) {
    if ($act === 'save') {
        $page  = trim(input('page_id'));
        if (preg_match('~facebook\.com/(?:profile\.php\?id=(\d+)|([A-Za-z0-9.\-]+))~', $page, $m)) {
            $page = $m[1] !== '' ? $m[1] : $m[2];   // วางลิงก์เพจมาทั้งลิงก์ได้
        }
        $token = trim((string) ($_POST['token'] ?? ''));
        if ($page === '' || !preg_match('~^[A-Za-z0-9.\-]{1,100}$~', $page)) {
            flash('err', 'รหัสเพจไม่ถูกต้อง — ใส่ตัวเลขรหัสเพจ หรือวางลิงก์เพจ Facebook');
            redirect($back);
        }
        if ($token !== '' || !empty($_POST['clear_token'])) {
            if ($token !== '') {
                try {
                    $name = Facebook::test($token, $page);
                } catch (Throwable $ex) {
                    flash('err', 'ยังไม่บันทึกโทเคน: ' . $ex->getMessage());
                    redirect($back);
                }
            }
            Settings::set('fb_token', $token !== '' ? Ai::encrypt($token) : '');
        }
        Settings::set('fb_page_id', $page);
        Settings::set('fb_publish', input('publish') === '1' ? '1' : '0');
        Settings::set('fb_auto', !empty($_POST['auto']) ? '1' : '0');
        Settings::set('fb_interval', (string) max(5, min(1440, input_int('interval', 15))));
        Settings::set('fb_limit', (string) max(1, min(25, input_int('limit', 10))));
        flash('ok', 'บันทึกการตั้งค่าแล้ว' . (!empty($name) ? ' · เชื่อมต่อเพจ “' . $name . '” สำเร็จ' : ''));
        redirect($back);
    }
    if ($act === 'test') {
        try {
            flash('ok', 'เชื่อมต่อเพจ “' . Facebook::test() . '” สำเร็จ');
        } catch (Throwable $ex) {
            flash('err', 'เชื่อมต่อไม่สำเร็จ: ' . $ex->getMessage());
        }
        redirect($back);
    }
    if ($act === 'sync') {
        set_time_limit(300);
        try {
            $r = Facebook::sync((int) $me['id']);
            flash($r['failed'] ? 'err' : 'ok', 'ดึงโพสต์ใหม่ ' . $r['added'] . ' โพสต์' . ($r['skipped'] ? ' (ข้าม ' . $r['skipped'] . ' โพสต์ที่นำเข้าแล้ว)' : '')
                . ($r['failed'] ? "\nนำเข้าไม่สำเร็จ:\n- " . implode("\n- ", $r['failed']) : ''));
        } catch (Throwable $ex) {
            flash('err', 'ดึงโพสต์ไม่สำเร็จ: ' . $ex->getMessage());
        }
        redirect($back);
    }
    if ($act === 'import') {
        set_time_limit(120);
        try {
            $p = Facebook::post(input('post'));
            $r = Facebook::import($p, (int) $me['id'], input('status') === 'draft' ? 'draft' : 'published');
            flash('ok', ($r['skipped'] ? 'โพสต์นี้นำเข้าไว้แล้ว' : 'นำเข้าเป็นข่าว #' . $r['id'] . ' แล้ว')
                . ($r['warnings'] ? "\n" . implode("\n", $r['warnings']) : ''));
        } catch (Throwable $ex) {
            flash('err', 'นำเข้าไม่สำเร็จ: ' . $ex->getMessage());
        }
        redirect($back);
    }
}

$token = Facebook::token();
$ok = Facebook::configured();
$last = Facebook::lastResult();
$posts = [];
$postErr = null;
if ($ok) {
    try {
        $posts = Facebook::posts(15);
    } catch (Throwable $ex) {
        $postErr = $ex->getMessage();
    }
}
$done = $posts ? Facebook::importedMap(array_column($posts, 'id')) : [];
$hotCount = (int) Database::value("SELECT COUNT(*) FROM news n WHERE n.category = 'hot' AND " . NEWS_PUBLIC);
$lastAt = (int) Settings::get('fb_last_sync', '0');

admin_header('ข่าวจาก Facebook', 'facebook', 'ข่าวจาก Facebook');
?>
<div class="head">
  <div><h1>ข่าวจาก Facebook</h1>
  <p>ดึงโพสต์จากเพจ Facebook ของวิทยาลัยมาเป็นข่าวหมวด “ฮอตอัพเดต” ซึ่งเป็นแท็บแรกของส่วนข่าวสารในหน้าแรก · ตอนนี้มีข่าวฮอตอัพเดต <?= $hotCount ?> ข่าว</p></div>
  <div class="acts">
    <?php if ($ok): ?>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="test"><button class="btn ghost" type="submit">ทดสอบการเชื่อมต่อ</button></form>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="sync"><button class="btn" type="submit"><?= icon('imp', 18) ?> ดึงโพสต์ใหม่ตอนนี้</button></form>
    <?php endif; ?>
    <a class="btn ghost" href="<?= e(url('admin/news.php', ['category' => 'hot'])) ?>">ข่าวฮอตอัพเดตทั้งหมด</a>
  </div>
</div>

<?php if ($ok): ?>
<div class="msg <?= isset($last['error']) || !empty($last['failed']) ? 'err' : 'ok' ?>" role="status" style="white-space:normal">
  <?php if (!$last): ?>ยังไม่เคยดึงโพสต์
  <?php elseif (isset($last['error'])): ?>ดึงครั้งล่าสุด <?= e(thai_date((int) $last['at'], 'time')) ?> ไม่สำเร็จ: <?= e($last['error']) ?>
  <?php else: ?>ดึงครั้งล่าสุด <?= e(thai_date((int) $last['at'], 'time')) ?> · ได้โพสต์ใหม่ <?= (int) $last['added'] ?> โพสต์<?= !empty($last['failed']) ? ' · ไม่สำเร็จ ' . count($last['failed']) . ' โพสต์' : '' ?><?php endif; ?>
  <?php if (Settings::get('fb_auto') === '1'): ?> · ดึงอัตโนมัติทุก <?= Facebook::interval() ?> นาที<?= $lastAt ? ' (รอบถัดไปหลัง ' . e(date('H:i', $lastAt + Facebook::interval() * 60)) . ' น. เมื่อมีผู้เข้าชมหน้าแรก)' : '' ?><?php else: ?> · ปิดการดึงอัตโนมัติอยู่<?php endif; ?>
</div>
<?php endif; ?>

<div class="cols">
  <div class="wide">
    <section class="tbl" style="margin-top:0">
      <div class="ttl">โพสต์ล่าสุดบนเพจ</div>
      <?php if (!$ok): ?>
        <p class="empty" style="padding:24px">ยังไม่ได้เชื่อมต่อเพจ — ตั้งค่า Page Access Token ด้านขวาก่อน<br>ระหว่างนี้ผู้ดูแลยังวางข้อความโพสต์ให้ผู้ช่วย AI สร้างเป็นข่าวฮอตอัพเดตได้</p>
      <?php elseif ($postErr): ?>
        <p class="empty" style="padding:24px;color:var(--err)"><?= e($postErr) ?></p>
      <?php elseif (!$posts): ?>
        <p class="empty" style="padding:24px">ไม่พบโพสต์บนเพจ</p>
      <?php else: ?>
      <div class="tw"><table style="min-width:640px">
        <thead><tr><th>โพสต์</th><th style="width:130px">วันที่</th><th style="width:150px"></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): $nid = $done[$p['id']] ?? null; ?>
          <tr class="row">
            <td><div class="slide-row">
              <div class="slide-th fb-th" style="<?= $p['images'] ? 'background-image:url(' . e($p['images'][0]) . ')' : '' ?>"><?= $p['images'] ? '' : 'ข้อความ' ?></div>
              <div><b><?= e($p['title']) ?></b>
                <div class="small mute"><?= e(str_limit($p['message'], 140)) ?></div>
                <div class="small mute"><?= count($p['images']) ? count($p['images']) . ' ภาพ · ' : '' ?><?= $p['video'] ? 'วิดีโอ · ' : '' ?><a href="<?= e($p['permalink']) ?>" target="_blank" rel="noopener">เปิดบน Facebook</a></div></div>
            </div></td>
            <td class="mute small"><?= e(thai_date($p['date'], 'time')) ?></td>
            <td class="r">
              <?php if ($nid): ?>
                <a class="linkbtn" href="<?= e(url('admin/news.php', ['act' => 'edit', 'id' => $nid])) ?>">นำเข้าแล้ว · ข่าว #<?= $nid ?></a>
              <?php else: ?>
                <div class="acts-cell">
                <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="import"><input type="hidden" name="post" value="<?= e($p['id']) ?>"><input type="hidden" name="status" value="published"><button class="linkbtn" type="submit">นำเข้าและเผยแพร่</button></form>
                <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="import"><input type="hidden" name="post" value="<?= e($p['id']) ?>"><input type="hidden" name="status" value="draft"><button class="linkbtn" type="submit">เป็นร่าง</button></form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <div class="hint" style="margin-top:10px">สั่งผู้ช่วย AI ได้ เช่น “ดึงโพสต์ใหม่จาก Facebook มาเป็นข่าวฮอตอัพเดต” — ผู้ช่วยจะเรียบเรียงหัวข้อข่าวให้ แล้วให้กดยืนยันก่อนนำเข้า</div>
  </div>

  <aside class="narrow">
    <form method="post" class="card">
      <?= Csrf::field() ?><input type="hidden" name="act" value="save">
      <h2><span class="num">1</span>การเชื่อมต่อเพจ</h2>
      <label class="f" for="pid">รหัสเพจหรือลิงก์เพจ</label>
      <input id="pid" name="page_id" value="<?= e(Facebook::pageId()) ?>" required spellcheck="false">
      <label class="f" for="tok">Page Access Token</label>
      <input id="tok" name="token" type="password" value="" autocomplete="new-password" spellcheck="false"
        placeholder="<?= $token !== '' ? e('มีอยู่แล้ว ' . Ai::maskKey($token) . ' — เว้นว่างเพื่อใช้ค่าเดิม') : 'วางโทเคนของเพจ' ?>">
      <?php if ($token !== ''): ?><label class="chk small"><input type="checkbox" name="clear_token" value="1"> ลบโทเคนที่บันทึกไว้</label><?php endif; ?>
      <div class="hint">เก็บแบบเข้ารหัส · ระบบทดสอบโทเคนกับเพจก่อนบันทึก</div>

      <h2 style="margin-top:22px"><span class="num">2</span>การนำเข้า</h2>
      <label class="f" for="pub">ข่าวที่ดึงมา</label>
      <select id="pub" name="publish">
        <option value="1" <?= Facebook::publishNow() ? 'selected' : '' ?>>เผยแพร่ทันที</option>
        <option value="0" <?= Facebook::publishNow() ? '' : 'selected' ?>>บันทึกเป็นร่าง ให้ตรวจก่อนเผยแพร่</option>
      </select>
      <label class="chk" style="margin-top:14px;font-weight:600"><input type="checkbox" name="auto" value="1" <?= Settings::get('fb_auto') === '1' ? 'checked' : '' ?> style="accent-color:var(--ok)"> ดึงโพสต์ใหม่อัตโนมัติ</label>
      <label class="f" for="iv">ทุก ๆ</label>
      <select id="iv" name="interval">
        <?php foreach ([5 => '5 นาที', 10 => '10 นาที', 15 => '15 นาที', 30 => '30 นาที', 60 => '1 ชั่วโมง', 180 => '3 ชั่วโมง'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= Facebook::interval() === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <label class="f" for="lim">ตรวจโพสต์ล่าสุดครั้งละ</label>
      <input id="lim" name="limit" type="number" min="1" max="25" value="<?= Facebook::limit() ?>">
      <div class="hint">การดึงอัตโนมัติเริ่มเมื่อมีผู้เข้าชมหน้าแรก (ไม่ทำให้หน้าเว็บช้า) หรือตั้ง cron ให้รัน <code>php cron/facebook_sync.php</code></div>
      <p style="margin:16px 0 0"><button class="btn" type="submit">บันทึก</button></p>
    </form>

    <details class="card" <?= $ok ? '' : 'open' ?>>
      <summary><b>วิธีขอ Page Access Token</b></summary>
      <ol class="small" style="padding-left:18px;line-height:1.7">
        <li>ใช้บัญชีที่เป็นผู้ดูแลเพจ เข้า <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com</a> สร้างแอปประเภท “Business” (ไม่ต้องส่งตรวจ เพราะอ่านเพจของตนเอง)</li>
        <li>เปิด <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> เลือกแอปนั้น เพิ่มสิทธิ์ <code>pages_show_list</code> และ <code>pages_read_engagement</code> แล้วกด Generate Access Token</li>
        <li>ที่ช่อง “User or Page” เลือกเพจของวิทยาลัย จะได้ Page Access Token</li>
        <li>นำไปขยายอายุที่ <a href="https://developers.facebook.com/tools/debug/accesstoken/" target="_blank" rel="noopener">Access Token Debugger</a> → “Extend Access Token” (โทเคนเพจจากโทเคนผู้ใช้อายุยาวจะไม่หมดอายุ)</li>
        <li>วางโทเคนในช่องด้านบนแล้วกดบันทึก</li>
      </ol>
    </details>
  </aside>
</div>
<div style="height:28px"></div>
<?php admin_footer();
