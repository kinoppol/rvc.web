<?php
declare(strict_types=1);

/* ผู้ช่วย AI — endpoint JSON ที่แผงผู้ช่วยในหน้าแก้ไขเนื้อหาเรียกใช้ (POST เท่านั้น ต้องมี CSRF token) */

require __DIR__ . '/_init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function ai_json(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$me = Auth::user();
if (!$me) {
    ai_json(401, ['ok' => false, 'error' => 'หมดเวลาเข้าสู่ระบบ กรุณาเข้าสู่ระบบใหม่']);
}
if (!is_post()) {
    ai_json(405, ['ok' => false, 'error' => 'ต้องใช้ POST']);
}
$t = $_POST['_csrf'] ?? '';
if (!is_string($t) || $t === '' || !hash_equals(Csrf::token(), $t)) {
    ai_json(400, ['ok' => false, 'error' => 'แบบฟอร์มหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่']);
}
if (!ai_can_use()) {
    ai_json(403, ['ok' => false, 'error' => 'บัญชีของคุณไม่มีสิทธิ์ใช้ผู้ช่วย AI']);
}

$task = input('task');
if (!isset(Ai::TASKS[$task])) {
    ai_json(422, ['ok' => false, 'error' => 'ไม่รู้จักงานที่สั่ง']);
}
$in = [
    'title'       => mb_substr(input('title'), 0, 500),
    'text'        => mb_substr(trim((string) ($_POST['text'] ?? '')), 0, 20000),
    'instruction' => mb_substr(input('instruction'), 0, 2000),
    'kind'        => input('kind'),
];
if (Ai::TASKS[$task][2] && $in['text'] === '' && $in['title'] === '') {
    ai_json(422, ['ok' => false, 'error' => $task === 'draft' ? 'กรุณาพิมพ์ประเด็นหรือข้อมูลของข่าวในช่องเนื้อหา หรือในช่องข้อความถึงผู้ช่วย' : 'ยังไม่มีข้อความให้ผู้ช่วยทำงาน — พิมพ์เนื้อหาก่อน หรือเลือกข้อความที่ต้องการ']);
}
if ($task === 'free' && $in['instruction'] === '') {
    ai_json(422, ['ok' => false, 'error' => 'กรุณาพิมพ์คำสั่งถึงผู้ช่วย']);
}

$model = Database::one('SELECT m.*, p.id AS pid FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id
                         WHERE m.id = ? AND m.is_enabled = 1 AND p.is_enabled = 1', [input_int('model')]);
if (!$model) {
    ai_json(422, ['ok' => false, 'error' => 'โมเดลนี้ถูกปิดใช้งานแล้ว กรุณาเลือกโมเดลอื่น']);
}

// จำกัดจำนวนครั้ง: 40 ครั้ง / 10 นาที / ผู้ใช้
$recent = (int) Database::value('SELECT COUNT(*) FROM ai_usage_log WHERE user_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE', [$me['id']]);
if ($recent >= 40) {
    ai_json(429, ['ok' => false, 'error' => 'ใช้งานถี่เกินไป กรุณารอสักครู่แล้วลองใหม่']);
}

session_write_close();   // ไม่ให้การรอคำตอบจาก AI ล็อก session ของแท็บอื่น
set_time_limit(180);

$provider = Ai::provider((int) $model['pid']);
$t0  = microtime(true);
$res = Ai::chat($provider, $model['model_id'], Ai::buildMessages($task, $in), [
    'temperature' => in_array($task, ['proofread', 'translate', 'excerpt'], true) ? 0.2 : 0.5,
    'max_tokens'  => 4000,
]);
$ms = (int) round((microtime(true) - $t0) * 1000);
Ai::logUsage((int) $me['id'], (int) $provider['id'], $model['model_id'], $task, $res, $ms);

if (!$res['ok']) {
    ai_json(502, ['ok' => false, 'error' => $res['error']]);
}
ai_json(200, [
    'ok'    => true,
    'text'  => $res['text'],
    'model' => $model['label'] ?: $model['model_id'],
    'ms'    => $ms,
    'usage' => $res['usage'],
]);
