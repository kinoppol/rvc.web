<?php
declare(strict_types=1);

/**
 * ดึงข้อมูลจากเว็บไซต์เดิม (www.rvc.ac.th) เพื่อนำมาใช้ในเว็บใหม่
 *
 * เว็บเดิมเป็น Nuxt (SPA) — ข้อมูลข่าว/กิจกรรม/รางวัล/จัดซื้อจัดจ้าง มาจาก JSON API (ค่าเริ่มต้น http://202.29.239.178:9000/api)
 * ส่วนหน้าเนื้อหาคงที่ (วิสัยทัศน์ ประวัติ ฯลฯ) ฝังอยู่ในไฟล์ JavaScript ของแต่ละหน้า
 *
 * เว็บเดิมจะถูกปิด: ไฟล์ที่อยู่บนเว็บเดิม (รูป PDF เอกสาร) จะถูก "ทำสำเนา" ไว้ใน uploads/legacy/ และเปลี่ยนลิงก์ให้ชี้ไฟล์ในเว็บนี้
 * ส่วนลิงก์ภายนอก (Google Drive, Facebook ฯลฯ) คงไว้เป็นลิงก์ตามเดิม
 */
final class LegacySite
{
    /** ชนิดข้อมูลจาก API เว็บเดิม */
    public const SOURCES = [
        'news'        => ['label' => 'ข่าวประชาสัมพันธ์', 'list' => 'news?status=1', 'one' => 'news/', 'id' => 'news_id', 'title' => 'title', 'date' => 'dt', 'category' => 'pr', 'view' => '/news/'],
        'activity'    => ['label' => 'ภาพกิจกรรม', 'list' => 'activity?status=1', 'one' => 'activity/', 'id' => 'activity_id', 'title' => 'activity', 'date' => 'dtact', 'category' => 'activity', 'view' => '/activity/'],
        'award'       => ['label' => 'ผลงานและรางวัล', 'list' => 'award?status=1', 'one' => 'award/', 'id' => 'award_id', 'title' => 'award', 'date' => 'dtaward', 'category' => 'award', 'view' => '/award/'],
        'procurement' => ['label' => 'จัดซื้อจัดจ้าง', 'list' => 'purchase?status=1&type=0', 'one' => 'purchase/', 'id' => 'purchase_id', 'title' => 'title', 'date' => 'dt', 'category' => 'procurement', 'view' => null],
        'jobs'        => ['label' => 'รับสมัครงาน / ประกาศผล', 'list' => 'purchase?status=1&type=1', 'one' => 'purchase/', 'id' => 'purchase_id', 'title' => 'title', 'date' => 'dt', 'category' => 'jobs', 'view' => null],
    ];

    public const DEFAULT_SITE  = 'http://www.rvc.ac.th';
    public const DEFAULT_API   = 'http://202.29.239.178:9000/api';
    public const DEFAULT_HOSTS = 'www.rvc.ac.th, rvc.ac.th, 202.29.239.178';

    /** ชนิดไฟล์ที่ทำสำเนาได้ */
    private const FILE_TYPES = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls', 'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
    private const FILE_EXT_RE = '~\.(pdf|jpe?g|png|gif|webp|docx?|xlsx?|pptx?)(\?.*)?$~i';
    private const MAX_FILE    = 30 * 1048576;

    /* ------------------------------ ค่าตั้ง ------------------------------ */

    public static function siteBase(): string
    {
        return rtrim(Settings::get('legacy_site_url', self::DEFAULT_SITE), '/');
    }

    public static function apiBase(): string
    {
        return rtrim(Settings::get('legacy_api_url', self::DEFAULT_API), '/');
    }

    /** host ของเว็บเดิม — ไฟล์จาก host เหล่านี้จะถูกทำสำเนา */
    public static function hosts(): array
    {
        $list = preg_split('/[\s,]+/', strtolower(Settings::get('legacy_hosts', self::DEFAULT_HOSTS))) ?: [];
        foreach ([self::siteBase(), self::apiBase()] as $u) {
            $list[] = strtolower((string) parse_url($u, PHP_URL_HOST));
        }
        return array_values(array_unique(array_filter($list)));
    }

    public static function isLegacyUrl(string $url): bool
    {
        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $h !== '' && in_array($h, self::hosts(), true);
    }

    /* ------------------------------ HTTP ------------------------------ */

