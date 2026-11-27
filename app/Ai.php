<?php
declare(strict_types=1);

/**
 * ผู้ช่วย AI — เชื่อมต่อผู้ให้บริการภายนอกที่ใช้รูปแบบ OpenAI-compatible API
 * (OpenRouter, ThaiLLM, Tokenme, Typhoon, OpenAI หรือกำหนดเอง) ได้หลายรายพร้อมกัน
 *
 *   ทดสอบการเชื่อมต่อ  → GET {base_url}{models_path}  (+ ตรวจ API key ถ้าผู้ให้บริการมี endpoint ให้)
 *   ดึงรายชื่อโมเดล     → บันทึกลง ai_models ให้ผู้ดูแลเลือกเปิดใช้ได้หลายโมเดล
 *   สั่งงาน            → POST {base_url}{chat_path}
 *
 * API key เก็บแบบเข้ารหัส (AES-256-GCM) ด้วยกุญแจใน config ('app' => ['secret' => ...]) หรือไฟล์ config/app.key
 */
final class Ai
{
    /** ค่าเริ่มต้นของผู้ให้บริการแต่ละราย — ผู้ดูแลแก้ Base URL ได้ทุกช่อง */
    public const PRESETS = [
        'openrouter' => [
            'name' => 'OpenRouter', 'base_url' => 'https://openrouter.ai/api/v1',
            'key_check' => '/key', 'key_url' => 'https://openrouter.ai/keys',
            'note' => 'รวมโมเดลจากหลายค่าย (GPT, Claude, Gemini, Llama, Qwen, Typhoon ฯลฯ) ด้วย API key เดียว · มีโมเดลฟรี (:free)',
        ],
        'thaillm' => [
            'name' => 'ThaiLLM', 'base_url' => 'https://thaillm.or.th/api/v1',
            'key_check' => null, 'key_url' => 'https://thaillm.or.th',
            'note' => 'โมเดลภาษาไทยแห่งชาติ (OpenThaiGPT, Typhoon-S, Pathumma, THaLLE) · ถ้าดึงรายชื่อโมเดลไม่ได้ ให้เพิ่มชื่อโมเดลเอง เช่น pathumma-thaillm-qwen3-8b-think-3.0.0',
        ],
        'tokenme' => [
            'name' => 'Tokenme', 'base_url' => 'https://api.tokenme.limited/api/v1',
            'key_check' => null, 'key_url' => 'https://tokenme.limited',
            'note' => 'ผู้ให้บริการในไทย · ถ้าดึงรายชื่อโมเดลไม่ได้ ให้เพิ่มชื่อโมเดลเอง เช่น deepseek-ai/deepseek-v4.1-flash-1017',
        ],
        'typhoon' => [
            'name' => 'Typhoon (SCB 10X)', 'base_url' => 'https://api.opentyphoon.ai/v1',
            'key_check' => null, 'key_url' => 'https://playground.opentyphoon.ai',
            'note' => 'โมเดลภาษาไทยจาก SCB 10X',
        ],
        'openai' => [
            'name' => 'OpenAI', 'base_url' => 'https://api.openai.com/v1',
            'key_check' => null, 'key_url' => 'https://platform.openai.com/api-keys',
            'note' => '',
        ],
        'custom' => [
            'name' => 'กำหนดเอง (OpenAI-compatible)', 'base_url' => '',
            'key_check' => null, 'key_url' => null,
            'note' => 'บริการใดก็ได้ที่รองรับ /v1/models และ /v1/chat/completions เช่น vLLM, Ollama, LM Studio',
        ],
    ];

