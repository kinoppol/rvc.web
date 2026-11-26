<?php
declare(strict_types=1);

/* ข้อมูลกลางของเนื้อหา: กลุ่มสาขา หมวดข่าว สถานะ และการดึงข้อมูลที่ใช้หลายหน้า */

/** กลุ่มสาขา => [ชื่อ, สีหลัก, สีอ่อน(บนพื้นเข้ม), สีพื้น, ไอคอน SVG path] */
const UNIT_GROUPS = [
    'biz'  => ['บริหารธุรกิจ', '#0e7a5a', '#4cc39a', '#e3f3ed', 'M3 21h18M5 21V8l7-5 7 5v13M9 21v-6h6v6'],
    'home' => ['คหกรรม', '#b0442a', '#f08a6c', '#f9e8e2', 'M6 14h12l-1 7H7zM8 14a4 4 0 1 1 8 0M12 3v3'],
    'tour' => ['ท่องเที่ยวและการโรงแรม', '#9a5b00', '#e6a93a', '#fbf0dc', 'M3 21h18M4 21V10h16v11M8 10V6h8v4M9 14h2M13 14h2M9 17h2M13 17h2'],
    'it'   => ['เทคโนโลยีสารสนเทศ', '#5b3bb0', '#a98cf0', '#ece7f8', 'M3 5h18v11H3zM8 20h8M12 16v4'],
    'org'  => ['หน่วยงานสนับสนุน', '#1f5aa6', '#8fb4e6', '#e8f0fb', 'M12 3v5M5 13V9h14v4M3 13h4v5H3zM10 13h4v5h-4zM17 13h4v5h-4z'],
];

const UNIT_KINDS = ['department' => 'แผนกวิชา', 'division' => 'ฝ่าย', 'work' => 'งาน'];

const NEWS_CATEGORIES = [
    'pr'          => 'ประชาสัมพันธ์',
    'activity'    => 'กิจกรรม',
    'procurement' => 'จัดซื้อจัดจ้าง',
    'jobs'        => 'รับสมัครงาน',
];

const POPUP_PLACEMENTS = [
    'home' => 'หน้าแรกเท่านั้น',
    'site' => 'ทุกหน้าของเว็บไซต์หลัก',
    'all'  => 'หน้าแรก + เว็บไซต์ย่อยทุกหน่วยงาน',
];

/** เงื่อนไขข่าวที่เผยแพร่แล้ว (ใช้กับ alias n) */
const NEWS_PUBLIC = "n.status = 'published' AND n.published_at <= NOW()";

function unit_group(?string $key): array
{
    [$label, $color, $light, $tint, $icon] = UNIT_GROUPS[$key] ?? UNIT_GROUPS['org'];
    return compact('label', 'color', 'light', 'tint', 'icon');
}

/** ชื่อเต็มของหน่วยงาน เช่น "แผนกวิชาการบัญชี" */
function unit_title(array $u): string
{
    return ($u['kind'] ?? '') === 'department' ? 'แผนกวิชา' . $u['name'] : $u['name'];
}

function unit_url(array $u): string
{
    return url('unit.php', ['u' => $u['slug']]);
}

function news_url(array $n): string
{
    return url('news_view.php', ['id' => $n['id']]);
}

function page_url(string $slug): string
{
    return url('page.php', ['p' => $slug]);
}

/** ป้ายสถานะข่าว [ข้อความ, พื้น, ตัวอักษร] */
function news_state(array $n): array
{
    return match (true) {
        $n['status'] === 'draft'   => ['ฉบับร่าง', '#e9eef6', '#4a5a74'],
        $n['status'] === 'pending' => ['รออนุมัติ', '#e8f0fb', '#1f5aa6'],
        $n['published_at'] !== null && strtotime($n['published_at']) > time() => ['ตั้งเวลาไว้', '#fcefd9', '#9a5800'],
        default => ['เผยแพร่แล้ว', '#e2f3e8', '#1d7a3e'],
    };
}

