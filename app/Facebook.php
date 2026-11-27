<?php
declare(strict_types=1);

/**
 * ดึงโพสต์จากเพจ Facebook ของวิทยาลัยมาเป็นข่าวหมวด "ฮอตอัพเดต"
 *
 * ใช้ Facebook Graph API ด้วย Page Access Token ของเพจ (สิทธิ์ pages_read_engagement)
 *   - โทเคนเก็บแบบเข้ารหัสด้วยกุญแจเดียวกับ API key ของผู้ช่วย AI
 *   - รูปภาพทำสำเนาลง uploads/ เพราะลิงก์รูปของ Facebook หมดอายุภายในไม่กี่วัน
 *   - กันนำเข้าซ้ำด้วย news.source_ref = "fb:<post id>"
 * ค่าตั้งค่า (ตาราง settings ไม่แสดงในหน้าตั้งค่าเว็บไซต์ และผู้ช่วย AI อ่าน/แก้ไม่ได้):
 *   fb_page_id, fb_token, fb_publish, fb_auto, fb_interval, fb_limit, fb_last_sync, fb_last_result
 */
final class Facebook
{
    public const CATEGORY     = 'hot';
    public const DEFAULT_PAGE = '100057582813831';
    private const GRAPH       = 'https://graph.facebook.com/v21.0/';
    private const FIELDS      = 'id,message,created_time,permalink_url,full_picture,status_type,'
        . 'attachments{media_type,title,url,media,subattachments.limit(20){media_type,media,url}}';
    private const MAX_IMAGES  = 12;
    private const MAX_IMAGE   = 10 * 1048576;

    /* ------------------------------ ค่าตั้งค่า ------------------------------ */

    public static function pageId(): string
    {
        return Settings::get('fb_page_id', self::DEFAULT_PAGE);
    }

    public static function token(): string
    {
        return Ai::decrypt(Settings::get('fb_token') ?: null);
    }

    public static function configured(): bool
    {
        return self::pageId() !== '' && self::token() !== '';
    }

    public static function publishNow(): bool
    {
        return Settings::get('fb_publish', '1') === '1';
    }

    public static function interval(): int
    {
        return max(5, (int) Settings::get('fb_interval', '15'));
    }

    public static function limit(): int
    {
        return max(1, min(25, (int) Settings::get('fb_limit', '10')));
    }

    /** ถึงเวลาดึงโพสต์อัตโนมัติรอบใหม่หรือยัง */
    public static function due(): bool
    {
        return Settings::get('fb_auto') === '1' && self::configured()
            && time() - (int) Settings::get('fb_last_sync', '0') >= self::interval() * 60;
    }

    /* ------------------------------ Graph API ------------------------------ */