    /** งานสำเร็จรูปของผู้ช่วย: [ชื่อปุ่ม, คำสั่ง, ต้องมีข้อความเดิมหรือไม่] */
    public const TASKS = [
        'import'    => ['เรียบเรียงจากเว็บเดิม', 'เรียบเรียงเนื้อหาที่ดึงมาจากเว็บไซต์เดิมใหม่ให้อ่านง่าย เป็นระเบียบ ตามรูปแบบของเว็บไซต์ใหม่ แก้คำผิดและข้อความที่ซ้ำ คงข้อเท็จจริง ชื่อ ตัวเลข วันที่ทั้งหมด และต้องคงลิงก์ทุกรายการในรูปแบบ [ข้อความ](ลิงก์) ไว้ครบโดยไม่แก้ไขลิงก์', true],
        'draft'     => ['ร่างจากประเด็น', 'เรียบเรียงเป็นข่าวประชาสัมพันธ์ของวิทยาลัยที่สมบูรณ์จากประเด็น/ข้อมูลที่ให้มา มีย่อหน้านำที่บอกว่าใคร ทำอะไร ที่ไหน เมื่อไร และใส่หัวข้อย่อยเมื่อเหมาะสม ห้ามแต่งข้อเท็จจริง ชื่อคน ตัวเลข หรือวันที่ที่ไม่มีในข้อมูล', true],
        'improve'   => ['ปรับสำนวน', 'ปรับสำนวนให้เป็นภาษาราชการที่สุภาพ อ่านง่าย เป็นธรรมชาติ แก้คำผิดและวรรคตอน คงเนื้อหาและข้อเท็จจริงเดิมทั้งหมด', true],
        'proofread' => ['ตรวจคำผิด', 'ตรวจและแก้เฉพาะคำผิด การสะกด การเว้นวรรค และเครื่องหมายวรรคตอน ห้ามเปลี่ยนสำนวนหรือเนื้อหา', true],
        'shorten'   => ['ย่อให้กระชับ', 'ย่อข้อความให้กระชับเหลือประมาณครึ่งหนึ่ง คงใจความสำคัญและข้อเท็จจริงไว้ครบ', true],
        'expand'    => ['ขยายความ', 'ขยายความให้ละเอียดขึ้นอย่างเหมาะสมกับเว็บไซต์ของสถานศึกษา โดยไม่แต่งข้อเท็จจริงใหม่ ถ้าต้องการข้อมูลเพิ่มให้ใส่ [ระบุ...] ไว้แทน', true],
        'excerpt'   => ['สรุปเป็นคำโปรย', 'สรุปเป็นคำโปรย 1–2 ประโยค ยาวไม่เกิน 200 ตัวอักษร ตอบเฉพาะคำโปรย', true],
        'titles'    => ['เสนอหัวข้อ', 'เสนอหัวข้อข่าว 5 แบบ สั้น กระชับ ชัดเจน ไม่เกิน 90 ตัวอักษร ตอบเป็นรายการขึ้นต้นด้วย "- " บรรทัดละหัวข้อ', true],
        'popup'     => ['ข้อความป๊อปอัพ', 'เขียนข้อความประกาศสั้นสำหรับป๊อปอัพหน้าเว็บ 1–2 ประโยค ไม่เกิน 300 ตัวอักษร', false],
        'translate' => ['แปลอังกฤษ', 'แปลเป็นภาษาอังกฤษที่เป็นทางการ คงรูปแบบหัวข้อย่อยและรายการเดิม', true],
        'free'      => ['คำสั่งอิสระ', '', false],
    ];

    public const DEFAULT_SYSTEM_PROMPT = "คุณคือผู้ช่วยเขียนเนื้อหาสำหรับเว็บไซต์ของสถานศึกษาอาชีวศึกษาในประเทศไทย ช่วยผู้ดูแลเว็บไซต์เขียน ปรับปรุง และตรวจทานข่าว ประกาศ และหน้าเว็บ\n"
        . "- ตอบเป็นภาษาไทย (เว้นแต่ถูกขอให้แปล) ใช้ภาษาสุภาพแบบราชการที่อ่านง่าย\n"
        . "- ตอบเฉพาะเนื้อหาที่นำไปใช้ได้ทันที ไม่ต้องมีคำเกริ่น คำอธิบาย หรือคำลงท้าย\n"
        . "- รูปแบบข้อความ: เว้นบรรทัดว่างระหว่างย่อหน้า · หัวข้อย่อยขึ้นต้นบรรทัดด้วย \"## \" · รายการขึ้นต้นด้วย \"- \" · ลิงก์เขียนแบบ [ข้อความ](ลิงก์) · ห้ามใช้ Markdown แบบอื่น เช่น **ตัวหนา** ตาราง หรือ #\n"
        . "- ถ้าข้อความที่ได้รับมีลิงก์หรือไฟล์แนบ ให้คงลิงก์นั้นไว้ทุกรายการโดยไม่แก้ไข\n"
        . "- ห้ามแต่งข้อเท็จจริง ชื่อบุคคล ตัวเลข วันที่ หรือสถานที่ที่ไม่มีในข้อมูลที่ได้รับ";

    private const CIPHER = 'aes-256-gcm';

    /* ------------------------------ เข้ารหัส API key ------------------------------ */

