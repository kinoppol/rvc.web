<?php
declare(strict_types=1);

/* ผู้ช่วย AI — เชื่อมต่อผู้ให้บริการ API ภายนอกได้หลายราย ทดสอบการเชื่อมต่อ ดึงรายชื่อโมเดล และเลือกโมเดลที่เปิดใช้งาน */

require __DIR__ . '/_init.php';
$me = Auth::require('admin');
Csrf::verify();

if (!Database::tableExists(Database::pdo(), 'ai_providers')) {
    admin_header('ผู้ช่วย AI', 'ai');
    echo '<div class="head"><div><h1>ผู้ช่วย AI</h1></div></div><div class="msg warn">ยังไม่ได้สร้างตารางของผู้ช่วย AI — ไปที่เมนู <a href="' . e(url('admin/migrations.php')) . '">Migrations</a> แล้วกดติดตั้ง migration ที่รออยู่</div>';
    admin_footer();
    exit;
}

$act = input('act');
$id  = input_int('id');
$p   = $id ? Ai::provider($id) : null;
if ($id && !$p) {
    abort(404, 'ไม่พบผู้ให้บริการนี้');
}

/* ------------------------------ บันทึก ------------------------------ */
if (is_post()) {
    switch ($act) {
        case 'save':
            $preset = array_key_exists(input('preset'), Ai::PRESETS) ? input('preset') : 'custom';
            $data = [
                'name'        => mb_substr(input('name'), 0, 120) ?: Ai::PRESETS[$preset]['name'],
                'preset'      => $preset,
                'base_url'    => rtrim(mb_substr(input('base_url'), 0, 500), '/'),
                'auth_header' => preg_replace('/[^A-Za-z0-9\-]/', '', input('auth_header')) ?: 'Authorization',
                'auth_prefix' => mb_substr(preg_replace('/[\r\n]/', '', (string) ($_POST['auth_prefix'] ?? 'Bearer ')) ?? '', 0, 30),
                'models_path' => '/' . ltrim(mb_substr(input('models_path') ?: '/models', 0, 120), '/'),
                'chat_path'   => '/' . ltrim(mb_substr(input('chat_path') ?: '/chat/completions', 0, 120), '/'),
                'is_enabled'  => (int) !empty($_POST['is_enabled']),
            ];
            $key = trim((string) ($_POST['api_key'] ?? ''));
            if ($key !== '') {
                $data['api_key'] = Ai::encrypt($key);
            } elseif (!empty($_POST['remove_key'])) {
                $data['api_key'] = null;
            }
            if (!preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $data['base_url'])) {
                flash('err', 'Base URL ต้องขึ้นต้นด้วย http:// หรือ https:// เช่น https://openrouter.ai/api/v1');
                redirect(url('admin/ai.php', ['act' => $p ? 'edit' : 'new', 'id' => $id ?: null, 'preset' => $p ? null : $preset]));
            }
            if ($p) {
                Database::update('ai_providers', $data, $id);
            } else {
                $id = Database::insert('ai_providers', $data);
            }
            if (input('then') === 'test') {
                $r = Ai::testAndSync($id);
                flash($r['ok'] ? 'ok' : 'err', 'บันทึกแล้ว · ทดสอบการเชื่อมต่อ: ' . ($r['ok'] ? 'สำเร็จ — ' : 'ไม่ผ่าน — ') . $r['message']);
                redirect(url('admin/ai.php', $r['ok'] && $r['total'] ? ['act' => 'models', 'id' => $id] : ['act' => 'edit', 'id' => $id]));
            }
            flash('ok', 'บันทึกผู้ให้บริการ "' . $data['name'] . '" แล้ว');
            redirect(url('admin/ai.php'));

        case 'delete':
            if ($p) {
                Database::delete('ai_providers', $id);
                flash('ok', 'ลบผู้ให้บริการ "' . $p['name'] . '" และโมเดลทั้งหมดของผู้ให้บริการนี้แล้ว');
            }
            redirect(url('admin/ai.php'));

        case 'test':
            if ($p) {
                $r = Ai::testAndSync($id);
                flash($r['ok'] ? 'ok' : 'err', $p['name'] . ': ' . ($r['ok'] ? 'เชื่อมต่อสำเร็จ — ' : 'เชื่อมต่อไม่ผ่าน — ') . $r['message']);
            }
            redirect(input('back') === 'models' ? url('admin/ai.php', ['act' => 'models', 'id' => $id]) : url('admin/ai.php'));

        case 'models_save':
            if ($p) {
                $on = array_values(array_filter(array_map('intval', (array) ($_POST['enabled'] ?? []))));
                $visible = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['visible'] ?? '')))));
                $pdo = Database::pdo();
                $pdo->beginTransaction();
                // ปรับเฉพาะโมเดลที่แสดงอยู่ในหน้าจอ (เมื่อกรองไว้ จะไม่ไปปิดโมเดลที่ถูกซ่อน)
                if ($visible) {
                    $in = implode(',', $visible);
                    $pdo->prepare("UPDATE ai_models SET is_enabled = 0 WHERE provider_id = ? AND id IN ($in)")->execute([$id]);
                }
                if ($on) {
                    $in = implode(',', $on);
                    $pdo->prepare("UPDATE ai_models SET is_enabled = 1 WHERE provider_id = ? AND id IN ($in)")->execute([$id]);
                }
                $def = input_int('default');
                if ($def && Database::value('SELECT id FROM ai_models WHERE id = ? AND provider_id = ? AND is_enabled = 1', [$def, $id])) {
                    $pdo->prepare('UPDATE ai_models SET is_default = (id = ?)')->execute([$def]);
                }
                $pdo->exec('UPDATE ai_models SET is_default = 0 WHERE is_enabled = 0');
                $pdo->commit();
                $n = (int) Database::value('SELECT COUNT(*) FROM ai_models WHERE provider_id = ? AND is_enabled = 1', [$id]);
                flash('ok', 'บันทึกแล้ว · เปิดใช้งาน ' . $n . ' โมเดลของ ' . $p['name']);
            }
            redirect(url('admin/ai.php', ['act' => 'models', 'id' => $id, 'q' => input('q') ?: null, 'show' => input('show') ?: null]));

        case 'model_add':
            if ($p) {
                $mid = mb_substr(trim(input('model_id')), 0, 190);
                if ($mid === '' || preg_match('/\s/', $mid)) {
                    flash('err', 'กรุณากรอกชื่อโมเดล (ไม่มีช่องว่าง) ตามที่ผู้ให้บริการกำหนด');
                } else {
                    Database::run('INSERT INTO ai_models (provider_id, model_id, label, is_manual, is_enabled) VALUES (?,?,?,1,1)
                                   ON DUPLICATE KEY UPDATE is_enabled = 1, label = IF(VALUES(label) <> \'\', VALUES(label), label)',
                        [$id, $mid, mb_substr(input('label'), 0, 190)]);
                    flash('ok', 'เพิ่มและเปิดใช้งานโมเดล ' . $mid . ' แล้ว — กด "ทดสอบ" เพื่อตรวจว่าใช้งานได้');
                }
            }
            redirect(url('admin/ai.php', ['act' => 'models', 'id' => $id]));

        case 'model_delete':
            Database::run('DELETE FROM ai_models WHERE id = ? AND provider_id = ?', [input_int('model'), $id]);
            flash('ok', 'ลบโมเดลแล้ว');
            redirect(url('admin/ai.php', ['act' => 'models', 'id' => $id]));

        case 'model_test':
            $m = Database::one('SELECT * FROM ai_models WHERE id = ? AND provider_id = ?', [input_int('model'), $id]);
            if ($p && $m) {
                $t0 = microtime(true);
                $r = Ai::chat($p, $m['model_id'], [['role' => 'user', 'content' => 'สวัสดี ตอบกลับสั้นๆ ไม่เกิน 1 ประโยคว่าคุณพร้อมช่วยงานหรือไม่']], ['max_tokens' => 400, 'timeout' => 60]);
                $ms = (int) round((microtime(true) - $t0) * 1000);
                Ai::logUsage((int) $me['id'], $id, $m['model_id'], 'test', $r, $ms);
                Database::run('UPDATE ai_models SET is_available = ? WHERE id = ?', [$r['ok'] ? 1 : (int) $m['is_available'], $m['id']]);
                flash($r['ok'] ? 'ok' : 'err', $m['model_id'] . ($r['ok']
                    ? ' ตอบกลับใน ' . number_format($ms / 1000, 1) . ' วินาที: "' . str_limit($r['text'], 160) . '"'
                    : ' ใช้งานไม่ได้: ' . $r['error']));
            }
            redirect(url('admin/ai.php', ['act' => 'models', 'id' => $id, 'q' => input('q') ?: null, 'show' => input('show') ?: null]));

        case 'prompt_save':
            $sp = trim((string) ($_POST['system_prompt'] ?? ''));
            Settings::set('ai_system_prompt', !empty($_POST['reset']) || $sp === Ai::DEFAULT_SYSTEM_PROMPT ? '' : mb_substr($sp, 0, 4000));
            Settings::set('ai_allow_editors', (string) (int) !empty($_POST['allow_editors']));
            flash('ok', !empty($_POST['reset']) ? 'คืนค่าคำสั่งระบบเป็นค่าเริ่มต้นแล้ว' : 'บันทึกการตั้งค่าผู้ช่วยแล้ว');
            redirect(url('admin/ai.php'));
    }
    redirect(url('admin/ai.php'));
}

