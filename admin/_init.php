<?php
declare(strict_types=1);

/* โหลดทุกหน้าของระบบจัดการ: bootstrap + layout (แถบเมนูซ้ายตามดีไซน์) */

require dirname(__DIR__) . '/app/bootstrap.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

const ADMIN_ICONS = [
    'dash' => 'M4 4h7v7H4zM13 4h7v4h-7zM13 10h7v10h-7zM4 13h7v7H4z',
    'news' => 'M4 5h13v14H4zM17 8h3v11H17M7 9h7M7 13h7',
    'page' => 'M6 3h9l4 4v14H6zM14 3v5h5',
    'pop'  => 'M4 4h16v12H8l-4 4z',
    'slide' => 'M3 5h18v12H3zM8 21h8M7 13l3-3 3 3 2-2 3 3',
    'unit' => 'M12 3v5M5 13V9h14v4M3 13h4v5H3zM10 13h4v5h-4zM17 13h4v5h-4z',
    'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c1-4 4-6 8-6s7 2 8 6',
    'set'  => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19 12h2M3 12h2M12 3v2M12 19v2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4',
    'db'   => 'M12 3c4.4 0 8 1.3 8 3v12c0 1.7-3.6 3-8 3s-8-1.3-8-3V6c0-1.7 3.6-3 8-3zM4 6c0 1.7 3.6 3 8 3s8-1.3 8-3M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3',
    'sys'  => 'M9 12l2 2 4-4M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z',
    'out'  => 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11',
    'ext'  => 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
    'imp'  => 'M12 3v12M7 10l5 5 5-5M4 17v3h16v-3',
    'ai'   => 'M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8zM18 15l.9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9z',
];

/** เมนู: [key, ชื่อ, ไอคอน, ไฟล์, กลุ่ม, เฉพาะ admin] */
const ADMIN_MENU = [
    ['dash', 'ภาพรวม', 'dash', 'index.php', null, false],
    ['news', 'ข่าวและประกาศ', 'news', 'news.php', 'เนื้อหา', false],
    ['pages', 'หน้าเว็บ', 'page', 'pages.php', null, true],
    ['popups', 'ป๊อปอัพ', 'pop', 'popups.php', null, false],
    ['slides', 'ภาพสไลด์หน้าแรก', 'slide', 'slides.php', null, true],
    ['units', 'เว็บไซต์ย่อยหน่วยงาน', 'unit', 'units.php', 'โครงสร้าง', true],
    ['users', 'ผู้ใช้และสิทธิ์', 'user', 'users.php', 'ผู้ดูแลระบบ', true],
    ['settings', 'ตั้งค่าเว็บไซต์', 'set', 'settings.php', null, true],
    ['ai', 'ผู้ช่วย AI', 'ai', 'ai.php', null, true],
    ['legacy', 'นำเข้าจากเว็บเดิม', 'imp', 'legacy.php', null, true],
    ['migrations', 'Migrations', 'db', 'migrations.php', null, true],
    ['system', 'ตรวจสอบระบบ', 'sys', 'system.php', null, true],
];

function icon(string $key, int $size = 20): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . (ADMIN_ICONS[$key] ?? '') . '"/></svg>';
}

function status_pill(array $s): string
{
    return '<span class="pill" style="background:' . e($s[1]) . ';color:' . e($s[2]) . '">' . e($s[0]) . '</span>';
}

function admin_header(string $title, string $active, ?string $crumb = null): void
{
    $u = Auth::user();
    $pendingMig = 0;
    if (Auth::isAdmin()) {
        try {
            $pendingMig = count((new Migrator(Database::pdo(), BASE_PATH . '/migrations'))->pending());
        } catch (Throwable) {
        }
    }
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($title) ?> · ระบบจัดการเว็บไซต์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anuphan:wght@500;600&family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>?v=6">
<link rel="icon" type="image/png" href="<?= e(url('assets/seal.png')) ?>">
</head>
<body>
<div class="shell">
<aside class="side" id="side">
  <div class="brand"><img class="seal" src="<?= e(url('assets/seal.png')) ?>" alt="ตราสำนักงานคณะกรรมการการอาชีวศึกษา">
    <div class="hd">ระบบจัดการเว็บไซต์<br><span><?= e(Settings::get('site_short', 'วอศ.ร้อยเอ็ด')) ?></span></div>
    <button class="menu-x" type="button" aria-label="ปิดเมนู" onclick="document.body.classList.remove('nav-open')">✕</button></div>
  <nav aria-label="เมนูระบบจัดการ">
  <?php foreach (ADMIN_MENU as [$key, $label, $ic, $file, $group, $adminOnly]):
      if ($adminOnly && !Auth::isAdmin()) { continue; }
      if ($group): ?><div class="grp"><?= e($group) ?></div><?php endif; ?>
    <a href="<?= e(url('admin/' . $file)) ?>" class="<?= $key === $active ? 'on' : '' ?>" <?= $key === $active ? 'aria-current="page"' : '' ?>>
      <?= icon($ic) ?><span><?= e($label) ?></span>
      <?php if ($key === 'migrations' && $pendingMig > 0): ?><b class="badge" title="รอติดตั้ง"><?= $pendingMig ?></b><?php endif; ?>
    </a>
  <?php endforeach; ?>
  </nav>
  <div class="who">เข้าสู่ระบบในชื่อ<br><b><?= e($u['display_name'] ?: $u['username']) ?></b><br>
    <?= e(Auth::ROLES[$u['role']] ?? $u['role']) ?><?= $u['unit_name'] ? ': ' . e($u['unit_name']) : '' ?>
    <div class="who-a"><a href="<?= e(url()) ?>" target="_blank" rel="noopener"><?= icon('ext', 16) ?> ดูเว็บไซต์</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= Csrf::field() ?><button type="submit"><?= icon('out', 16) ?> ออกจากระบบ</button></form></div>
  </div>
</aside>
<main class="main">
  <div class="mobbar"><button type="button" class="btn ghost sm" onclick="document.body.classList.add('nav-open')">☰ เมนู</button><b><?= e($title) ?></b></div>
  <?php if ($crumb): ?><div class="crumb"><?= e($crumb) ?></div><?php endif; ?>
  <?php foreach (take_flashes() as $f): ?>
    <div class="msg <?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
</main>
</div>
<script src="<?= e(url('assets/admin.js')) ?>?v=2"></script>
<?php if (!empty($GLOBALS['_ai_panel'])): ?><script src="<?= e(url('assets/ai.js')) ?>?v=2"></script><?php endif; ?>
<?= agent_widget() ?>
</body>
</html>
<?php
}