    private static function secret(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $cfg = (string) ($GLOBALS['config']['app']['secret'] ?? '');
        if ($cfg !== '') {
            return $key = hash('sha256', $cfg, true);
        }
        $file = BASE_PATH . '/config/app.key';
        $raw  = is_file($file) ? trim((string) file_get_contents($file)) : '';
        if ($raw === '') {
            $raw = bin2hex(random_bytes(32));
            if (!write_file_atomic($file, $raw)) {
                throw new RuntimeException('สร้างไฟล์กุญแจ config/app.key ไม่ได้ — ตรวจสิทธิ์เขียนของโฟลเดอร์ config/');
            }
        }
        return $key = hash('sha256', $raw, true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, self::CIPHER, self::secret(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new RuntimeException('เข้ารหัส API key ไม่สำเร็จ');
        }
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(?string $stored): string
    {
        if (!$stored || !str_starts_with($stored, 'v1:')) {
            return '';
        }
        $bin = base64_decode(substr($stored, 3), true);
        if ($bin === false || strlen($bin) < 29) {
            return '';
        }
        $pt = openssl_decrypt(substr($bin, 28), self::CIPHER, self::secret(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
        return $pt === false ? '' : $pt;
    }

    /** แสดง API key แบบปิดบัง เช่น sk-or-…a1b2 */
    public static function maskKey(string $key): string
    {
        if ($key === '') {
            return '';
        }
        return strlen($key) <= 10 ? str_repeat('•', 8) : substr($key, 0, 6) . '…' . substr($key, -4);
    }

    /* ------------------------------ ข้อมูล ------------------------------ */

    public static function provider(int $id): ?array
    {
        return Database::one('SELECT * FROM ai_providers WHERE id = ?', [$id]);
    }

    /** โมเดลที่เปิดใช้งาน (จากผู้ให้บริการที่เปิดอยู่) — โมเดลเริ่มต้นมาก่อน */
    public static function enabledModels(): array
    {
        try {
            return Database::all('SELECT m.id, m.model_id, m.label, m.is_default, m.is_free, p.id AS provider_id, p.name AS provider_name
                                    FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id
                                   WHERE m.is_enabled = 1 AND p.is_enabled = 1
                                   ORDER BY m.is_default DESC, p.name, COALESCE(NULLIF(m.label, \'\'), m.model_id)');
        } catch (Throwable) {
            return [];   // ยังไม่ได้รัน migration
        }
    }

    public static function systemPrompt(): string
    {
        return Settings::get('ai_system_prompt', self::DEFAULT_SYSTEM_PROMPT);
    }

    /* ------------------------------ HTTP ------------------------------ */

    /**
     * @return array{status:int, body:string, json:?array, error:?string}
     */
    private static function http(array $p, string $method, string $path, ?array $payload = null, int $timeout = 30): array
    {
        $url = rtrim((string) $p['base_url'], '/') . '/' . ltrim($path, '/');
        $headers = ['Accept: application/json', 'User-Agent: rvc-web-ai-assistant/1.0'];
        $key = self::decrypt($p['api_key'] ?? null);
        if ($key !== '') {
            $headers[] = ($p['auth_header'] ?: 'Authorization') . ': ' . ($p['auth_prefix'] ?? '') . $key;
        }
        if (($p['preset'] ?? '') === 'openrouter') {   // OpenRouter แนะนำให้ระบุแหล่งที่มา
            $headers[] = 'HTTP-Referer: ' . self::siteOrigin();
            $headers[] = 'X-Title: ' . preg_replace('/[^\x20-\x7E]/', '', Settings::get('site_name_en', 'College Website'));
        }
        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $res    = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err    = $res === false ? curl_error($ch) : null;
            curl_close($ch);
            $res = $res === false ? '' : (string) $res;
        } else {
            $ctx = stream_context_create(['http' => [
                'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
                'timeout' => $timeout, 'ignore_errors' => true, 'follow_location' => 0,
            ]]);
            $res    = @file_get_contents($url, false, $ctx);
            $status = 0;
            foreach ($http_response_header ?? [] as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) {
                    $status = (int) $m[1];
                }
            }
            $err = $res === false ? (error_get_last()['message'] ?? 'เชื่อมต่อไม่ได้') : null;
            $res = $res === false ? '' : $res;
        }
        $json = $res !== '' ? json_decode($res, true) : null;
        return ['status' => $status, 'body' => $res, 'json' => is_array($json) ? $json : null, 'error' => $err];
    }

    private static function siteOrigin(): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host  = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host . base_url() . '/';
    }

    /** แปลงผลลัพธ์ที่ผิดพลาดเป็นข้อความภาษาไทยที่เข้าใจได้ */
    private static function describeError(array $r): string
    {
        if ($r['error']) {
            return 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้: ' . $r['error'];
        }
        $msg = $r['json']['error']['message'] ?? $r['json']['error'] ?? $r['json']['message'] ?? $r['json']['detail'] ?? null;
        $msg = is_string($msg) ? $msg : ($msg !== null ? json_encode($msg, JSON_UNESCAPED_UNICODE) : str_limit(strip_tags($r['body']), 160));
        $why = match (true) {
            $r['status'] === 401, $r['status'] === 403 => 'API key ไม่ถูกต้องหรือไม่มีสิทธิ์',
            $r['status'] === 402 => 'เครดิตไม่พอ',
            $r['status'] === 404 => 'ไม่พบ endpoint — ตรวจ Base URL',
            $r['status'] === 429 => 'เรียกใช้ถี่เกินกำหนด (rate limit) — รอสักครู่แล้วลองใหม่',
            $r['status'] >= 500  => 'เซิร์ฟเวอร์ของผู้ให้บริการขัดข้อง',
            default => 'ผิดพลาด',
        };
        return 'HTTP ' . $r['status'] . ' · ' . $why . ($msg ? ' — ' . mb_substr($msg, 0, 240) : '');
    }

    /* ------------------------------ ทดสอบ / ดึงรายชื่อโมเดล ------------------------------ */

    /**
     * @return array{ok:bool, models:?list<array{id:string,label:string,context:?int,free:bool}>, message:string}
     *         models = null เมื่อผู้ให้บริการไม่มีรายการโมเดลให้ดึง
     */
    public static function fetchModels(array $p): array
    {
        $check = self::PRESETS[$p['preset']]['key_check'] ?? null;
        if ($check) {   // บางรายเปิด /models ให้ดูได้โดยไม่ต้องใช้ key จึงต้องตรวจ key แยก
            $r = self::http($p, 'GET', $check, null, 20);
            if ($r['status'] !== 200) {
                return ['ok' => false, 'models' => null, 'message' => self::describeError($r)];
            }
        }
        $r = self::http($p, 'GET', $p['models_path'] ?: '/models', null, 30);
        if ($r['status'] !== 200 || $r['json'] === null) {
            return ['ok' => false, 'models' => null, 'message' => $r['status'] === 200 ? 'ผลลัพธ์ไม่ใช่ JSON — ตรวจ Base URL' : self::describeError($r)];
        }
        $list = $r['json']['data'] ?? $r['json']['models'] ?? (array_is_list($r['json']) ? $r['json'] : []);
        $models = [];
        foreach ((array) $list as $m) {
            $id = is_string($m) ? $m : (string) ($m['id'] ?? $m['name'] ?? $m['model'] ?? '');
            if ($id === '' || mb_strlen($id) > 190) {
                continue;
            }
            $price = $m['pricing'] ?? null;
            $models[$id] = [
                'id'      => $id,
                'label'   => is_array($m) && !empty($m['name']) && $m['name'] !== $id ? mb_substr((string) $m['name'], 0, 190) : '',
                'context' => is_array($m) && isset($m['context_length']) ? (int) $m['context_length'] : null,
                'free'    => str_ends_with($id, ':free') || (is_array($price) && (float) ($price['prompt'] ?? 1) == 0 && (float) ($price['completion'] ?? 1) == 0),
            ];
        }
        return ['ok' => true, 'models' => array_values($models), 'message' => 'พบ ' . count($models) . ' โมเดล'];
    }

    /**
     * ทดสอบการเชื่อมต่อ: ดึงรายชื่อโมเดลและบันทึก — ถ้าผู้ให้บริการไม่มีรายการโมเดล
     * จะลองส่งข้อความสั้นๆ ด้วยโมเดลที่เพิ่มไว้เองแทน
     * @return array{ok:bool, message:string, added:int, total:int}
     */
    public static function testAndSync(int $providerId): array
    {
        $p = self::provider($providerId);
        if (!$p) {
            return ['ok' => false, 'message' => 'ไม่พบผู้ให้บริการ', 'added' => 0, 'total' => 0];
        }
        $res = self::fetchModels($p);
        $added = 0;
        if ($res['ok']) {
            $added = self::syncModels($providerId, $res['models']);
            $msg = $res['message'] . ($added ? ' (ใหม่ ' . $added . ')' : '');
        } else {
            $manual = Database::value('SELECT model_id FROM ai_models WHERE provider_id = ? ORDER BY is_enabled DESC, is_manual DESC, id LIMIT 1', [$providerId]);
            if ($manual) {
                $chat = self::chat($p, (string) $manual, [['role' => 'user', 'content' => 'ตอบคำว่า OK']], ['max_tokens' => 16, 'timeout' => 45]);
                $res['ok'] = $chat['ok'];
                $msg = $chat['ok']
                    ? 'ดึงรายชื่อโมเดลไม่ได้ แต่ทดสอบส่งข้อความด้วยโมเดล ' . $manual . ' สำเร็จ'
                    : 'ดึงรายชื่อโมเดลไม่ได้ (' . $res['message'] . ') และทดสอบโมเดล ' . $manual . ' ไม่ผ่าน: ' . $chat['error'];
            } else {
                $msg = $res['message'] . (preg_match('/HTTP 40[123] /', $res['message']) ? ''
                    : ' — ถ้าผู้ให้บริการไม่มีรายการโมเดลให้ดึง ให้เพิ่มชื่อโมเดลเองแล้วทดสอบอีกครั้ง');
            }
        }
        Database::run('UPDATE ai_providers SET last_test_at = NOW(), last_test_ok = ?, last_test_msg = ? WHERE id = ?',
            [$res['ok'] ? 1 : 0, mb_substr($msg, 0, 500), $providerId]);
        $total = (int) Database::value('SELECT COUNT(*) FROM ai_models WHERE provider_id = ?', [$providerId]);
        return ['ok' => $res['ok'], 'message' => $msg, 'added' => $added, 'total' => $total];
    }

    /** บันทึกรายชื่อโมเดล — คงค่าที่เปิดใช้งานไว้ และทำเครื่องหมายโมเดลที่หายไปจากผู้ให้บริการ */
    private static function syncModels(int $providerId, array $models): int
    {
        $pdo = Database::pdo();
        $existing = array_column(Database::all('SELECT id, model_id FROM ai_models WHERE provider_id = ?', [$providerId]), 'id', 'model_id');
        $added = 0;
        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare('INSERT INTO ai_models (provider_id, model_id, label, context_length, is_free) VALUES (?,?,?,?,?)');
            $upd = $pdo->prepare('UPDATE ai_models SET label = IF(is_manual = 1 AND label <> \'\', label, ?), context_length = ?, is_free = ?, is_available = 1 WHERE id = ?');
            $seen = [];
            foreach ($models as $m) {
                if (isset($existing[$m['id']])) {
                    $upd->execute([$m['label'], $m['context'], (int) $m['free'], $existing[$m['id']]]);
                } else {
                    $ins->execute([$providerId, $m['id'], $m['label'], $m['context'], (int) $m['free']]);
                    $added++;
                }
                $seen[$m['id']] = true;
            }
            $gone = array_diff_key($existing, $seen);
            if ($gone) {
                $ids = implode(',', array_map('intval', $gone));
                $pdo->exec("UPDATE ai_models SET is_available = 0 WHERE is_manual = 0 AND id IN ($ids)");
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        return $added;
    }

    /* ------------------------------ สั่งงาน ------------------------------ */

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool, text:string, error:?string, usage:array{prompt:int,completion:int}}
     */
    public static function chat(array $p, string $model, array $messages, array $opt = []): array
    {
        $payload = ['model' => $model, 'messages' => $messages, 'temperature' => $opt['temperature'] ?? 0.4];
        if (!empty($opt['max_tokens'])) {
            $payload['max_tokens'] = (int) $opt['max_tokens'];
        }
        $r = self::http($p, 'POST', $p['chat_path'] ?: '/chat/completions', $payload, (int) ($opt['timeout'] ?? 120));
        $usage = ['prompt' => (int) ($r['json']['usage']['prompt_tokens'] ?? 0), 'completion' => (int) ($r['json']['usage']['completion_tokens'] ?? 0)];
        if ($r['status'] !== 200 || $r['json'] === null || isset($r['json']['error'])) {
            return ['ok' => false, 'text' => '', 'error' => self::describeError($r), 'usage' => $usage];
        }
        $msg  = $r['json']['choices'][0]['message'] ?? [];
        $text = $msg['content'] ?? $r['json']['choices'][0]['text'] ?? '';
        if (is_array($text)) {   // บางรายส่งเป็นชิ้นส่วน [{type:text,text:...}]
            $text = implode('', array_map(fn($c) => is_array($c) ? (string) ($c['text'] ?? '') : (string) $c, $text));
        }
        $text = !empty($opt['raw'])   // raw: ไม่แปลงรูปแบบข้อความ (ผู้ช่วยสั่งงานตอบเป็น JSON)
            ? trim(preg_replace('~<think>.*?</think>~is', '', (string) $text) ?? (string) $text)
            : self::cleanOutput((string) $text);
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'error' => 'โมเดลไม่ได้ส่งข้อความกลับมา (อาจตอบเฉพาะส่วนความคิด หรือถูกตัดเพราะ max_tokens)', 'usage' => $usage];
        }
        return ['ok' => true, 'text' => $text, 'error' => null, 'usage' => $usage];
    }

    /** ตัดส่วน "ความคิด" ของโมเดลแบบ reasoning และ Markdown ที่หน้าเว็บไม่รองรับ */
    public static function cleanOutput(string $t): string
    {
        $t = preg_replace('~<think>.*?</think>~is', '', $t) ?? $t;
        $t = preg_replace('~^.*?</think>~is', '', $t) ?? $t;          // บางรุ่นไม่มีแท็กเปิด
        $t = preg_replace('~^```[a-z]*\n(.*)\n```$~is', '$1', trim($t)) ?? $t;
        $t = preg_replace('~\*\*(.+?)\*\*~u', '$1', $t) ?? $t;        // **ตัวหนา**
        $t = preg_replace('~^#{3,6}\s+~mu', '## ', $t) ?? $t;         // ### → ##
        $t = preg_replace('~^#\s+~mu', '## ', $t) ?? $t;
        $t = preg_replace('~^(\s*)[*•]\s+~mu', '$1- ', $t) ?? $t;     // * รายการ → - รายการ
        return trim($t);
    }

    /**
     * สร้างข้อความสั่งงานจากงานสำเร็จรูป
     * @param array{title?:string,text?:string,instruction?:string,kind?:string} $in
     */
    public static function buildMessages(string $task, array $in): array
    {
        [, $instruction] = self::TASKS[$task] ?? self::TASKS['free'];
        $kind  = ['news' => 'ข่าว/ประกาศ', 'page' => 'หน้าเว็บ', 'popup' => 'ป๊อปอัพประกาศ'][$in['kind'] ?? ''] ?? 'เนื้อหาเว็บไซต์';
        $parts = ['ประเภทเนื้อหา: ' . $kind . ' ของ' . Settings::get('site_name', 'วิทยาลัย')];
        if ($instruction !== '') {
            $parts[] = 'งานที่ต้องทำ: ' . $instruction;
        }
        if (($in['instruction'] ?? '') !== '') {
            $parts[] = ($instruction !== '' ? 'คำสั่งเพิ่มเติม: ' : 'คำสั่ง: ') . $in['instruction'];
        }
        if (($in['title'] ?? '') !== '') {
            $parts[] = "หัวข้อ:\n" . $in['title'];
        }
        if (($in['text'] ?? '') !== '') {
            $parts[] = ($task === 'draft' ? "ประเด็น/ข้อมูล:\n" : ($task === 'import' ? "เนื้อหาจากเว็บไซต์เดิม:\n" : "ข้อความ:\n")) . "<<<\n" . $in['text'] . "\n>>>";
        }
        return [
            ['role' => 'system', 'content' => self::systemPrompt()],
            ['role' => 'user', 'content' => implode("\n\n", $parts)],
        ];
    }

    public static function logUsage(?int $userId, ?int $providerId, string $model, string $task, array $res, int $ms): void
    {
        try {
            Database::run('INSERT INTO ai_usage_log (user_id, provider_id, model_id, task, ok, prompt_tokens, completion_tokens, duration_ms, error) VALUES (?,?,?,?,?,?,?,?,?)', [
                $userId, $providerId, mb_substr($model, 0, 190), $task, $res['ok'] ? 1 : 0,
                $res['usage']['prompt'] ?? 0, $res['usage']['completion'] ?? 0, $ms, $res['error'] ? mb_substr($res['error'], 0, 500) : null,
            ]);
        } catch (Throwable) {
        }
    }
}
