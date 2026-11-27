<?php
/**
 * ส่วนท้ายเว็บไซต์ + ป๊อปอัพ (แสดงวันละ 1 ครั้งต่อผู้เข้าชม)
 * @var string $popupWhere
 */
$siteName = Settings::get('site_name');
$popup    = active_popup($popupWhere ?? 'site');
$visits   = visit_counter();
$address  = Settings::get('address', '[ที่อยู่วิทยาลัย]');
$map      = Settings::get('map_embed_url');
?>
</main>

<footer class="site-foot">
  <div class="wrap foot-links">
    <span class="t">หน่วยงานที่เกี่ยวข้อง</span>
    <a class="ul" href="https://www.moe.go.th" target="_blank" rel="noopener">กระทรวงศึกษาธิการ</a>
    <a class="ul" href="https://www.vec.go.th" target="_blank" rel="noopener">สำนักงานคณะกรรมการการอาชีวศึกษา</a>
    <a class="ul" href="https://www.gprocurement.go.th" target="_blank" rel="noopener">ระบบ e-GP</a>
  </div>
  <div class="wrap foot-grid">
    <div><div class="hd ft"><?= e($siteName) ?></div>
      <p class="addr"><?= nl2br(e($address), false) ?>
        <?php if ($p = Settings::get('phone')): ?><br>โทร <?= e($p) ?><?php endif; ?>
        <?php if ($m = Settings::get('email')): ?><br><a href="mailto:<?= e($m) ?>"><?= e($m) ?></a><?php endif; ?>
        <?php if ($fb = Settings::get('facebook_url')): ?><br><a href="<?= e($fb) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?></p></div>
    <div><div class="hd fh">เกี่ยวกับเรา</div><div class="fl">
      <a class="ul" href="<?= e(page_url('history')) ?>">ประวัติวิทยาลัย</a><a class="ul" href="<?= e(page_url('vision')) ?>">วิสัยทัศน์ พันธกิจ</a>
      <a class="ul" href="<?= e(page_url('structure')) ?>">โครงสร้างการบริหาร</a><a class="ul" href="<?= e(page_url('executives')) ?>">ทำเนียบผู้บริหาร</a></div></div>
    <div><div class="hd fh">ข้อมูลสาธารณะ</div><div class="fl">
      <a class="ul" href="<?= e(page_url('ita')) ?>">ITA</a><a class="ul" href="<?= e(url('news.php', ['cat' => 'procurement'])) ?>">ข่าวจัดซื้อจัดจ้าง</a>
      <a class="ul" href="<?= e(url('news.php', ['cat' => 'jobs'])) ?>">รับสมัครงาน</a><a class="ul" href="<?= e(page_url('privacy')) ?>">นโยบายคุ้มครองข้อมูลส่วนบุคคล</a></div></div>
    <div><div class="hd fh">การเดินทาง</div>
      <?php if ($map): ?><iframe class="map" src="<?= e($map) ?>" title="แผนที่ <?= e($siteName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
      <?php else: ?><div class="map ph">แผนที่ Google Maps<br><span class="small">(ตั้งค่าได้จากระบบจัดการ)</span></div><?php endif; ?></div>
  </div>
  <div class="foot-bot"><div class="wrap"><span>© <?= date('Y') + 543 ?> <?= e($siteName) ?></span><span>ผู้เข้าชม <?= number_format($visits) ?> ครั้ง</span></div></div>
</footer>

<?php if ($popup): ?>
<div class="popup" id="popup" role="dialog" aria-modal="true" aria-labelledby="pp-t" data-key="popup-<?= (int) $popup['id'] ?>-<?= e(substr(sha1($popup['updated_at']), 0, 6)) ?>" hidden>
  <div class="popup-box">
    <?php if ($popup['image']): ?>
      <img class="popup-img" data-sk src="<?= e(upload_url($popup['image'])) ?>" alt="<?= e($popup['title']) ?>">
    <?php else: ?>
      <div class="popup-ph"><div class="hd"><?= e($popup['title']) ?></div></div>
    <?php endif; ?>
    <div class="popup-c"><h2 id="pp-t" class="hd"><?= e($popup['title']) ?></h2><?php if ($popup['body']): ?><p><?= e($popup['body']) ?></p><?php endif; ?></div>
    <div class="popup-a">
      <button type="button" class="btn-line" data-close>ปิดหน้าต่าง</button>
      <?php if ($popup['link_url']): ?><a class="btn-navy" href="<?= e($popup['link_url']) ?>">อ่านรายละเอียด</a><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(url('assets/site.js')) ?>?v=4"></script>
</body>
</html>