    /** @return array{status:int, body:string, type:string} */
    private static function get(string $url, int $timeout = 25, int $max = 8_388_608): array
    {
        if (!preg_match('~^https?://~i', $url)) {
            throw new RuntimeException('ลิงก์ต้องขึ้นต้นด้วย http:// หรือ https://');
        }
        if (!self::isLegacyUrl($url)) {
            self::assertPublicHost($url);
        }
        $ch = curl_init($url);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; rvc-web-importer/1.0)',
            CURLOPT_ENCODING       => '',
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, $max) {
                $body .= $chunk;
                return strlen($body) > $max ? 0 : strlen($chunk);   // ใหญ่เกิน → หยุด
            },
        ]);
        $ok     = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $loc    = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($ok === false && strlen($body) > $max) {
            throw new RuntimeException('ไฟล์ใหญ่เกิน ' . round($max / 1048576) . ' MB');
        }
        if ($ok === false) {
            throw new RuntimeException('เชื่อมต่อ ' . parse_url($url, PHP_URL_HOST) . ' ไม่ได้: ' . $err);
        }
        if ($status >= 300 && $status < 400 && $loc !== '') {
            static $hops = 0;
            if (++$hops > 3) {
                throw new RuntimeException('เปลี่ยนเส้นทางหลายครั้งเกินไป');
            }
            try {
                return self::get($loc, $timeout, $max);
            } finally {
                $hops--;
            }
        }
        return ['status' => $status, 'body' => $body, 'type' => $type];
    }

    /** กันการดึง URL ที่ชี้เข้าเครือข่ายภายใน (SSRF) */
    private static function assertPublicHost(string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ips  = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            throw new RuntimeException('ไม่พบโดเมน ' . $host);
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('ไม่อนุญาตให้ดึงข้อมูลจากเครือข่ายภายใน');
            }
        }
    }

    /** ดึง JSON/ข้อความพร้อมแคชใน storage/cache */
    private static function cached(string $url, int $ttl): string
    {
        $dir  = BASE_PATH . '/storage/cache';
        $file = $dir . '/legacy_' . sha1($url) . '.cache';
        if ($ttl > 0 && is_file($file) && filemtime($file) > time() - $ttl) {
            return (string) file_get_contents($file);
        }
        $r = self::get($url);
        if ($r['status'] !== 200) {
            if (is_file($file)) {
                return (string) file_get_contents($file);   // เว็บเดิมล่ม → ใช้ของเก่าในแคช
            }
            throw new RuntimeException('เว็บเดิมตอบกลับ HTTP ' . $r['status'] . ' (' . $url . ')');
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, $r['body'], LOCK_EX);
        return $r['body'];
    }

    private static function api(string $path, int $ttl = 600): array
    {
        $j = json_decode(self::cached(self::apiBase() . '/' . ltrim($path, '/'), $ttl), true);
        if (!is_array($j)) {
            throw new RuntimeException('API เว็บเดิมตอบกลับไม่ใช่ JSON');
        }
        return $j['data'] ?? $j;
    }

    /* ------------------------------ ข่าว / กิจกรรม / ประกาศ ------------------------------ */

    /** @return array{items: list<array>, total: int, page: int, pages: int} */
    public static function list(string $source, string $q = '', int $page = 1, int $per = 20): array
    {
        $src  = self::SOURCES[$source] ?? throw new InvalidArgumentException('ไม่รู้จักชนิดข้อมูล');
        $rows = self::api($src['list']);
        $rows = array_is_list($rows) ? $rows : [];
        $items = array_map(fn($r) => self::normalize($source, $r), $rows);
        if ($q !== '') {
            $items = array_values(array_filter($items, fn($i) => mb_stripos($i['title'] . ' ' . $i['text'], $q) !== false));
        }
        usort($items, fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));
        $pg = paginate(count($items), $per, $page);
        return ['items' => array_slice($items, $pg['offset'], $per), 'total' => $pg['total'], 'page' => $pg['page'], 'pages' => $pg['pages']];
    }

    public static function item(string $source, int $id): array
    {
        $src = self::SOURCES[$source] ?? throw new InvalidArgumentException('ไม่รู้จักชนิดข้อมูล');
        $row = self::api($src['one'] . $id, 300);
        $row = array_is_list($row) ? ($row[0] ?? null) : $row;
        if (!is_array($row) || (int) ($row[$src['id']] ?? 0) !== $id) {
            throw new RuntimeException('ไม่พบรายการนี้ในเว็บเดิม');
        }
        return self::normalize($source, $row);
    }

    private static function normalize(string $source, array $r): array
    {
        $src  = self::SOURCES[$source];
        $id   = (int) ($r[$src['id']] ?? 0);
        $date = $r[$src['date']] ?? $r['dt'] ?? null;
        $ts   = $date ? strtotime((string) $date) : false;
        return [
            'source' => $source,
            'id'     => $id,
            'ref'    => $source . ':' . $id,
            'title'  => trim(preg_replace('/\s+/u', ' ', (string) ($r[$src['title']] ?? '')) ?? ''),
            'date'   => $ts ? date('Y-m-d H:i:s', $ts) : null,
            'text'   => self::htmlToText((string) ($r['detail'] ?? '')),
            'place'  => trim((string) ($r['place'] ?? '')),
            'image'  => self::absUrl((string) ($r['thumbnail'] ?? '')),
            'attach' => self::absUrl((string) ($r['file'] ?? $r['attach'] ?? '')),
            'link'   => trim((string) ($r['website'] ?? '')),
            'view'   => $src['view'] ? self::siteBase() . $src['view'] . $id : null,
            'category' => $src['category'],
        ];
    }

    private static function absUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '' || preg_match('~^https?://~i', $u)) {
            return $u;
        }
        $api = parse_url(self::apiBase());
        return ($api['scheme'] ?? 'http') . '://' . ($api['host'] ?? '') . (isset($api['port']) ? ':' . $api['port'] : '') . '/' . ltrim($u, '/');
    }

    /** HTML สั้นๆ จาก API → ข้อความรูปแบบของเว็บนี้ (ลิงก์เป็น [ข้อความ](url)) */
    public static function htmlToText(string $html): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $html);
        $t = preg_replace_callback('~<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)</a>~is', function ($m) {
            $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $href  = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return $label === '' || $label === $href ? $href : '[' . str_replace(['[', ']'], ['(', ')'], $label) . '](' . $href . ')';
        }, $t) ?? $t;
        $t = preg_replace('~<br\s*/?>|</p>|</div>|</li>~i', "\n", $t) ?? $t;
        $t = preg_replace('~<li[^>]*>~i', '- ', $t) ?? $t;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/ *\n */', "\n", $t) ?? $t;
        return trim(preg_replace("/\n{3,}/", "\n\n", $t) ?? $t);
    }

    /**
     * ประกอบเนื้อหาพร้อมใช้: รายละเอียด + สถานที่ + ไฟล์แนบ + ลิงก์ภาพเพิ่มเติม
     * ไฟล์ที่อยู่บนเว็บเดิมจะถูกทำสำเนาและเปลี่ยนลิงก์เป็นไฟล์ในเว็บนี้
     * @return array{text:string, warnings:list<string>}
     */
    public static function compose(array $it): array
    {
        $warn  = [];
        $parts = [];
        if ($it['text'] !== '') {
            $parts[] = $it['text'];
        }
        $meta = [];
        if ($it['place'] !== '') {
            $meta[] = '- สถานที่: ' . $it['place'];
        }
        if ($it['source'] === 'activity' && $it['date']) {
            $meta[] = '- วันที่จัดกิจกรรม: ' . thai_date($it['date'], 'long');
        }
        if ($it['attach'] !== '') {
            $meta[] = '- [เอกสารแนบ' . (preg_match('~\.pdf$~i', $it['attach']) ? ' (PDF)' : '') . '](' . $it['attach'] . ')';
        }
        if ($it['link'] !== '' && preg_match('~^https?://~i', $it['link'])) {
            $meta[] = '- [' . ($it['source'] === 'activity' || $it['source'] === 'award' ? 'ชมภาพเพิ่มเติม' : 'รายละเอียดเพิ่มเติม') . '](' . $it['link'] . ')';
        }
        if ($meta) {
            $parts[] = implode("\n", $meta);
        }
        return self::mirrorLinks(implode("\n\n", $parts), $warn) + ['warnings' => $warn];
    }

    /**
     * เปลี่ยนลิงก์ไฟล์บนเว็บเดิมเป็นสำเนาในเว็บนี้ · ลิงก์หน้าเว็บเดิม (ไม่ใช่ไฟล์) จะเหลือเฉพาะข้อความ เพราะเว็บเดิมจะถูกปิด
     * @return array{text:string, warnings:list<string>}
     */
    public static function mirrorLinks(string $text, array &$warn = []): array
    {
        $fix = function (string $url, ?string $label) use (&$warn): string {
            if (!self::isLegacyUrl($url)) {
                return $label !== null ? '[' . $label . '](' . $url . ')' : $url;
            }
            if (preg_match(self::FILE_EXT_RE, (string) parse_url($url, PHP_URL_PATH))) {
                try {
                    $path = self::mirrorFile($url);
                    return '[' . ($label ?? basename((string) parse_url($url, PHP_URL_PATH))) . '](' . $path . ')';
                } catch (Throwable $ex) {
                    $warn[] = 'ทำสำเนาไฟล์ไม่สำเร็จ: ' . $url . ' — ' . $ex->getMessage();
                    return $label !== null ? '[' . $label . '](' . $url . ')' : $url;
                }
            }
            return $label ?? '';   // หน้าเว็บเดิม — จะใช้ไม่ได้หลังปิดเว็บ
        };
        $text = preg_replace_callback('~\[([^\]\n]+)\]\((https?://[^\s)]+)\)~u', fn($m) => $fix($m[2], $m[1]), $text) ?? $text;
        $text = preg_replace_callback('~(?<![(\w/])(https?://[^\s<>()\[\]"\']+[^\s<>()\[\]"\'.,;:!?])~u', fn($m) => $fix($m[1], null), $text) ?? $text;
        return ['text' => trim($text), 'warnings' => $warn];
    }

    /**
     * ทำสำเนาไฟล์จากเว็บเดิมลง uploads/legacy/ (ดาวน์โหลดครั้งเดียว ใช้ซ้ำได้)
     * @return string path แบบ relative เช่น uploads/legacy/2026/11/abc….pdf
     */
    public static function mirrorFile(string $url, bool $imagesOnly = false): string
    {
        if (!self::isLegacyUrl($url)) {
            throw new RuntimeException('ทำสำเนาได้เฉพาะไฟล์จากเว็บเดิม');
        }
        $hash = sha1($url);
        $row  = Database::one('SELECT path, mime FROM legacy_files WHERE src_hash = ?', [$hash]);
        if ($row && is_file(BASE_PATH . '/' . $row['path'])) {
            if ($imagesOnly && !str_starts_with($row['mime'], 'image/')) {
                throw new RuntimeException('ไฟล์นี้ไม่ใช่รูปภาพ');
            }
            return $row['path'];
        }
        $r = self::get($url, 120, self::MAX_FILE);
        if ($r['status'] !== 200 || $r['body'] === '') {
            throw new RuntimeException('HTTP ' . $r['status']);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($r['body']) ?: '';
        if ($mime === 'application/zip' || $mime === 'application/octet-stream') {   // ไฟล์ Office แบบใหม่บางไฟล์
            $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mime = array_search($ext, self::FILE_TYPES, true) ?: $mime;
        }
        $ext = self::FILE_TYPES[$mime] ?? null;
        if ($ext === null || ($imagesOnly && !str_starts_with($mime, 'image/'))) {
            throw new RuntimeException('ชนิดไฟล์ไม่รองรับ (' . ($mime ?: 'ไม่ทราบ') . ')');
        }
        if (str_starts_with($mime, 'image/') && @getimagesizefromstring($r['body']) === false) {
            throw new RuntimeException('ไฟล์รูปภาพเสีย');
        }
        $sub = 'uploads/legacy/' . date('Y/m');
        $dir = BASE_PATH . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            throw new RuntimeException('สร้างโฟลเดอร์ ' . $sub . ' ไม่ได้ — ตรวจสิทธิ์เขียนของ uploads/');
        }
        $path = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (@file_put_contents(BASE_PATH . '/' . $path, $r['body'], LOCK_EX) === false) {
            throw new RuntimeException('บันทึกไฟล์ไม่สำเร็จ');
        }
        Database::run('INSERT INTO legacy_files (src_hash, src_url, path, mime, bytes) VALUES (?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE path = VALUES(path), mime = VALUES(mime), bytes = VALUES(bytes)',
            [$hash, mb_substr($url, 0, 1000), $path, $mime, strlen($r['body'])]);
        return $path;
    }

    /* ------------------------------ หน้าเนื้อหาคงที่ (อยู่ใน JavaScript ของเว็บเดิม) ------------------------------ */

    /** ข้อมูลโครงสร้าง Nuxt: [routes => [path => chunk ids], chunks => [id => hash], assets => url] */
    private static function nuxt(): array
    {
        static $info = null;
        if ($info !== null) {
            return $info;
        }
        $base = self::siteBase();
        $html = self::cached($base . '/', 86400);
        preg_match_all('~<script[^>]+src="([^"]+\.js)"~i', $html, $m);
        $routes = [];
        $chunks = [];
        $assets = $base . '/_nuxt/';
        foreach ($m[1] as $src) {
            $url = preg_match('~^https?://~', $src) ? $src : $base . '/' . ltrim($src, '/');
            $assets = preg_replace('~[^/]+$~', '', $url) ?: $assets;
            $js = self::cached($url, 86400);
            if (preg_match('~\{(\d+:"[0-9a-f]{6,}"(?:,\d+:"[0-9a-f]{6,}")*)\}\[\w+\]\+"\.js"~', $js, $cm)) {
                preg_match_all('~(\d+):"([0-9a-f]+)"~', $cm[1], $pairs, PREG_SET_ORDER);
                foreach ($pairs as $p) {
                    $chunks[(int) $p[1]] = $p[2];
                }
            }
            preg_match_all('~path:"(/[^"]*)",component:function\(\)\{return [^;]{0,40}?\((Promise\.all\(\[[^\]]+\]\)|\w+\.e\(\d+\))~', $js, $rm, PREG_SET_ORDER);
            foreach ($rm as $r) {
                preg_match_all('~\.e\((\d+)\)~', $r[2], $ids);
                $routes[$r[1]] = array_map('intval', $ids[1]);
            }
        }
        if (!$chunks) {   // บางเวอร์ชันแผนที่ chunk อยู่รูปแบบอื่น — ลองหาแบบกว้าง
            foreach ($m[1] as $src) {
                $js = self::cached(preg_match('~^https?://~', $src) ? $src : $base . '/' . ltrim($src, '/'), 86400);
                if (preg_match('~\{0:"[0-9a-f]{6,}"[^}]+\}~', $js, $cm)) {
                    preg_match_all('~(\d+):"([0-9a-f]+)"~', $cm[0], $pairs, PREG_SET_ORDER);
                    foreach ($pairs as $p) {
                        $chunks[(int) $p[1]] = $p[2];
                    }
                }
            }
        }
        return $info = ['routes' => $routes, 'chunks' => $chunks, 'assets' => $assets];
    }

    /** รายชื่อหน้าเนื้อหาคงที่ของเว็บเดิม (ไม่รวมหน้าระบบหลังบ้าน/รายการข่าว) */
    public static function pages(): array
    {
        $out = [];
        foreach (self::nuxt()['routes'] as $path => $ids) {
            if ($path === '/' || str_contains($path, ':') || preg_match('~^/(adminnn|auth|admin)|_save$|-save$|_store$|^/(news|activity|award)$~', $path)) {
                continue;
            }
            $out[] = $path;
        }
        sort($out);
        return $out;
    }

    /**
     * ข้อความของหน้าเนื้อหาคงที่ — อ่านจากข้อความในเทมเพลต Vue ที่คอมไพล์แล้ว
     * @return array{title:string, text:string, url:string, warnings:list<string>}
     */
    public static function page(string $path): array
    {
        $nx = self::nuxt();
        if (!isset($nx['routes'][$path])) {
            throw new RuntimeException('ไม่พบหน้า ' . $path . ' ในเว็บเดิม');
        }
        $lines = [];
        $heads = [];
        $title = '';
        foreach ($nx['routes'][$path] as $cid) {
            if (!isset($nx['chunks'][$cid])) {
                continue;
            }
            $js = self::cached($nx['assets'] . $nx['chunks'][$cid] . '.js', 86400);
            if (!str_contains($js, '_v(')) {
                continue;   // chunk ส่วนประกอบ (เช่น CSS/คอมโพเนนต์กลาง)
            }
            // หัวข้อ: n("h2",…,[t._v("…")])
            if (preg_match_all('~\w\("(h[1-3])"(?:,\{[^\[]{0,300}?\})?,\[\w+\._v\("((?:[^"\\\\]|\\\\.)*)"\)~u', $js, $hm, PREG_SET_ORDER)) {
                foreach ($hm as $h) {
                    $heads[self::jsString($h[2])] = true;
                }
            }
            // ข้อความและลิงก์ตามลำดับที่ปรากฏ
            preg_match_all('~_v\("((?:[^"\\\\]|\\\\.)*)"\)|attrs:\{[^{}]*?href:"((?:[^"\\\\]|\\\\.)*)"~u', $js, $tm, PREG_SET_ORDER);
            foreach ($tm as $t) {
                if (($t[2] ?? '') !== '') {
                    $href = self::jsString($t[2]);
                    if (preg_match('~^https?://~i', $href)) {
                        $lines[] = '- ' . $href;
                    } elseif (str_starts_with($href, '/') && preg_match(self::FILE_EXT_RE, $href)) {
                        $lines[] = '- ' . self::siteBase() . $href;
                    }
                    continue;
                }
                $s = trim(preg_replace('/\s+/u', ' ', self::jsString($t[1])) ?? '');
                if ($s === '' || !preg_match('/\p{L}|\d/u', $s)) {
                    continue;
                }
                if (isset($heads[$s])) {
                    $title = $title ?: $s;
                    $lines[] = "\n## " . $s;
                } else {
                    $lines[] = $s;
                }
            }
        }
        if (!$lines) {
            throw new RuntimeException('หน้านี้ไม่มีข้อความ (อาจเป็นรูปภาพหรือดึงข้อมูลจากระบบอื่น)');
        }
        // รวมบรรทัดสั้นที่ต่อกันเป็นย่อหน้า, คั่นด้วยบรรทัดว่างเมื่อขึ้นหัวข้อใหม่
        $text = preg_replace("/\n{3,}/", "\n\n", trim(implode("\n", $lines))) ?? '';
        $text = preg_replace('/^(## .+)\n(?!\n)/mu', "$1\n", $text) ?? $text;
        $warn = [];
        $res  = self::mirrorLinks($text, $warn);
        return ['title' => $title ?: self::pageLabel($path), 'text' => $res['text'], 'url' => self::siteBase() . $path, 'warnings' => $warn];
    }

    public static function pageLabel(string $path): string
    {
        return [
            '/base/vision' => 'ปรัชญา วิสัยทัศน์ พันธกิจ', '/base/rvc' => 'ประวัติวิทยาลัย', '/base/contact' => 'ติดต่อเรา',
            '/base/administrator' => 'ผู้บริหาร', '/base/department' => 'แผนกวิชา', '/base/law' => 'กฎหมายที่เกี่ยวข้อง',
            '/base/responsibility' => 'อำนาจหน้าที่', '/base/org_chart' => 'โครงสร้างการบริหาร', '/base/parent' => 'สำหรับผู้ปกครอง',
            '/base/college_board' => 'คณะกรรมการสถานศึกษา', '/base/administrator_board' => 'คณะผู้บริหาร',
        ][$path] ?? $path;
    }

    private static function jsString(string $s): string
    {
        $d = json_decode('"' . $s . '"');
        return is_string($d) ? $d : stripcslashes($s);
    }

    /* ------------------------------ ลิงก์ใดๆ ------------------------------ */

    /**
     * ดึงเนื้อหาจากลิงก์ — ลิงก์ของเว็บเดิมจะอ่านผ่าน API/หน้าเนื้อหาคงที่ · เว็บอื่นอ่านจาก HTML
     * @return array{title:string, text:string, image:string, date:?string, ref:?string, category:?string, url:string, warnings:list<string>}
     */
    public static function fromUrl(string $url): array
    {
        $url = trim($url);
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'http://' . ltrim($url, '/');
        }
        $siteHost = strtolower((string) parse_url(self::siteBase(), PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        if ($host === $siteHost || $host === preg_replace('/^www\./', '', $siteHost)) {
            foreach (['news' => 'news', 'activity' => 'activity', 'award' => 'award'] as $seg => $source) {
                if (preg_match('~^/' . $seg . '/(\d+)/?$~', $path, $m)) {
                    $it = self::item($source, (int) $m[1]);
                    $c  = self::compose($it);
                    return ['title' => $it['title'], 'text' => $c['text'], 'image' => $it['image'], 'date' => $it['date'], 'ref' => $it['ref'],
                        'category' => $it['category'], 'url' => $url, 'warnings' => $c['warnings']];
                }
            }
            if (!preg_match(self::FILE_EXT_RE, $path)) {
                $p = self::page(rtrim($path, '/') ?: '/');
                return $p + ['image' => '', 'date' => null, 'ref' => null, 'category' => null];
            }
        }
        if (preg_match(self::FILE_EXT_RE, $path)) {
            throw new RuntimeException('ลิงก์นี้เป็นไฟล์ ไม่ใช่หน้าเว็บ — ใส่ไว้เป็นไฟล์แนบได้เลย');
        }
        $r = self::get($url, 25, 4_194_304);
        if ($r['status'] !== 200) {
            throw new RuntimeException('ดึงหน้าเว็บไม่ได้ (HTTP ' . $r['status'] . ')');
        }
        if (!preg_match('~html|xml|text/plain~i', $r['type'])) {
            throw new RuntimeException('ลิงก์นี้ไม่ใช่หน้าเว็บ (' . $r['type'] . ')');
        }
        $res = self::htmlPage($r['body'], $url);
        $warn = [];
        $m = self::mirrorLinks($res['text'], $warn);
        return ['title' => $res['title'], 'text' => $m['text'], 'image' => $res['image'], 'date' => null, 'ref' => null, 'category' => null, 'url' => $url, 'warnings' => $warn];
    }

    /** อ่านเนื้อหาหลักจากหน้า HTML ทั่วไป */
    private static function htmlPage(string $html, string $base): array
    {
        if (!preg_match('~<meta[^>]+charset~i', $html)) {
            $html = '<meta charset="utf-8">' . $html;
        }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);
        $title = trim((string) ($xp->query('//meta[@property="og:title"]/@content')->item(0)?->nodeValue
            ?? $xp->query('//title')->item(0)?->textContent ?? ''));
        $image = (string) ($xp->query('//meta[@property="og:image"]/@content')->item(0)?->nodeValue ?? '');
        foreach (['//script', '//style', '//noscript', '//nav', '//header', '//footer', '//form', '//iframe', '//svg', '//aside'] as $q) {
            foreach (iterator_to_array($xp->query($q)) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }
        $root = $xp->query('//article')->item(0) ?? $xp->query('//main')->item(0) ?? $xp->query('//*[@id="content"]')->item(0) ?? $xp->query('//body')->item(0);
        $out = [];
        $walk = function (DOMNode $n) use (&$walk, &$out, $base): void {
            foreach ($n->childNodes as $c) {
                if ($c instanceof DOMText) {
                    $t = preg_replace('/\s+/u', ' ', $c->textContent) ?? '';
                    if (trim($t) !== '') {
                        $out[] = ['t', $t];
                    }
                    continue;
                }
                if (!$c instanceof DOMElement) {
                    continue;
                }
                $tag = strtolower($c->tagName);
                if (in_array($tag, ['h1', 'h2', 'h3', 'h4'], true)) {
                    $out[] = ['b', "\n\n## " . trim(preg_replace('/\s+/u', ' ', $c->textContent) ?? '') . "\n"];
                } elseif ($tag === 'a' && $c->getAttribute('href') !== '') {
                    $href  = self::resolve($c->getAttribute('href'), $base);
                    $label = trim(preg_replace('/\s+/u', ' ', $c->textContent) ?? '');
                    $out[] = ['t', $href && preg_match('~^https?://~', $href) ? ($label !== '' && $label !== $href ? ' [' . str_replace(['[', ']'], '', $label) . '](' . $href . ') ' : ' ' . $href . ' ') : $label];
                } elseif ($tag === 'li') {
                    $out[] = ['b', "\n- "];
                    $walk($c);
                } elseif ($tag === 'br') {
                    $out[] = ['b', "\n"];
                } elseif (in_array($tag, ['p', 'div', 'section', 'tr', 'ul', 'ol', 'table', 'blockquote'], true)) {
                    $out[] = ['b', "\n\n"];
                    $walk($c);
                    $out[] = ['b', "\n\n"];
                } else {
                    $walk($c);
                }
            }
        };
        if ($root) {
            $walk($root);
        }
        $text = implode('', array_column($out, 1));
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return ['title' => $title, 'text' => mb_substr(trim($text), 0, 30000), 'image' => $image ? (string) self::resolve($image, $base) : ''];
    }

    private static function resolve(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(javascript|mailto|tel):~i', $href)) {
            return preg_match('~^(mailto|tel):~i', $href) ? $href : null;
        }
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'http') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'http') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');
        return $origin . $dir . $href;
    }

    /** รายการที่นำเข้าแล้ว [ref => news id] */
    /**
     * ภาพสไลด์ที่เปิดใช้อยู่บนเว็บเดิม (API: /slide?status=1)
     * @return list<array{id:int, title:string, image:string, date:?string}>
     */
    public static function slides(): array
    {
        $rows = self::api('slide?status=1', 120);
        $out = [];
        foreach (array_is_list($rows) ? $rows : [] as $r) {
            $img = trim((string) ($r['thumbnail'] ?? ''));
            if (!empty($r['slide_id']) && preg_match('~^https?://~i', $img)) {
                $ts = !empty($r['dt']) ? strtotime((string) $r['dt']) : false;
                $out[] = ['id' => (int) $r['slide_id'], 'title' => trim((string) ($r['slide'] ?? '')) ?: 'ภาพสไลด์', 'image' => $img, 'date' => $ts ? date('Y-m-d H:i:s', $ts) : null];
            }
        }
        return $out;
    }

    /**
     * นำเข้าภาพสไลด์จากเว็บเดิม (ทำสำเนารูปลง uploads/legacy/) — ข้ามรายการที่เคยนำเข้าแล้ว
     * เรียงตามลำดับเดิมของเว็บเดิม (ใหม่ก่อน) ต่อท้ายสไลด์ที่มีอยู่
     * @return array{added:int, skipped:int, failed:list<string>}
     */
    public static function importSlides(?int $userId): array
    {
        $done = array_flip(array_column(Database::all('SELECT source_ref FROM slides WHERE source_ref IS NOT NULL'), 'source_ref'));
        $sort = (int) Database::value('SELECT COALESCE(MAX(sort), 0) FROM slides');
        $added = $skipped = 0;
        $failed = [];
        foreach (self::slides() as $s) {
            $ref = 'slide:' . $s['id'];
            if (isset($done[$ref])) {
                $skipped++;
                continue;
            }
            try {
                $path = self::mirrorFile($s['image'], true);
            } catch (Throwable $ex) {
                $failed[] = $s['title'] . ' — ' . $ex->getMessage();
                continue;
            }
            Database::insert('slides', [
                'title' => mb_substr($s['title'], 0, 255), 'image' => $path, 'sort' => ++$sort, 'is_enabled' => 1,
                'source_ref' => $ref, 'created_by' => $userId,
            ]);
            $added++;
        }
        return compact('added', 'skipped', 'failed');
    }

    public static function importedRefs(): array
    {
        try {
            return array_column(Database::all('SELECT id, source_ref FROM news WHERE source_ref IS NOT NULL'), 'id', 'source_ref');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * นำเข้ารายการจากเว็บเดิมเป็นข่าวในเว็บนี้ (ไม่ผ่าน AI) — ทำสำเนารูปหน้าปกและไฟล์แนบ
     * @return array{id:int, warnings:list<string>}
     */
    public static function importAsNews(string $source, int $id, int $authorId, bool $publish): array
    {
        $it = self::item($source, $id);
        if ($exist = Database::value('SELECT id FROM news WHERE source_ref = ?', [$it['ref']])) {
            return ['id' => (int) $exist, 'warnings' => [], 'skipped' => true];
        }
        $c = self::compose($it);
        $warn = $c['warnings'];
        $cover = null;
        if ($it['image'] !== '') {
            try {
                $cover = self::isLegacyUrl($it['image']) ? self::mirrorFile($it['image'], true) : null;
            } catch (Throwable $ex) {
                $warn[] = 'รูปหน้าปก: ' . $ex->getMessage();
            }
        }
        $plain = preg_replace('~\[([^\]]+)\]\([^)]+\)~u', '$1', $c['text']) ?? $c['text'];
        $newId = Database::insert('news', [
            'title'        => mb_substr($it['title'] ?: 'ไม่มีหัวข้อ', 0, 255),
            'excerpt'      => mb_substr(str_limit(preg_replace('/^(##|-)\s+.*$/mu', '', $plain) ?? $plain, 220), 0, 500),
            'body'         => $c['text'],
            'cover'        => $cover,
            'category'     => $it['category'],
            'status'       => $publish ? 'published' : 'draft',
            'published_at' => $it['date'] ?? date('Y-m-d H:i:s'),
            'author_id'    => $authorId,
            'source_ref'   => $it['ref'],
        ]);
        return ['id' => $newId, 'warnings' => $warn, 'skipped' => false];
    }
}