/* ------------------------------ ฟอร์มผู้ให้บริการ ------------------------------ */
if ($act === 'new' || ($act === 'edit' && $p)) {
    $preset = $p['preset'] ?? (array_key_exists(input('preset'), Ai::PRESETS) ? input('preset') : 'openrouter');
    $f = $p ?? [
        'id' => 0, 'name' => Ai::PRESETS[$preset]['name'], 'preset' => $preset, 'base_url' => Ai::PRESETS[$preset]['base_url'],
        'api_key' => null, 'auth_header' => 'Authorization', 'auth_prefix' => 'Bearer ', 'models_path' => '/models',
        'chat_path' => '/chat/completions', 'is_enabled' => 1, 'last_test_at' => null,
    ];
    $curKey = Ai::decrypt($f['api_key']);
    admin_header($f['id'] ? 'แก้ไขผู้ให้บริการ AI' : 'เพิ่มผู้ให้บริการ AI', 'ai');
    ?>
    <form method="post" id="ai-provider-form" autocomplete="off">
    <?= Csrf::field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/ai.php')) ?>">‹ กลับไปผู้ช่วย AI</a><h1><?= $f['id'] ? e($f['name']) : 'เพิ่มผู้ให้บริการ AI' ?></h1>
        <p>รองรับผู้ให้บริการที่ใช้รูปแบบ OpenAI-compatible API</p></div>
      <div class="acts">
        <button class="btn ghost" type="submit" name="then" value="">บันทึก</button>
        <button class="btn" type="submit" name="then" value="test">บันทึกและทดสอบการเชื่อมต่อ</button>
      </div>
    </div>
    <?php if ($f['last_test_at']): ?>
      <div class="msg <?= $f['last_test_ok'] ? 'ok' : 'err' ?>">ทดสอบล่าสุด <?= e(thai_date($f['last_test_at'], 'time')) ?> · <?= e($f['last_test_msg']) ?></div>
    <?php endif; ?>
    <div class="cols">
      <section class="card wide" style="display:block">
        <label class="f" for="preset">ผู้ให้บริการ</label>
        <select id="preset" name="preset">
          <?php foreach (Ai::PRESETS as $k => $pr): ?>
            <option value="<?= e($k) ?>" <?= $f['preset'] === $k ? 'selected' : '' ?> data-name="<?= e($pr['name']) ?>" data-url="<?= e($pr['base_url']) ?>" data-note="<?= e($pr['note']) ?>" data-key-url="<?= e($pr['key_url'] ?? '') ?>"><?= e($pr['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="preset-note"><?= e(Ai::PRESETS[$f['preset']]['note'] ?? '') ?></div>

        <label class="f" for="name">ชื่อที่แสดง</label>
        <input id="name" name="name" value="<?= e($f['name']) ?>" maxlength="120" required>

        <label class="f" for="base_url">Base URL</label>
        <input id="base_url" name="base_url" type="url" value="<?= e($f['base_url']) ?>" placeholder="https://example.com/v1" required>
        <div class="hint">ระบบจะเรียก <code id="u-models"><?= e($f['base_url'] . $f['models_path']) ?></code> และ <code id="u-chat"><?= e($f['base_url'] . $f['chat_path']) ?></code></div>

        <label class="f" for="api_key">API key</label>
        <input id="api_key" name="api_key" type="password" value="" placeholder="<?= $curKey !== '' ? e('มีอยู่แล้ว ' . Ai::maskKey($curKey) . ' — เว้นว่างเพื่อใช้ค่าเดิม') : 'วาง API key ที่ได้จากผู้ให้บริการ' ?>" autocomplete="new-password" spellcheck="false">
        <div class="hint">เก็บแบบเข้ารหัสในฐานข้อมูล ไม่แสดงกลับบนหน้าจอ
          <a id="key-link" href="<?= e(Ai::PRESETS[$f['preset']]['key_url'] ?? '#') ?>" target="_blank" rel="noopener" <?= empty(Ai::PRESETS[$f['preset']]['key_url']) ? 'hidden' : '' ?>>· ขอ API key</a></div>
        <?php if ($curKey !== ''): ?><label class="chk"><input type="checkbox" name="remove_key" value="1"> ลบ API key ที่บันทึกไว้</label><?php endif; ?>

        <details style="margin-top:18px"><summary style="cursor:pointer;font-weight:600">ตั้งค่าขั้นสูง</summary>
          <div class="grid2" style="margin-top:8px">
            <div><label class="f" for="ah">ชื่อ header สำหรับ key</label><input id="ah" name="auth_header" value="<?= e($f['auth_header']) ?>"></div>
            <div><label class="f" for="ap">คำนำหน้า key <span class="opt">(รวมช่องว่าง)</span></label><input id="ap" name="auth_prefix" value="<?= e($f['auth_prefix']) ?>"></div>
            <div><label class="f" for="mp">path รายชื่อโมเดล</label><input id="mp" name="models_path" value="<?= e($f['models_path']) ?>"></div>
            <div><label class="f" for="cp">path สั่งงาน (chat)</label><input id="cp" name="chat_path" value="<?= e($f['chat_path']) ?>"></div>
          </div>
          <div class="hint">ค่าปกติ: <code>Authorization</code> + <code>Bearer </code> · บางรายใช้ <code>apikey</code> หรือ <code>x-api-key</code> โดยไม่มีคำนำหน้า</div>
        </details>
      </section>
      <aside class="narrow">
        <section class="card">
          <label class="chk"><input type="checkbox" name="is_enabled" value="1" <?= $f['is_enabled'] ? 'checked' : '' ?>> เปิดใช้งานผู้ให้บริการนี้</label>
          <p class="hint">เมื่อปิด โมเดลของผู้ให้บริการนี้จะไม่แสดงในผู้ช่วย AI</p>
        </section>
        <section class="card">
          <h2>ขั้นตอน</h2>
          <ol class="small" style="margin:0;padding-left:20px;line-height:1.9">
            <li>เลือกผู้ให้บริการ และวาง API key</li>
            <li>กด <b>บันทึกและทดสอบการเชื่อมต่อ</b> — ระบบจะดึงรายชื่อโมเดลที่ใช้ได้</li>
            <li>เลือกเปิดใช้งานโมเดลที่ต้องการ (ได้หลายโมเดล)</li>
            <li>ใช้ปุ่ม <b>ผู้ช่วย AI</b> ในหน้าเขียนข่าว/หน้าเว็บ/ป๊อปอัพ</li>
          </ol>
        </section>
        <?php if ($f['id']): ?>
        <section class="card">
          <a class="btn ghost sm" href="<?= e(url('admin/ai.php', ['act' => 'models', 'id' => $f['id']])) ?>">จัดการโมเดล</a>
          <p style="margin:12px 0 0"><button class="linkbtn danger" type="submit" form="del-form">ลบผู้ให้บริการนี้</button></p>
        </section>
        <?php endif; ?>
      </aside>
    </div>
    </form>
    <?php if ($f['id']): ?><form id="del-form" method="post" data-confirm="ลบผู้ให้บริการนี้และโมเดลทั้งหมดที่ตั้งค่าไว้?"><?= Csrf::field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"></form><?php endif; ?>
    <script>
    (function () {
      var sel = document.getElementById('preset'), name = document.getElementById('name'), base = document.getElementById('base_url');
      var note = document.getElementById('preset-note'), link = document.getElementById('key-link');
      var mp = document.getElementById('mp'), cp = document.getElementById('cp');
      var prev = sel.options[sel.selectedIndex];
      function paths() {
        var b = base.value.replace(/\/+$/, '');
        document.getElementById('u-models').textContent = b + mp.value;
        document.getElementById('u-chat').textContent = b + cp.value;
      }
      sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        if (!name.value || name.value === prev.dataset.name) name.value = o.dataset.name;
        if (!base.value || base.value === prev.dataset.url || o.dataset.url) base.value = o.dataset.url || base.value;
        note.textContent = o.dataset.note;
        link.hidden = !o.dataset.keyUrl; link.href = o.dataset.keyUrl || '#';
        prev = o; paths();
      });
      [base, mp, cp].forEach(function (el) { el.addEventListener('input', paths); });
    })();
    </script>
    <?php
    admin_footer();
    exit;
}