/** ป้ายสถานะป๊อปอัพ */
function popup_state(array $p): array
{
    $now = time();
    return match (true) {
        (bool) $p['is_draft']               => ['ฉบับร่าง', '#e9eef6', '#4a5a74'],
        !$p['is_enabled']                   => ['ปิดใช้งาน', '#e9eef6', '#4a5a74'],
        strtotime($p['end_at']) < $now      => ['หมดเวลาแสดง', '#fbe5e3', '#b42318'],
        strtotime($p['start_at']) > $now    => ['ตั้งเวลาไว้', '#fcefd9', '#9a5800'],
        default                             => ['กำลังแสดง', '#e2f3e8', '#1d7a3e'],
    };
}

/** ช่วงเวลาแบบย่อ เช่น 1 – 7 ธ.ค. 2569 */
function date_range(string $a, string $b): string
{
    $ta = strtotime($a);
    $tb = strtotime($b);
    if (date('Y-m', $ta) === date('Y-m', $tb)) {
        return date('j', $ta) . ' – ' . thai_date($b);
    }
    return date('Y', $ta) === date('Y', $tb)
        ? date('j', $ta) . ' ' . TH_MONTHS_SHORT[(int) date('n', $ta) - 1] . ' – ' . thai_date($b)
        : thai_date($a) . ' – ' . thai_date($b);
}

/**
 * ป๊อปอัพที่ต้องแสดงในหน้าปัจจุบัน
 * @param string $where home = หน้าแรก · site = หน้าอื่นของเว็บหลัก · unit = เว็บไซต์ย่อยหน่วยงาน
 */
