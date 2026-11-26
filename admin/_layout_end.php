<?php
/**
 * Layout ท้ายสำหรับหน้า admin (เรียก include ก่อนหน้า ) — ต้องมี $adminTitle
 * เรียกหลังแสดงเนื้อหาทั้งหมด: include admin/_layout_end.php
 */
$authUser = Auth::user();
$navActive = $navActive ?? '';
$menu = [
    'dashboard'  => ['index.php',      'แผงควบคุม'],
    'migrations' => ['migrations.php', 'Migrations'],
    'news'       => ['news.php',       'ข่าวสาร'],
    'popups'     => ['popups.php',     'ป๊อปอัพประกาศ'],
    'units'      => ['units.php',      'หน่วยงาน'],
    'settings'   => ['settings.php',   'การตั้งค่า'],
];
?>
</main>
  <div class="foot">
    เข้าสู่ระบบ: <?= e($authUser['username'] ?? '') ?><br>
    <a href="<?= e(url('index.php')) ?>" style="color:#9db8dd">← กลับหน้าเว็บ</a>
  </div>
</aside>
</div>
</body>
</html>