/** ผู้ใช้คนนี้ใช้ผู้ช่วย AI ได้หรือไม่ */
function ai_can_use(): bool
{
    return Auth::isAdmin() || (Auth::user() !== null && Settings::get('ai_allow_editors', '1') === '1');
}

/**
 * ปุ่มเปิดแผงผู้ช่วย AI สำหรับหน้าแก้ไขเนื้อหา (แสดงเฉพาะเมื่อมีโมเดลที่เปิดใช้งาน)
 * @param string $kind   news | page | popup
 * @param array  $fields ช่องในฟอร์ม เช่น ['title' => '#nt', 'body' => '#body', 'excerpt' => '#ex']
 */
function ai_button(string $kind, array $fields): string
{
    if (!ai_can_use()) {
        return '';
    }
    $models = Ai::enabledModels();
    if (!$models) {
        return Auth::isAdmin()
            ? '<a class="btn ghost" href="' . e(url('admin/ai.php')) . '" title="ยังไม่ได้เปิดใช้งานโมเดล AI">' . icon('ai', 18) . ' ตั้งค่าผู้ช่วย AI</a>'
            : '';
    }
    $GLOBALS['_ai_panel'] = true;
    $cfg = [
        'endpoint' => url('admin/ai_api.php'),
        'csrf'     => Csrf::token(),
        'legacy'   => LegacySite::siteBase(),
        'cover'    => $kind === 'news',
        'kind'     => $kind,
        'fields'   => $fields,
        'models'   => array_map(fn($m) => [
            'id' => (int) $m['id'],
            'name' => ($m['label'] ?: $m['model_id']) . ($m['is_free'] ? ' (ฟรี)' : ''),
            'provider' => $m['provider_name'],
            'default' => (bool) $m['is_default'],
        ], $models),
        'tasks'    => array_map(fn($k) => Ai::TASKS[$k][0], array_combine($keys = $kind === 'popup'
            ? ['popup', 'import', 'improve', 'proofread', 'shorten', 'free']
            : array_values(array_diff(array_keys(Ai::TASKS), ['popup'])), $keys)),
    ];
    return '<button class="btn ghost ai-open" type="button" data-ai="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '" aria-haspopup="dialog">'
        . icon('ai', 18) . ' ผู้ช่วย AI</button>';
}

/** ปุ่มลอย "สนทนากับผู้ช่วย AI" มุมขวาล่างทุกหน้าในระบบจัดการ */
function agent_widget(): string
{
    if (!Auth::user() || !ai_can_use()) {
        return '';
    }
    $models = Ai::enabledModels();
    if (!$models && !Auth::isAdmin()) {
        return '';
    }
    $u = Auth::user();
    $cfg = [
        'endpoint' => url('admin/agent_api.php'),
        'csrf'     => Csrf::token(),
        'user'     => (int) $u['id'],
        'setup'    => $models ? null : url('admin/ai.php'),
        'models'   => array_map(fn($m) => [
            'id' => (int) $m['id'],
            'name' => ($m['label'] ?: $m['model_id']) . ($m['is_free'] ? ' (ฟรี)' : ''),
            'default' => (bool) $m['is_default'],
        ], $models),
        'hints'    => Auth::isAdmin()
            ? ['สรุปภาพรวมระบบตอนนี้', 'มีข่าวไหนรออนุมัติบ้าง', 'ร่างข่าวกิจกรรมจากประเด็นที่ฉันจะพิมพ์', 'สร้างป๊อปอัพประกาศวันหยุด']
            : ['ข่าวของฉันมีอะไรบ้าง', 'ร่างข่าวกิจกรรมจากประเด็นที่ฉันจะพิมพ์', 'สร้างป๊อปอัพประกาศ'],
    ];
    return '<button type="button" class="agent-fab" id="agent-fab" aria-haspopup="dialog" aria-controls="agent" title="สนทนากับผู้ช่วย AI">'
        . icon('ai', 22) . '<span>ผู้ช่วย AI</span></button>'
        . '<section class="agent" id="agent" role="dialog" aria-label="ผู้ช่วย AI" hidden data-cfg="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"></section>'
        . '<script src="' . e(url('assets/agent.js')) . '?v=2"></script>';
}