function active_popup(string $where): ?array
{
    $allowed = match ($where) {
        'home'  => ['home', 'site', 'all'],
        'site'  => ['site'],
        'unit'  => ['all'],
        default => [],
    };
    if (!$allowed) {
        return null;
    }
    $in = implode(',', array_fill(0, count($allowed), '?'));
    try {
        return Database::one("SELECT * FROM popups WHERE is_enabled = 1 AND is_draft = 0
            AND start_at <= NOW() AND end_at >= NOW() AND placement IN ($in)
            ORDER BY start_at DESC, id DESC LIMIT 1", $allowed);
    } catch (Throwable) {
        return null;
    }
}

/** ข่าวที่เผยแพร่ (กรองหมวด/หน่วยงานได้) */
function latest_news(int $limit, ?string $category = null, ?int $unitId = null, bool $withCover = false): array
{
    $where  = [NEWS_PUBLIC];
    $params = [];
    if ($category !== null) {
        $where[]  = 'n.category = ?';
        $params[] = $category;
    }
    if ($unitId !== null) {
        $where[]  = 'n.unit_id = ?';
        $params[] = $unitId;
    }
    if ($withCover) {
        $where[] = "n.cover IS NOT NULL AND n.cover <> ''";
    }
    return Database::all('SELECT n.*, u.name AS unit_name, u.kind AS unit_kind, u.group_key
        FROM news n LEFT JOIN units u ON u.id = n.unit_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY n.pinned DESC, n.published_at DESC, n.id DESC LIMIT ' . max(1, $limit), $params);
}

/** ป้ายหมวดของข่าว: ใช้ชื่อแผนก (ถ้ามี) และสีกลุ่ม */
function news_tag(array $n): array
{
    if (!empty($n['unit_name']) && ($n['unit_kind'] ?? '') === 'department') {
        $g = unit_group($n['group_key'] ?? 'org');
        return [$n['unit_name'], $g['color'], $g['tint']];
    }
    return [NEWS_CATEGORIES[$n['category']] ?? 'ข่าว', '#1f5aa6', '#e3eaf4'];
}

/** แผนกวิชาแยกตามกลุ่ม */
function departments_by_group(): array
{
    $out = [];
    foreach (Database::all("SELECT * FROM units WHERE kind = 'department' AND is_active = 1 ORDER BY sort, name") as $u) {
        $out[$u['group_key']][] = $u;
    }
    return array_intersect_key(array_replace(array_fill_keys(array_keys(UNIT_GROUPS), []), $out), $out);
}

/** ฝ่าย พร้อมงานในสังกัด (สำหรับเมนูหน่วยงาน) */
function divisions_with_works(): array
{
    $rows = Database::all("SELECT * FROM units WHERE kind IN ('division','work') AND is_active = 1 ORDER BY sort, name");
    $divs = [];
    foreach ($rows as $r) {
        if ($r['kind'] === 'division') {
            $divs[$r['id']] = $r + ['works' => []];
        }
    }
    foreach ($rows as $r) {
        if ($r['kind'] === 'work' && isset($divs[$r['parent_id']])) {
            $divs[$r['parent_id']]['works'][] = $r;
        }
    }
    return array_values($divs);
}

/** นับผู้เข้าชม (ครั้งละหนึ่งต่อ session) */
function visit_counter(): int
{
    try {
        if (empty($_SESSION['_counted'])) {
            Database::run("INSERT INTO settings (`key`, `value`) VALUES ('visit_count', '1')
                           ON DUPLICATE KEY UPDATE `value` = CAST(`value` AS UNSIGNED) + 1");
            $_SESSION['_counted'] = 1;
        }
        return (int) Database::value("SELECT `value` FROM settings WHERE `key` = 'visit_count'");
    } catch (Throwable) {
        return 0;
    }
}

/** ใส่ข่าวตัวอย่าง (เลือกได้ตอนติดตั้ง) — ใส่เฉพาะเมื่อยังไม่มีข่าว */
function seed_sample_news(PDO $db, int $authorId): int
{
    if ((int) $db->query('SELECT COUNT(*) FROM news')->fetchColumn() > 0) {
        return 0;
    }
    $unit = fn(string $slug) => ($id = $db->query('SELECT id FROM units WHERE slug = ' . $db->quote($slug))->fetchColumn()) ? (int) $id : null;
    $items = [
        ['ประกาศรับสมัครนักเรียน นักศึกษา ปีการศึกษา 2570 รอบโควตา', 'pr', null, 1, 1],
        ['นักศึกษาฝึกประสบการณ์วิชาชีพในสถานประกอบการ', 'activity', 'hotel', 0, 3],
        ['อบรมทักษะดิจิทัลสำหรับชุมชน', 'activity', 'information-technology', 0, 7],
        ['ผลงานการแข่งขันทักษะวิชาชีพระดับภาค', 'pr', 'food-nutrition', 0, 9],
        ['บริการทำบัญชีครัวเรือนแก่ชุมชน', 'activity', 'accounting', 0, 15],
        ['ประกาศผู้ชนะการเสนอราคา', 'procurement', null, 0, 2],
        ['แผนการจัดซื้อจัดจ้าง ปีงบประมาณ 2570', 'procurement', null, 0, 8],
        ['รับสมัครครูอัตราจ้าง', 'jobs', null, 0, 6],
        ['ประกาศรายชื่อผู้มีสิทธิ์สอบคัดเลือก', 'jobs', null, 0, 12],
    ];
    $st = $db->prepare("INSERT INTO news (title, excerpt, body, category, unit_id, status, published_at, pinned, author_id)
                        VALUES (?,?,?,?,?,'published', NOW() - INTERVAL ? DAY, ?, ?)");
    foreach ($items as [$title, $cat, $slug, $pin, $ago]) {
        $body = "[ตัวอย่าง] {$title}\n\nเนื้อหาข่าวตัวอย่าง แก้ไขหรือลบได้จากระบบจัดการ › ข่าวและประกาศ";
        $st->execute(['[ตัวอย่าง] ' . $title, 'เนื้อหาข่าวตัวอย่าง แก้ไขหรือลบได้จากระบบจัดการ', $body, $cat, $slug ? $unit($slug) : null, $ago, $pin, $authorId]);
    }
    return count($items);
}
