<?php
/** ส่วนท้ายหน้าเว็บสาธารณะ — ปิด <main> เอง + โมดัลป๊อปอัพ (ต้องมี $popupScope) */
$popup = active_popup($popupScope ?? 'home');
?>
</main>
<footer class="site">
  <div class="inner">
    <span><?= e(Settings::get('site_footer', '© ' . Settings::get('site_name', 'เว็บไซต์'))) ?></span>
    <span><a href="<?= e(url('admin/index.php')) ?>">เข้าสู่ระบบผู้ดูแล</a></span>
  </div>
</footer>
<?php if ($popup): ?>
<div id="popup" role="dialog" aria-modal="true">
  <div class="box">
    <button class="close" onclick="document.getElementById('popup').style.display='none'" aria-label="ปิด">×</button>
    <h2><?= e($popup['title']) ?></h2>
    <?= $popup['body'] !== '' ? '<p>' . nl2br(e($popup['body'])) . '</p>' : '' ?>
    <?php if ($popup['link_url'] !== ''): ?>
      <a class="cta" href="<?= e($popup['link_url']) ?>"
         <?= str_starts_with($popup['link_url'], 'http') ? 'target="_blank" rel="noopener"' : '' ?>>ดูรายละเอียด ›</a>
    <?php endif; ?>
  </div>
</div>
<script>
  setTimeout(function () { document.getElementById('popup').style.display = 'grid'; }, 700);
  document.getElementById('popup').addEventListener('click', function (ev) {
    if (ev.target === this) this.style.display = 'none';
  });
</script>
<?php endif; ?>
</body>
</html>
