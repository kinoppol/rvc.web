<?php
declare(strict_types=1);

/* หน้าแรก — ตามดีไซน์ Main.dc.html / Mobile.dc.html */

require __DIR__ . '/app/bootstrap.php';

$cat = array_key_exists(input('cat'), NEWS_CATEGORIES) ? input('cat') : 'pr';
$news = latest_news(5, $cat);
$lead = $news[0] ?? null;
$rest = array_slice($news, 1);
$notice = latest_news(1, 'pr')[0] ?? null;
$groups = departments_by_group();
$gallery = latest_news(5, 'activity', null, true);
$deptCount = array_sum(array_map('count', $groups));
$director = Settings::get('director_name', '[ชื่อ–สกุล]');
$applyUrl = Settings::get('apply_url') ?: page_url('curriculum');
$arches = [['biz', '68%', '.3s'], ['home', '90%', '.45s'], ['tour', '78%', '.6s'], ['it', '100%', '.75s']];

$pageTitle  = '';
$nav        = 'home';
$popupWhere = 'home';
require BASE_PATH . '/app/views/header.php';
?>
<section class="hero">
  <div class="wrap hero-in">
    <div class="hero-text">
      <div class="aff"><?= e(Settings::get('affiliation')) ?></div>
      <div class="rule" aria-hidden="true"></div>
      <h1 class="hd"><?= e(Settings::get('site_name')) ?></h1>
      <p class="lead"><?= e(Settings::get('hero_lead')) ?></p>
      <div class="cta">
        <a class="btn-gold" href="<?= e($applyUrl) ?>"><?= e(Settings::get('apply_label', 'สมัครเรียน')) ?><span aria-hidden="true">→</span></a>
        <a class="btn-outline" href="<?= e(page_url('about')) ?>">รู้จักวิทยาลัย</a>
      </div>
    </div>
    <div class="arches" aria-hidden="true">
      <?php foreach ($arches as [$g, $h, $d]): ?><div style="height:<?= $h ?>;background:<?= e(unit_group($g)['color']) ?>;animation-delay:<?= $d ?>"></div><?php endforeach; ?>
    </div>
  </div>
  <div class="groupbar"><div class="wrap">
    <?php foreach ($groups as $key => $depts): $g = unit_group($key); ?>
      <a class="ul" href="#depts" style="border-top-color:<?= e($g['light']) ?>"><div class="hd n"><?= e($g['label']) ?></div><div class="c"><?= count($depts) ?> แผนกวิชา</div></a>
    <?php endforeach; ?>
  </div></div>
  <div class="curtain" aria-hidden="true"><div style="animation:rise .8s cubic-bezier(.2,.7,.2,1) both">
    <div class="seal hd">ตรา</div><div class="hd" style="font-size:24px;font-weight:600"><?= e(Settings::get('site_name')) ?></div></div></div>
</section>

<?php if ($notice): ?>
<div class="notice"><div class="wrap">
  <span class="tag hd">ประกาศ</span>
  <a class="item ul" href="<?= e(news_url($notice)) ?>"><span class="d"><?= e(thai_date($notice['published_at'], 'short')) ?></span><?= e($notice['title']) ?></a>
  <a class="all" href="<?= e(url('news.php', ['cat' => 'pr'])) ?>">ประกาศทั้งหมด →</a>
</div></div>
<?php endif; ?>

