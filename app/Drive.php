<?php
declare(strict_types=1);

/**
 * รูปภาพจากโฟลเดอร์ Google Drive ที่แนบลิงก์ในเนื้อข่าว — แสดงเป็นกริดรูปย่อในหน้าอ่านข่าว
 *   - อ่านรายการไฟล์จากหน้า embeddedfolderview ของ Google (ไม่ต้องใช้ API key)
 *     โฟลเดอร์ต้องแชร์แบบ "ทุกคนที่มีลิงก์" · ถ้าอ่านไม่ได้ หน้าข่าวยังมีลิงก์เดิมให้กดเปิด
 *   - รวมโฟลเดอร์ย่อย (เช่น แยกตามกล้อง) · แคชรายการไว้ใน storage/cache
 *   - ไม่ทำสำเนารูปลงเว็บ เบราว์เซอร์โหลดรูปย่อจาก Google โดยตรง
 */
final class Drive
{
    private const MAX_LINKS   = 4;    // ลิงก์โฟลเดอร์ต่อข่าว
    private const MAX_FOLDERS = 15;   // รวมโฟลเดอร์ย่อย
    private const MAX_DEPTH   = 2;
    private const MAX_PAGE    = 8 * 1048576;
    private const TTL         = 1800;
    private const RETRY       = 300;  // อ่านไม่สำเร็จ ลองใหม่หลังผ่านไปเท่านี้

    /** รหัสโฟลเดอร์ที่ลิงก์ไว้ในข้อความ @return list<string> */
    public static function folderIds(string $text): array
    {
        preg_match_all('~https?://drive\.google\.com/(?:drive/(?:u/\d+/)?(?:mobile/)?folders/|(?:embeddedfolderview|folderview|open)\?(?:[^\s)]*?&)?id=)([A-Za-z0-9_-]{15,100})~', $text, $m);
        return array_slice(array_values(array_unique($m[1])), 0, self::MAX_LINKS);
    }

    /**
     * รูปทั้งหมดของโฟลเดอร์ที่ระบุ แยกกลุ่มตามโฟลเดอร์
     * @return array{groups:list<array{id:string, name:string, images:list<array{0:string,1:string}>}>, partial:bool}
     */
    public static function gallery(array $ids): array
    {
        $queue = array_map(fn($i) => [$i, 0], $ids);
        $seen = $groups = [];
        $partial = false;
        $deadline = microtime(true) + 40;
        while ($queue) {
            [$id, $depth] = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            if (count($seen) >= self::MAX_FOLDERS || microtime(true) > $deadline) {
                $partial = true;
                break;
            }
            $seen[$id] = true;
            $f = self::folder($id);
            $partial = $partial || !empty($f['failed']);
            if ($f['images']) {
                $groups[] = ['id' => $id, 'name' => $f['title'], 'images' => $f['images']];
            }
            if ($depth < self::MAX_DEPTH) {
                foreach ($f['folders'] as $sub) {
                    $queue[] = [$sub, $depth + 1];
                }
            } elseif ($f['folders']) {
                $partial = true;
            }
        }
        return ['groups' => $groups, 'partial' => $partial];
    }

    /** รายการในโฟลเดอร์หนึ่ง (จากแคช หรืออ่านใหม่เมื่อแคชหมดอายุ) */
    private static function folder(string $id): array
    {
        $file = BASE_PATH . '/storage/cache/drive_' . $id . '.json';
        $cached = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (is_array($cached) && filemtime($file) > time() - self::TTL) {
            return $cached;
        }
        $mtime = time();
        try {
            $data = self::parse(self::fetch($id));
        } catch (Throwable $ex) {
            log_error('Drive ' . $id . ': ' . $ex->getMessage());
            $data = is_array($cached) ? $cached : ['title' => '', 'images' => [], 'folders' => [], 'failed' => true];   // ใช้ของเดิมไปก่อน
            $mtime = time() - self::TTL + self::RETRY;
        }
        @mkdir(dirname($file), 0775, true);
        if (@file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) !== false) {
            @touch($file, $mtime);
        }
        return $data;
    }

    private static function fetch(string $id): string
    {
        $ch = curl_init('https://drive.google.com/embeddedfolderview?id=' . rawurlencode($id));
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'rvc-web-drive/1.0',
            CURLOPT_ENCODING       => '',
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body) {
                $body .= $chunk;
                return strlen($body) > self::MAX_PAGE ? 0 : strlen($chunk);
            },
        ]);
        $res    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($res === false) {
            throw new RuntimeException('เชื่อมต่อ Google Drive ไม่ได้: ' . $err);
        }
        if ($status !== 200) {
            throw new RuntimeException('เปิดโฟลเดอร์ไม่ได้ (HTTP ' . $status . ') — ตรวจว่าแชร์แบบ "ทุกคนที่มีลิงก์" แล้ว');
        }
        return $body;
    }

    /**
     * แยกรายการจากหน้า embeddedfolderview — เก็บเฉพาะไฟล์รูปภาพและโฟลเดอร์ย่อย
     * @return array{title:string, images:list<array{0:string,1:string}>, folders:list<string>}
     */
    public static function parse(string $html): array
    {
        $text = fn(string $s) => trim(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = preg_match('~<title>(.*?)</title>~s', $html, $m) ? $text($m[1]) : '';
        $images = $folders = [];
        foreach (array_slice(explode('<div class="flip-entry" id="entry-', $html), 1) as $chunk) {
            if (!preg_match('~^([A-Za-z0-9_-]{15,100})"~', $chunk, $m)) {
                continue;
            }
            $id = $m[1];
            if (str_contains($chunk, '/drive/folders/' . $id)) {
                $folders[] = $id;
            } elseif (str_contains($chunk, '/type/image/')) {
                $name = preg_match('~class="flip-entry-title">(.*?)</div>~s', $chunk, $t) ? $text($t[1]) : '';
                $images[] = [$id, $name];
            }
        }
        return compact('title', 'images', 'folders');
    }
}