    private static function http(string $url, int $max, int $timeout = 20): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $ok = parse_url($url, PHP_URL_SCHEME) === 'https'
            && ($host === 'graph.facebook.com' || str_ends_with($host, '.fbcdn.net') || str_ends_with($host, '.facebook.com'));
        if (!$ok) {
            throw new RuntimeException('ไม่อนุญาตให้ดึงข้อมูลจาก ' . $host);
        }
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'rvc-web-facebook/1.0',
            CURLOPT_ENCODING       => '',
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, $max) {
                $body .= $chunk;
                return strlen($body) > $max ? 0 : strlen($chunk);
            },
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            throw new RuntimeException(strlen($body) > $max ? 'ไฟล์ใหญ่เกินกำหนด' : 'เชื่อมต่อ Facebook ไม่ได้: ' . $err);
        }
        return [$status, $body];
    }

    private static function graph(string $path, array $query = [], ?string $token = null): array
    {
        $token ??= self::token();
        if ($token === '') {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า Page Access Token ของเพจ Facebook (เมนู "ข่าวจาก Facebook")');
        }
        [$status, $body] = self::http(self::GRAPH . $path . '?' . http_build_query($query + ['access_token' => $token]), 4 * 1048576);
        $j = json_decode($body, true);
        if (!is_array($j)) {
            throw new RuntimeException('Facebook ตอบกลับไม่ถูกต้อง (HTTP ' . $status . ')');
        }
        if (isset($j['error'])) {
            $code = (int) ($j['error']['code'] ?? 0);
            throw new RuntimeException(match (true) {
                $code === 190 => 'Page Access Token หมดอายุหรือถูกยกเลิก — สร้างโทเคนใหม่แล้วบันทึกในเมนู "ข่าวจาก Facebook"',
                in_array($code, [10, 200, 283], true) => 'โทเคนไม่มีสิทธิ์อ่านโพสต์ของเพจ (ต้องใช้ Page Access Token ที่มีสิทธิ์ pages_read_engagement)',
                in_array($code, [4, 17, 32, 613], true) => 'เรียก Facebook ถี่เกินไป ลองใหม่ภายหลัง',
                default => 'Facebook: ' . ($j['error']['message'] ?? 'เกิดข้อผิดพลาด'),
            });
        }
        return $j;
    }

    /** ตรวจโทเคน: คืนชื่อเพจ */
    public static function test(?string $token = null, ?string $pageId = null): string
    {
        $j = self::graph(rawurlencode($pageId ?? self::pageId()), ['fields' => 'name'], $token);
        return (string) ($j['name'] ?? '');
    }

    /** โพสต์ล่าสุดของเพจ (แคช 2 นาที) @return list<array> */
    public static function posts(int $limit = 10, bool $fresh = false): array
    {
        $limit = max(1, min(25, $limit));
        $file = BASE_PATH . '/storage/cache/fb_posts_' . md5(self::pageId() . '|' . $limit) . '.json';
        if (!$fresh && is_file($file) && filemtime($file) > time() - 120) {
            $c = json_decode((string) file_get_contents($file), true);
            if (is_array($c)) {
                return $c;
            }
        }
        $j = self::graph(rawurlencode(self::pageId()) . '/posts', ['fields' => self::FIELDS, 'limit' => $limit]);
        $out = array_values(array_filter(array_map([self::class, 'normalize'], (array) ($j['data'] ?? []))));
        @mkdir(dirname($file), 0775, true);
        @file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $out;
    }

    public static function post(string $id): array
    {
        if (!preg_match('~^\d+(_\d+)?$~', $id)) {
            throw new InvalidArgumentException('เลขโพสต์ Facebook ไม่ถูกต้อง: ' . $id);
        }
        $p = self::normalize(self::graph($id, ['fields' => self::FIELDS]));
        if (!$p) {
            throw new RuntimeException('โพสต์ ' . $id . ' ไม่มีข้อความหรือรูปภาพให้นำเข้า');
        }
        return $p;
    }

    /** แปลงโพสต์ให้อยู่ในรูปที่ใช้ภายใน (null = ไม่มีเนื้อหาให้นำเข้า) */
    private static function normalize(array $r): ?array
    {
        $msg = trim(str_replace(["\r\n", "\r"], "\n", (string) ($r['message'] ?? '')));
        $att = $r['attachments']['data'][0] ?? [];
        $images = [];
        $add = function (array $a) use (&$images) {
            $src = $a['media']['image']['src'] ?? null;
            if ($src && in_array($a['media_type'] ?? 'photo', ['photo', 'album', 'link', 'video', 'share', 'cover_photo', 'profile_media'], true)) {
                $images[] = (string) $src;
            }
        };
        foreach ((array) ($att['subattachments']['data'] ?? []) as $s) {
            $add((array) $s);
        }
        if (!$images && $att) {
            $add((array) $att);
        }
        if (!$images && !empty($r['full_picture'])) {
            $images[] = (string) $r['full_picture'];
        }
        if ($msg === '' && !$images) {
            return null;
        }
        $link = ($att['media_type'] ?? '') === 'link' ? (string) ($att['url'] ?? '') : '';
        $ts = strtotime((string) ($r['created_time'] ?? '')) ?: time();
        return [
            'id'        => (string) $r['id'],
            'message'   => $msg,
            'title'     => self::titleFrom($msg, (string) ($att['title'] ?? ''), $ts),
            'date'      => date('Y-m-d H:i:s', $ts),
            'permalink' => (string) ($r['permalink_url'] ?? ('https://www.facebook.com/' . $r['id'])),
            'images'    => array_slice(array_values(array_unique($images)), 0, self::MAX_IMAGES),
            'video'     => ($att['media_type'] ?? '') === 'video',
            'link'      => $link,
        ];
    }

    /** หัวข้อข่าวจากบรรทัดแรกที่มีเนื้อหาของโพสต์ (ตัดอีโมจิ/สัญลักษณ์นำหน้า และแฮชแท็ก) */
    public static function titleFrom(string $msg, string $fallback = '', int $ts = 0): string
    {
        foreach (explode("\n", $msg) as $line) {
            $l = preg_replace('~#[\p{L}\p{M}\p{N}_]+~u', '', $line) ?? $line;
            $l = preg_replace('~^[^\p{L}\p{N}]+|[\s\p{So}\p{Sk}\x{FE0F}\x{200D}]+$~u', '', trim($l)) ?? $l;
            $l = trim(preg_replace('~\s+~u', ' ', $l) ?? $l);
            if (mb_strlen($l) >= 4) {
                return self::cut($l, 120);
            }
        }
        $fallback = trim($fallback);
        return $fallback !== '' ? self::cut($fallback, 120) : 'โพสต์จากเพจ Facebook ' . thai_date($ts ?: time(), 'short');
    }

    private static function cut(string $s, int $n): string
    {
        if (mb_strlen($s) <= $n) {
            return $s;
        }
        $c = mb_substr($s, 0, $n);
        $sp = mb_strrpos($c, ' ');
        return rtrim($sp !== false && $sp > $n * 0.6 ? mb_substr($c, 0, $sp) : $c) . '…';
    }

    /* ------------------------------ นำเข้าเป็นข่าว ------------------------------ */

    /** @return array<string,int> source_ref => news id ของโพสต์ที่นำเข้าแล้ว */
    public static function importedMap(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $refs = array_map(fn($i) => 'fb:' . $i, $ids);
        $rows = Database::all('SELECT id, source_ref FROM news WHERE source_ref IN (' . implode(',', array_fill(0, count($refs), '?')) . ')', $refs);
        $map = [];
        foreach ($rows as $r) {
            $map[substr($r['source_ref'], 3)] = (int) $r['id'];
        }
        return $map;
    }

    /** ทำสำเนารูปจาก Facebook ลง uploads/ปี/เดือน/ (ชื่อแบบเดียวกับไฟล์อัปโหลด ลบพร้อมข่าวได้) */
    private static function saveImage(string $url): string
    {
        [$status, $body] = self::http($url, self::MAX_IMAGE, 60);
        if ($status !== 200 || $body === '') {
            throw new RuntimeException('ดาวน์โหลดรูปไม่สำเร็จ (HTTP ' . $status . ')');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '';
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if ($ext === null || @getimagesizefromstring($body) === false) {
            throw new RuntimeException('ไฟล์ไม่ใช่รูปภาพที่รองรับ');
        }
        $sub = 'uploads/' . date('Y/m');
        if (!is_dir(BASE_PATH . '/' . $sub) && !@mkdir(BASE_PATH . '/' . $sub, 0775, true)) {
            throw new RuntimeException('สร้างโฟลเดอร์ ' . $sub . ' ไม่ได้ — ตรวจสิทธิ์เขียนของ uploads/');
        }
        $path = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (@file_put_contents(BASE_PATH . '/' . $path, $body, LOCK_EX) === false) {
            throw new RuntimeException('บันทึกรูปไม่สำเร็จ');
        }
        return $path;
    }

    /**
     * นำเข้าโพสต์เป็นข่าวหมวดฮอตอัพเดต
     * @param string $status published|pending|draft
     * @return array{id:int, skipped:bool, warnings:list<string>}
     */
    public static function import(array $p, ?int $authorId, string $status, ?string $title = null): array
    {
        if ($exist = Database::value('SELECT id FROM news WHERE source_ref = ?', ['fb:' . $p['id']])) {
            return ['id' => (int) $exist, 'skipped' => true, 'warnings' => []];
        }
        $warn = [];
        $saved = [];
        foreach ($p['images'] as $src) {
            try {
                $saved[] = self::saveImage($src);
            } catch (Throwable $ex) {
                $warn[] = 'รูปภาพ: ' . $ex->getMessage();
            }
        }
        $title = trim((string) $title) !== '' ? mb_substr(trim((string) $title), 0, 255) : $p['title'];
        $cover = $saved[0] ?? null;
        $body = $p['message'];
        if (count($saved) > 1) {
            $body .= "\n\n" . implode("\n", array_map(fn($s, $i) => '![ภาพที่ ' . ($i + 1) . '](' . $s . ')', $saved, array_keys($saved)));
        }
        if ($p['link'] !== '' && !str_contains($body, $p['link'])) {
            $body .= "\n\n[ลิงก์ที่แนบในโพสต์](" . $p['link'] . ')';
        }
        $body = ltrim($body . "\n\n[" . ($p['video'] ? 'ดูวิดีโอและโพสต์ต้นฉบับบน Facebook' : 'ดูโพสต์ต้นฉบับบน Facebook') . '](' . $p['permalink'] . ')');
        $plain = preg_replace('~#[\p{L}\p{M}\p{N}_]+~u', '', $p['message']) ?? $p['message'];
        try {
            $id = Database::insert('news', [
                'title'        => $title,
                'excerpt'      => mb_substr(str_limit($plain !== '' ? $plain : $title, 220), 0, 500),
                'body'         => $body,
                'cover'        => $cover,
                'category'     => self::CATEGORY,
                'status'       => $status,
                'published_at' => $p['date'],
                'author_id'    => $authorId,
                'source_ref'   => 'fb:' . $p['id'],
            ]);
        } catch (PDOException $ex) {
            foreach ($saved as $s) {
                delete_upload($s);
            }
            if ($ex->getCode() === '23000' && ($exist = Database::value('SELECT id FROM news WHERE source_ref = ?', ['fb:' . $p['id']]))) {
                return ['id' => (int) $exist, 'skipped' => true, 'warnings' => []];   // นำเข้าพร้อมกันจากอีกคำขอ
            }
            throw $ex;
        }
        return ['id' => $id, 'skipped' => false, 'warnings' => $warn];
    }

    /**
     * ดึงโพสต์ล่าสุดที่ยังไม่เคยนำเข้า (เรียงจากเก่าไปใหม่ ให้ลำดับข่าวตรงกับเพจ)
     * @return array{added:int, skipped:int, failed:list<string>, ids:list<int>}
     */
    public static function sync(?int $authorId, ?string $status = null, ?int $limit = null): array
    {
        $status ??= self::publishNow() ? 'published' : 'draft';
        $posts = self::posts($limit ?? self::limit(), true);
        $done = self::importedMap(array_column($posts, 'id'));
        $added = $skipped = 0;
        $failed = $ids = [];
        foreach (array_reverse($posts) as $p) {
            if (isset($done[$p['id']])) {
                $skipped++;
                continue;
            }
            try {
                $r = self::import($p, $authorId, $status);
                if ($r['skipped']) {
                    $skipped++;
                } else {
                    $added++;
                    $ids[] = $r['id'];
                }
            } catch (Throwable $ex) {
                $failed[] = $p['title'] . ': ' . $ex->getMessage();
            }
        }
        Settings::set('fb_last_sync', (string) time());
        Settings::set('fb_last_result', json_encode(['at' => time(), 'added' => $added, 'skipped' => $skipped, 'failed' => $failed], JSON_UNESCAPED_UNICODE));
        return compact('added', 'skipped', 'failed', 'ids');
    }

    /** ดึงอัตโนมัติเมื่อถึงรอบ (กันหลายคำขอทำงานพร้อมกันด้วย lock file) — คืนผลหรือ null ถ้าไม่ได้ทำงาน */
    public static function autoSync(bool $force = false): ?array
    {
        if (!($force ? self::configured() : self::due())) {
            return null;
        }
        $dir = BASE_PATH . '/storage/cache';
        @mkdir($dir, 0775, true);
        $fh = @fopen($dir . '/fb_sync.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return null;
        }
        try {
            Settings::set('fb_last_sync', (string) time());   // จองรอบนี้ไว้ก่อน แม้ Facebook จะตอบช้าหรือผิดพลาด
            return self::sync(null);
        } catch (Throwable $ex) {
            Settings::set('fb_last_result', json_encode(['at' => time(), 'error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE));
            log_error('Facebook sync: ' . $ex->getMessage());
            return null;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** ผลการดึงครั้งล่าสุด */
    public static function lastResult(): ?array
    {
        $r = json_decode(Settings::get('fb_last_result'), true);
        return is_array($r) ? $r : null;
    }
}