<section id="news" class="sec">
  <div class="wrap">
    <div class="sec-h">
      <h2 class="hd">ข่าวสาร</h2>
      <nav class="tabs" aria-label="หมวดข่าว">
        <?php foreach (NEWS_CATEGORIES as $k => $label): ?>
          <a href="<?= e(url('', ['cat' => $k])) ?>#news" class="<?= $k === $cat ? 'on' : '' ?>" <?= $k === $cat ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <?php if (!$lead): ?>
      <p class="empty">ยังไม่มีข่าวในหมวด<?= e(NEWS_CATEGORIES[$cat]) ?></p>
    <?php else: [$tag, $color, $tint] = news_tag($lead); ?>
    <div class="news-lead-wrap">
      <a class="news-lead ph" href="<?= e(news_url($lead)) ?>">
        <div class="cover" style="background-color:<?= e($tint) ?>;<?= $lead['cover'] ? 'background-image:url(' . e(upload_url($lead['cover'])) . ')' : '' ?>"><?php if (!$lead['cover']): ?><span class="lbl">ภาพข่าว</span><?php endif; ?></div>
        <div class="meta" style="color:<?= e($color) ?>"><?= e($tag) ?> <span>· <?= e(thai_date($lead['published_at'], 'short')) ?></span></div>
        <h3 class="hd cap"><?= e($lead['title']) ?></h3>
        <?php if ($lead['excerpt']): ?><p><?= e(str_limit($lead['excerpt'], 160)) ?></p><?php endif; ?>
      </a>
      <div class="news-side">
        <?php foreach ($rest as $n): [$tag, $color, $tint] = news_tag($n); ?>
          <a class="news-row ph" href="<?= e(news_url($n)) ?>">
            <div class="th" style="background-color:<?= e($tint) ?>;<?= $n['cover'] ? 'background-image:url(' . e(upload_url($n['cover'])) . ')' : '' ?>"></div>
            <div><div class="m"><span style="color:<?= e($color) ?>;font-weight:600"><?= e($tag) ?></span> · <?= e(thai_date($n['published_at'], 'short')) ?></div>
            <div class="t cap"><?= e($n['title']) ?></div></div>
          </a>
        <?php endforeach; ?>
        <a class="morelink" href="<?= e(url('news.php', ['cat' => $cat])) ?>">ข่าวทั้งหมดในหมวดนี้ →</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section id="depts" class="sec alt">
  <div class="wrap">
    <div style="display:flex;flex-wrap:wrap;gap:16px 48px;align-items:baseline;margin-bottom:32px">
      <h2 class="hd h2" style="margin:0">แผนกวิชา</h2>
      <p style="margin:0;color:var(--mute);max-width:40em">เปิดสอนระดับ ปวช. และ ปวส. ทั้งระบบปกติและระบบทวิภาคีร่วมกับสถานประกอบการ</p>
    </div>
    <div class="depts">
      <?php foreach ($groups as $key => $depts): $g = unit_group($key); ?>
        <div class="dept-g" style="border-top-color:<?= e($g['color']) ?>">
          <div class="hd gn" style="color:<?= e($g['color']) ?>"><?= e($g['label']) ?></div>
          <div style="margin-top:10px">
            <?php foreach ($depts as $d): ?><a class="ul" href="<?= e(unit_url($d)) ?>"><?= e($d['name']) ?><span aria-hidden="true">→</span></a><?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap director">
    <figure>
      <div class="photo" style="<?= ($ph = Settings::get('director_photo')) ? 'background-image:url(' . e(upload_url($ph)) . ')' : '' ?>" role="img" aria-label="ภาพผู้อำนวยการ"></div>
      <figcaption><?= e($director) ?><br>ผู้อำนวยการ<?= e(Settings::get('site_name')) ?></figcaption>
    </figure>
    <div class="txt">
      <h2 class="hd h2" style="margin-bottom:8px">สารจากผู้อำนวยการ</h2>
      <div class="hd quote-mark" aria-hidden="true">“</div>
      <p class="hd quote"><?= e(Settings::get('director_quote', '[ข้อความสารจากผู้อำนวยการ 2–3 บรรทัด แก้ไขได้จากระบบจัดการ]')) ?></p>
      <a class="morelink" href="<?= e(page_url('director-message')) ?>">อ่านสารฉบับเต็ม →</a>
      <div class="stats">
        <div><div class="hd v"><?= $deptCount ?></div><div class="l">แผนกวิชา</div></div>
        <div><div class="hd v"><?= e(Settings::get('stat_students', '—')) ?></div><div class="l">นักเรียน นักศึกษา</div></div>
        <div><div class="hd v"><?= e(Settings::get('stat_staff', '—')) ?></div><div class="l">ครูและบุคลากร</div></div>
        <div><div class="hd v"><?= e(Settings::get('stat_partners', '—')) ?></div><div class="l">สถานประกอบการคู่ความร่วมมือ</div></div>
      </div>
    </div>
  </div>
</section>

<?php if ($gallery): ?>
<section class="sec bt">
  <div class="wrap">
    <div class="sec-h"><h2 class="hd">ภาพกิจกรรม</h2><a class="more" href="<?= e(url('news.php', ['cat' => 'activity'])) ?>">กิจกรรมทั้งหมด →</a></div>
    <div class="gallery">
      <?php foreach ($gallery as $i => $n): ?>
        <a class="ph <?= $i === 0 ? 'big' : '' ?>" href="<?= e(news_url($n)) ?>" style="background-image:url(<?= e(upload_url($n['cover'])) ?>)"><span class="cap"><?= e($n['title']) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/footer.php';
