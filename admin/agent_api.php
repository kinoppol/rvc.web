<?php
declare(strict_types=1);

/* ผู้ช่วย AI สั่งงาน (ปุ่มลอยมุมขวาล่าง) — endpoint JSON (POST + CSRF)
 *   action=chat     ส่งบทสนทนาให้โมเดล → คำตอบ + ข้อเสนอที่รอยืนยัน
 *   action=confirm  ผู้ใช้กดยืนยันข้อเสนอ → ดำเนินการจริง */

require __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function agent_json(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$me = Auth::user();
if (!$me) {
    agent_json(401, ['ok' => false, 'error' => 'หมดเวลาเข้าสู่ระบบ กรุณาเข้าสู่ระบบใหม่']);
}
if (!is_post()) {
    agent_json(405, ['ok' => false, 'error' => 'ต้องใช้ POST']);
}
$t = $_POST['_csrf'] ?? '';
if (!is_string($t) || $t === '' || !hash_equals(Csrf::token(), $t)) {
    agent_json(400, ['ok' => false, 'error' => 'หน้าเว็บหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่']);
}
if (!ai_can_use()) {
    agent_json(403, ['ok' => false, 'error' => 'บัญชีของคุณไม่มีสิทธิ์ใช้ผู้ช่วย AI']);
}

$action = input('action');

if ($action === 'confirm') {
    try {
        $r = Agent::execute((string) ($_POST['token'] ?? ''), (string) ($_POST['sig'] ?? ''), $me);
        log_error('agent: user #' . $me['id'] . ' ' . ($r['message'] ?? ''));   // บันทึกการกระทำไว้ตรวจย้อนหลัง
        agent_json(200, ['ok' => true] + $r);
    } catch (InvalidArgumentException | RuntimeException $ex) {
        agent_json(422, ['ok' => false, 'error' => $ex->getMessage()]);
    }
}

if ($action !== 'chat') {
    agent_json(422, ['ok' => false, 'error' => 'ไม่รู้จักคำสั่ง']);
}

$history = json_decode((string) ($_POST['messages'] ?? ''), true);
if (!is_array($history) || !$history) {
    agent_json(422, ['ok' => false, 'error' => 'กรุณาพิมพ์ข้อความถึงผู้ช่วย']);
}
$model = Database::one('SELECT m.*, p.id AS pid FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id
                         WHERE m.id = ? AND m.is_enabled = 1 AND p.is_enabled = 1', [input_int('model')]);
if (!$model) {
    agent_json(422, ['ok' => false, 'error' => 'โมเดลนี้ถูกปิดใช้งานแล้ว กรุณาเลือกโมเดลอื่น']);
}
$recent = (int) Database::value('SELECT COUNT(*) FROM ai_usage_log WHERE user_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE', [$me['id']]);
if ($recent >= 40) {
    agent_json(429, ['ok' => false, 'error' => 'ใช้งานถี่เกินไป กรุณารอสักครู่แล้วลองใหม่']);
}

// ลายเซ็นข้อเสนอใช้ session id + CSRF token ที่อ่านไว้แล้ว — ปิด session ได้เลยเพื่อไม่ให้แท็บอื่นค้างระหว่างรอ AI
Csrf::token();
session_write_close();
set_time_limit(300);

$provider = Ai::provider((int) $model['pid']);
$t0  = microtime(true);
$res = Agent::run($provider, $model['model_id'], $history, $me);
$ms  = (int) round((microtime(true) - $t0) * 1000);
Ai::logUsage((int) $me['id'], (int) $provider['id'], $model['model_id'], 'agent', $res, $ms);

if (!$res['ok']) {
    agent_json(502, ['ok' => false, 'error' => $res['error']]);
}
agent_json(200, [
    'ok'        => true,
    'reply'     => $res['reply'],
    'proposals' => $res['proposals'],
    'links'     => $res['links'],
    'model'     => $model['label'] ?: $model['model_id'],
    'ms'        => $ms,
]);