/* ------------------------------ จัดการโมเดล ------------------------------ */
if ($act === 'models' && $p) {
    $q    = input('q');
    $show = input('show');                  // '' | on | free
    $where = ['provider_id = ?'];
    $params = [$id];
    if ($q !== '') {
        $where[] = '(model_id LIKE ? OR label LIKE ?)';
        $like = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if ($show === 'on') {
        $where[] = 'is_enabled = 1';
    } elseif ($show === 'free') {
        $where[] = 'is_free = 1';
    }
    $models = Database::all('SELECT * FROM ai_models WHERE ' . implode(' AND ', $where) . ' ORDER BY is_enabled DESC, is_manual DESC, model_id', $params);
    $cnt = Database::one('SELECT COUNT(*) t, SUM(is_enabled) e, SUM(is_free) f FROM ai_models WHERE provider_id = ?', [$id]) ?? [];
    admin_header('โมเดลของ ' . $p['name'], 'ai');
    ?>
    <div class="head">
      <div><a class="back" href="<?= e(url('admin/ai.php')) ?>">‹ กลับไปผู้ช่วย AI</a><h1>โมเดลของ <?= e($p['name']) ?></h1>
        <p>เลือกเปิดใช้งานได้หลายโมเดล · โมเดลที่เปิดจะให้เลือกในผู้ช่วย AI · <?= (int) ($cnt['t'] ?? 0) ?> โมเดล เปิดใช้ <?= (int) ($cnt['e'] ?? 0) ?></p></div>
      <div class="acts">
        <a class="btn ghost" href="<?= e(url('admin/ai.php', ['act' => 'edit', 'id' => $id])) ?>">ตั้งค่าการเชื่อมต่อ</a>
        <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="test"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="back" value="models">
          <button class="btn" type="submit">ทดสอบและดึงรายชื่อโมเดลใหม่</button></form>
      </div>
    </div>
    <?php if ($p['last_test_at']): ?>
      <div class="msg <?= $p['last_test_ok'] ? 'ok' : 'err' ?>">ทดสอบล่าสุด <?= e(thai_date($p['last_test_at'], 'time')) ?> · <?= e($p['last_test_msg']) ?></div>
    <?php endif; ?>
    <div class="cols">
      <section class="tbl wide" style="display:block">
        <form class="bar" method="get">
          <input type="hidden" name="act" value="models"><input type="hidden" name="id" value="<?= $id ?>">
          <label for="q" class="sr">ค้นหาโมเดล</label><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหา เช่น gpt, claude, gemini, qwen, typhoon">
          <label for="show" class="sr">แสดง</label>
          <select id="show" name="show">
            <option value="">ทุกโมเดล</option>
            <option value="on" <?= $show === 'on' ? 'selected' : '' ?>>เฉพาะที่เปิดใช้</option>
            <option value="free" <?= $show === 'free' ? 'selected' : '' ?>>เฉพาะโมเดลฟรี (<?= (int) ($cnt['f'] ?? 0) ?>)</option>
          </select>
          <button class="btn ghost" type="submit">กรอง</button>
        </form>
        <form method="post" id="models-form">
          <?= Csrf::field() ?><input type="hidden" name="act" value="models_save"><input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="show" value="<?= e($show) ?>">
          <input type="hidden" name="visible" value="<?= e(implode(',', array_column($models, 'id'))) ?>">
          <div class="ttl"><span><?= count($models) ?> โมเดล<?= $q !== '' || $show !== '' ? ' (กรองอยู่)' : '' ?></span>
            <span class="acts-cell"><button class="btn ghost sm" type="button" data-check="none">ยกเลิกทั้งหมด</button><button class="btn sm" type="submit">บันทึกโมเดลที่เลือก</button></span></div>
          <div class="tw" style="max-height:62vh;overflow-y:auto"><table style="min-width:640px">
            <thead><tr><th style="width:56px">เปิดใช้</th><th>โมเดล</th><th>บริบท</th><th title="โมเดลเริ่มต้นของผู้ช่วย">ค่าเริ่มต้น</th><th></th></tr></thead>
            <tbody>
            <?php if (!$models): ?><tr><td colspan="5" class="empty"><?= $q !== '' || $show !== '' ? 'ไม่พบโมเดลที่ตรงกับตัวกรอง' : 'ยังไม่มีรายชื่อโมเดล — กด "ทดสอบและดึงรายชื่อโมเดลใหม่" หรือเพิ่มชื่อโมเดลเองด้านขวา' ?></td></tr><?php endif; ?>
            <?php foreach ($models as $m): ?>
              <tr class="row">
                <td><input type="checkbox" name="enabled[]" value="<?= (int) $m['id'] ?>" <?= $m['is_enabled'] ? 'checked' : '' ?> aria-label="เปิดใช้ <?= e($m['model_id']) ?>" style="width:20px;height:20px;accent-color:var(--blue)"></td>
                <td><b style="word-break:break-all"><?= e($m['model_id']) ?></b>
                  <?= $m['is_free'] ? ' <span class="pill ok">ฟรี</span>' : '' ?><?= $m['is_manual'] ? ' <span class="pill info">เพิ่มเอง</span>' : '' ?><?= !$m['is_available'] ? ' <span class="pill bad">ไม่พบในผู้ให้บริการแล้ว</span>' : '' ?>
                  <?php if ($m['label'] !== ''): ?><div class="small mute"><?= e($m['label']) ?></div><?php endif; ?></td>
                <td class="small mute nowrap"><?= $m['context_length'] ? e(number_format((int) $m['context_length'])) . ' tokens' : '—' ?></td>
                <td><input type="radio" name="default" value="<?= (int) $m['id'] ?>" <?= $m['is_default'] ? 'checked' : '' ?> aria-label="ตั้ง <?= e($m['model_id']) ?> เป็นค่าเริ่มต้น" style="width:18px;height:18px;accent-color:var(--blue)"></td>
                <td class="r"><div class="acts-cell">
                  <button class="btn sm ghost" type="submit" form="mt-form" name="model" value="<?= (int) $m['id'] ?>" title="ส่งข้อความทดสอบ">ทดสอบ</button>
                  <?php if ($m['is_manual'] || !$m['is_available']): ?><button class="linkbtn danger small" type="submit" form="md-form" name="model" value="<?= (int) $m['id'] ?>">ลบ</button><?php endif; ?>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        </form>
        <form id="mt-form" method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="model_test"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="show" value="<?= e($show) ?>"></form>
        <form id="md-form" method="post" data-confirm="ลบโมเดลนี้ออกจากรายการ?"><?= Csrf::field() ?><input type="hidden" name="act" value="model_delete"><input type="hidden" name="id" value="<?= $id ?>"></form>
      </section>
      <aside class="narrow">
        <section class="card">
          <h2>เพิ่มโมเดลเอง</h2>
          <p class="hint" style="margin-top:0">สำหรับผู้ให้บริการที่ไม่มีรายการโมเดลให้ดึง หรือโมเดลที่ไม่อยู่ในรายการ</p>
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="model_add"><input type="hidden" name="id" value="<?= $id ?>">
            <label class="f" for="mid">ชื่อโมเดล (model id)</label><input id="mid" name="model_id" required spellcheck="false" placeholder="เช่น pathumma-thaillm-qwen3-8b-think-3.0.0">
            <label class="f" for="mlabel">ชื่อที่แสดง <span class="opt">(ไม่บังคับ)</span></label><input id="mlabel" name="label" maxlength="190">
            <button class="btn block" type="submit">เพิ่มโมเดล</button>
          </form>
        </section>
        <section class="card">
          <h2>คำแนะนำ</h2>
          <ul class="small" style="margin:0;padding-left:18px;line-height:1.8">
            <li>เปิดเฉพาะโมเดลที่ต้องการให้ผู้ดูแลเลือกใช้ เพื่อควบคุมค่าใช้จ่าย</li>
            <li>ภาษาไทยดี: Typhoon, OpenThaiGPT, Pathumma, Qwen, Gemini, GPT, Claude</li>
            <li>โมเดลที่มีป้าย <span class="pill ok">ฟรี</span> ใช้ได้โดยไม่เสียเครดิต แต่อาจจำกัดจำนวนครั้ง</li>
            <li>กด <b>ทดสอบ</b> เพื่อส่งข้อความตัวอย่างและตรวจว่าโมเดลตอบได้จริง</li>
          </ul>
        </section>
      </aside>
    </div>
    <script>
    document.querySelector('[data-check=none]').addEventListener('click', function () {
      document.querySelectorAll('#models-form input[name="enabled[]"]').forEach(function (c) { c.checked = false; });
    });
    </script>
    <?php
    admin_footer();
    exit;
}

/* ------------------------------ ภาพรวม ------------------------------ */
$providers = Database::all('SELECT p.*, COUNT(m.id) AS n_models, COALESCE(SUM(m.is_enabled), 0) AS n_enabled
                              FROM ai_providers p LEFT JOIN ai_models m ON m.provider_id = p.id
                             GROUP BY p.id ORDER BY p.is_enabled DESC, p.name');
$enabled = Ai::enabledModels();
$usage = Database::one('SELECT COUNT(*) n, COALESCE(SUM(ok), 0) ok, COALESCE(SUM(prompt_tokens + completion_tokens), 0) tk
                          FROM ai_usage_log WHERE created_at >= NOW() - INTERVAL 30 DAY') ?? [];
$recent = Database::all('SELECT l.*, u.display_name, u.username FROM ai_usage_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8');
$sp = Ai::systemPrompt();

admin_header('ผู้ช่วย AI', 'ai', 'ผู้ดูแลระบบ › ผู้ช่วย AI');
?>
<div class="head">
  <div><h1>ผู้ช่วย AI</h1><p>เชื่อมต่อ API ของผู้ให้บริการ AI ภายนอกได้หลายราย เพื่อช่วยเขียน ปรับสำนวน ตรวจคำผิด และสรุปเนื้อหาเว็บไซต์</p></div>
  <a class="btn big" href="<?= e(url('admin/ai.php', ['act' => 'new'])) ?>">+ เพิ่มผู้ให้บริการ</a>
</div>
<div class="stats">
  <div class="stat"><div class="l">ผู้ให้บริการที่เชื่อมต่อ</div><div class="v"><?= count(array_filter($providers, fn($r) => $r['is_enabled'])) ?></div></div>
  <div class="stat"><div class="l">โมเดลที่เปิดใช้งาน</div><div class="v"><?= count($enabled) ?></div></div>
  <div class="stat"><div class="l">เรียกใช้ 30 วันล่าสุด</div><div class="v"><?= (int) ($usage['n'] ?? 0) ?></div></div>
  <div class="stat"><div class="l">tokens 30 วันล่าสุด</div><div class="v"><?= e(number_format((int) ($usage['tk'] ?? 0))) ?></div></div>
</div>

<section class="tbl">
  <div class="ttl">ผู้ให้บริการ API</div>
  <div class="tw"><table style="min-width:760px">
    <thead><tr><th>ผู้ให้บริการ</th><th>สถานะการเชื่อมต่อ</th><th>โมเดล</th><th></th></tr></thead>
    <tbody>
    <?php if (!$providers): ?>
      <tr><td colspan="4" class="empty">ยังไม่ได้เชื่อมต่อผู้ให้บริการ — เลือกผู้ให้บริการเพื่อเริ่มต้น
        <div class="quick" style="justify-content:center">
          <?php foreach (Ai::PRESETS as $k => $pr): ?><a class="chip" style="display:inline-flex;align-items:center;text-decoration:none;color:inherit" href="<?= e(url('admin/ai.php', ['act' => 'new', 'preset' => $k])) ?>"><?= e($pr['name']) ?></a><?php endforeach; ?>
        </div></td></tr>
    <?php endif; ?>
    <?php foreach ($providers as $r): ?>
      <tr class="row">
        <td><b><?= e($r['name']) ?></b><?= $r['is_enabled'] ? '' : ' <span class="pill mute">ปิดใช้งาน</span>' ?>
          <div class="small mute" style="word-break:break-all"><?= e($r['base_url']) ?><?= $r['api_key'] ? '' : ' · <span style="color:var(--warn)">ยังไม่มี API key</span>' ?></div></td>
        <td style="max-width:340px"><?php if ($r['last_test_at'] === null): ?><span class="pill mute">ยังไม่ทดสอบ</span>
          <?php else: ?><span class="pill <?= $r['last_test_ok'] ? 'ok' : 'bad' ?>"><?= $r['last_test_ok'] ? 'เชื่อมต่อได้' : 'เชื่อมต่อไม่ได้' ?></span>
            <div class="small mute"><?= e(thai_date($r['last_test_at'], 'time')) ?> · <?= e(str_limit((string) $r['last_test_msg'], 120)) ?></div><?php endif; ?></td>
        <td class="nowrap"><b><?= (int) $r['n_enabled'] ?></b> <span class="mute">/ <?= (int) $r['n_models'] ?> เปิดใช้</span></td>
        <td class="r"><div class="acts-cell">
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="test"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn sm ghost" type="submit">ทดสอบ</button></form>
          <a class="btn sm" href="<?= e(url('admin/ai.php', ['act' => 'models', 'id' => $r['id']])) ?>">เลือกโมเดล</a>
          <a class="btn sm ghost" href="<?= e(url('admin/ai.php', ['act' => 'edit', 'id' => $r['id']])) ?>">แก้ไข</a></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<div class="cols" style="margin-top:20px">
  <section class="card wide" style="display:block">
    <h2>คำสั่งระบบ (System prompt)</h2>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="act" value="prompt_save">
      <label for="sp" class="sr">คำสั่งระบบ</label>
      <textarea id="sp" name="system_prompt" style="min-height:220px"><?= e($sp) ?></textarea>
      <div class="hint">กำหนดบทบาท น้ำเสียง และรูปแบบข้อความที่ผู้ช่วยตอบกลับ ใช้กับทุกโมเดล</div>
      <label class="chk top"><input type="checkbox" name="allow_editors" value="1" <?= Settings::get('ai_allow_editors', '1') === '1' ? 'checked' : '' ?>> อนุญาตให้ผู้เขียนข่าว (editor) ใช้ผู้ช่วย AI</label>
      <div class="quick"><button class="btn" type="submit">บันทึก</button>
        <button class="btn ghost" type="submit" name="reset" value="1" data-confirm-click="คืนค่าคำสั่งระบบเป็นค่าเริ่มต้น?">คืนค่าเริ่มต้น</button></div>
    </form>
  </section>
  <aside class="narrow">
    <section class="card">
      <h2>โมเดลที่พร้อมใช้</h2>
      <?php if (!$enabled): ?><p class="hint" style="margin:0">ยังไม่มีโมเดลที่เปิดใช้งาน</p><?php endif; ?>
      <ul class="small" style="margin:0;padding-left:18px;line-height:1.8">
        <?php foreach ($enabled as $m): ?><li><b style="word-break:break-all"><?= e($m['label'] ?: $m['model_id']) ?></b> <span class="mute">· <?= e($m['provider_name']) ?></span><?= $m['is_default'] ? ' <span class="pill info">ค่าเริ่มต้น</span>' : '' ?></li><?php endforeach; ?>
      </ul>
    </section>
    <section class="card">
      <h2>การใช้งานล่าสุด</h2>
      <?php if (!$recent): ?><p class="hint" style="margin:0">ยังไม่มีการใช้งาน</p><?php endif; ?>
      <ul class="small" style="margin:0;padding-left:0;list-style:none;display:grid;gap:8px">
        <?php foreach ($recent as $l): ?>
          <li><span class="pill <?= $l['ok'] ? 'ok' : 'bad' ?>"><?= e(Ai::TASKS[$l['task']][0] ?? $l['task']) ?></span>
            <span class="mute"><?= e(thai_date($l['created_at'], 'time')) ?> · <?= e($l['display_name'] ?: $l['username'] ?: '—') ?></span>
            <div style="word-break:break-all"><?= e($l['model_id']) ?><?= $l['ok'] ? ' · ' . number_format($l['prompt_tokens'] + $l['completion_tokens']) . ' tokens' : '' ?></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
<?php admin_footer();
