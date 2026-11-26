<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
$me = Auth::require();

$scope  = $me['role'] === 'admin' ? '' : ' AND n.author_id = ' . (int) $me['id'];
$counts = Database::one("SELECT
    SUM(n.status = 'published' AND n.published_at <= NOW() AND n.published_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month,
    SUM(n.status = 'published' AND n.published_at > NOW()) AS scheduled,
    SUM(n.status = 'draft') AS drafts,
    SUM(n.status = 'pending') AS pending
    FROM news n WHERE 1 = 1{$scope}") ?? [];
$recent = Database::all("SELECT n.*, u.name AS unit_name FROM news n LEFT JOIN units u ON u.id = n.unit_id WHERE 1 = 1{$scope} ORDER BY n.updated_at DESC LIMIT 6");
$popups = Database::all('SELECT * FROM popups WHERE is_draft = 0 AND is_enabled = 1 AND end_at >= NOW() ORDER BY start_at LIMIT 4');
$pendingMig = Auth::isAdmin() ? (new Migrator(Database::pdo(), BASE_PATH . '/migrations'))->pending() : [];

admin_header('ภาพรวม', 'dash');
?>
<div class="head">
  <div><h1>สวัสดี <?= e($me['display_name'] ?: $me['username']) ?></h1>
  <p><?= e(thai_date(time(), 'full')) ?></p></div>
  <div class="acts"><a class="btn big" href="<?= e(url('admin/news.php', ['act' => 'new'])) ?>">+ เขียนข่าวใหม่</a></div>
</div>

<?php if ($pendingMig): ?>
  <div class="msg warn">มีการปรับปรุงโครงสร้างฐานข้อมูลรอติดตั้ง <?= count($pendingMig) ?> รายการ — <a href="<?= e(url('admin/migrations.php')) ?>">ไปที่เมนู Migrations</a></div>
<?php endif; ?>

<div class="stats">
  <a class="stat" href="<?= e(url('admin/news.php')) ?>"><div class="l">เผยแพร่เดือนนี้</div><div class="v"><?= (int) ($counts['month'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'scheduled'])) ?>"><div class="l">ตั้งเวลาไว้</div><div class="v" style="color:var(--warn)"><?= (int) ($counts['scheduled'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'draft'])) ?>"><div class="l">ฉบับร่าง</div><div class="v" style="color:var(--mute)"><?= (int) ($counts['drafts'] ?? 0) ?></div></a>
  <a class="stat" href="<?= e(url('admin/news.php', ['status' => 'pending'])) ?>"><div class="l">รออนุมัติ</div><div class="v" style="color:var(--blue)"><?= (int) ($counts['pending'] ?? 0) ?></div></a>
</div>

<div class="cols">
  <div class="wide">
    <section class="tbl">
      <div class="ttl">ข่าวที่แก้ไขล่าสุด <a class="small" href="<?= e(url('admin/news.php')) ?>">ดูทั้งหมด ›</a></div>
      <div class="tw"><table>
        <tbody>
        <?php if (!$recent): ?><tr><td class="empty">ยังไม่มีข่าว — <a href="<?= e(url('admin/news.php', ['act' => 'new'])) ?>">เขียนข่าวแรก</a></td></tr><?php endif; ?>
        <?php foreach ($recent as $n): ?>
          <tr class="row"><td><div class="ncell"><div class="thumb" style="<?= $n['cover'] ? 'background-image:url(' . e(upload_url($n['cover'])) . ')' : '' ?>"></div>
            <div><a href="<?= e(url('admin/news.php', ['act' => 'edit', 'id' => $n['id']])) ?>"><b><?= e($n['title']) ?></b></a>
            <div class="small mute"><?= e(NEWS_CATEGORIES[$n['category']] ?? '') ?><?= $n['unit_name'] ? ' · ' . e($n['unit_name']) : '' ?> · แก้ไข <?= e(thai_date($n['updated_at'], 'time')) ?></div></div></div></td>
            <td class="r"><?= status_pill(news_state($n)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  </div>
  <div class="narrow">
    <section class="tbl">
      <div class="ttl">ป๊อปอัพที่กำลังแสดง / ตั้งเวลาไว้ <a class="small" href="<?= e(url('admin/popups.php')) ?>">จัดการ ›</a></div>
      <div class="tw"><table><tbody>
        <?php if (!$popups): ?><tr><td class="empty">ไม่มีป๊อปอัพที่กำลังใช้งาน</td></tr><?php endif; ?>
        <?php foreach ($popups as $p): ?>
          <tr><td><b><?= e($p['title']) ?></b><div class="small mute"><?= e(date_range($p['start_at'], $p['end_at'])) ?></div></td>
            <td class="r"><?= status_pill(popup_state($p)) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    </section>
    <section class="card">
      <h2>ลัด</h2>
      <div style="display:grid;gap:8px">
        <a href="<?= e(url('admin/popups.php', ['act' => 'new'])) ?>">+ ตั้งป๊อปอัพเทศกาล</a>
        <?php if (Auth::isAdmin()): ?>
          <a href="<?= e(url('admin/settings.php')) ?>">แก้ไขสารจากผู้อำนวยการ / ข้อมูลติดต่อ</a>
          <a href="<?= e(url('admin/units.php')) ?>">จัดการแผนกวิชาและหน่วยงาน</a>
          <a href="<?= e(url('admin/users.php')) ?>">เพิ่มผู้ใช้ให้หน่วยงาน</a>
          <a href="<?= e(url('install.php')) ?>">เปิดตัวติดตั้ง (โหมดดูแลระบบ)</a>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php admin_footer();
