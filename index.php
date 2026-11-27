<?php
declare(strict_types=1);

/* หน้าแรก — ตามดีไซน์ Main.dc.html / Mobile.dc.html */

require __DIR__ . '/app/bootstrap.php';

$cats = public_categories();
$cat = array_key_exists(input('cat'), $cats) ? input('cat') : (isset($cats['hot']) && latest_news(1, 'hot') ? 'hot' : 'pr');   // แท็บแรก: ฮอตอัพเดต (ถ้าเปิดแสดงและมีข่าว)
$news = latest_news(5, $cat);
$lead = $news[0] ?? null;
$rest = array_slice($news, 1);
$notice = latest_news(1, 'pr')[0] ?? null;
$groups = departments_by_group();
$gallery = latest_news(5, 'activity', null, true);
$deptCount = array_sum(array_map('count', $groups));
$director = Settings::get('director_name', '[ชื่อ–สกุล]');
$applyUrl = Settings::get('apply_url') ?: page_url('curriculum');
$arches = require BASE_PATH . '/app/views/roiet_symbols.php';
$slides = active_slides();

$pageTitle  = '';
$nav        = 'home';
$popupWhere = 'home';
require BASE_PATH . '/app/views/header.php';
?>
<script>/* แสดงฉากเปิดตราวิทยาลัยครั้งแรกที่เข้าเว็บในแต่ละรอบ */try{if(sessionStorage.getItem('rvc-intro'))document.documentElement.classList.add('no-intro');else sessionStorage.setItem('rvc-intro','1')}catch(e){}</script>
<section class="hero<?= $slides ? ' has-slides' : '' ?>"<?= $slides ? ' aria-roledescription="carousel" aria-label="ภาพเด่นหน้าแรก"' : '' ?>>
  <div class="hero-track" id="hero-track">
  <div class="hero-slide is-on" data-slide="0"<?= $slides ? ' role="group" aria-roledescription="slide" aria-label="1 จาก ' . (count($slides) + 1) . '"' : '' ?>>
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
    <div class="arches" role="img" aria-label="สัญลักษณ์จังหวัดร้อยเอ็ด: <?= e(implode(' ', array_map(fn($a) => strip_tags($a['label']), $arches))) ?>">
      <?php foreach ($arches as $a): ?>
        <div style="height:<?= $a['height'] ?>;--ad:<?= $a['delay'] ?>" aria-hidden="true">
          <img src="<?= e(url('assets/roiet/' . $a['file'])) ?>" alt="" style="object-position:<?= $a['position'] ?>" decoding="async">
          <span><?= $a['label'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  </div>
  <?php foreach ($slides as $i => $sl): $src = upload_url($sl['image']); ?>
  <div class="hero-slide img" data-slide="<?= $i + 1 ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 2 ?> จาก <?= count($slides) + 1 ?>" aria-hidden="true">
    <span class="bg" style="background-image:url(<?= e($src) ?>)"></span>
    <?php if ($sl['link_url'] !== ''): ?><a class="hs-link" href="<?= e($sl['link_url']) ?>" tabindex="-1"><?php endif; ?>
    <img src="<?= e($src) ?>" alt="<?= e($sl['title']) ?>" loading="lazy" decoding="async">
    <?php if ($sl['link_url'] !== ''): ?></a><?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php if ($slides): ?>
  <div class="hero-ctl">
    <button type="button" class="hc-arrow prev" data-hero="prev" aria-label="ภาพก่อนหน้า"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
    <button type="button" class="hc-arrow next" data-hero="next" aria-label="ภาพถัดไป"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
    <div class="hc-bar">
      <button type="button" class="hc-play" data-hero="pause" aria-label="หยุดเลื่อนอัตโนมัติ"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path class="i-pause" d="M7 5h3v14H7zM14 5h3v14h-3z" fill="currentColor"/><path class="i-play" d="M8 5l11 7-11 7z" fill="currentColor"/></svg></button>
      <?php for ($i = 0; $i <= count($slides); $i++): ?><button type="button" class="hc-dot<?= $i === 0 ? ' on' : '' ?>" data-go="<?= $i ?>" aria-label="<?= $i === 0 ? 'ฉากเปิด' : e($slides[$i - 1]['title']) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span></span></button><?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
  </div>
  <div class="groupbar"><div class="wrap">
    <?php foreach ($groups as $key => $depts): $g = unit_group($key); ?>
      <a class="ul" href="#depts" style="border-top-color:<?= e($g['light']) ?>"><div class="hd n"><?= e($g['label']) ?></div><div class="c"><?= count($depts) ?> แผนกวิชา</div></a>
    <?php endforeach; ?>
  </div>
    <p class="wrap arch-credit">ภาพจาก Wikimedia Commons (ย่อและครอบตัด):
      <?php foreach ($arches as $i => $a): ?><?= $i ? ' · ' : '' ?><a href="<?= e($a['source']) ?>" target="_blank" rel="noopener"><?= e(strip_tags($a['label'])) ?></a> โดย <?= e($a['author']) ?>
        (<?php if ($a['license'] === 'CC BY-SA 4.0'): ?><a href="https://creativecommons.org/licenses/by-sa/4.0/deed.th" target="_blank" rel="noopener license"><?= e($a['license']) ?></a><?php else: ?><?= e($a['license']) ?><?php endif; ?>)<?php endforeach; ?>
    </p>
  </div>
  <div class="curtain" aria-hidden="true"><div class="curtain-in">
    <div class="seal-wrap"><img class="seal" src="<?= e(url('assets/seal.png')) ?>" alt=""></div>
    <div class="hd name"><?= e(Settings::get('site_name')) ?></div>
    <div class="line"></div>
    <div class="en"><?= e(Settings::get('site_name_en')) ?></div></div></div>
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
        <?php foreach ($cats as $k => $label): ?>
          <a href="<?= e(url('', ['cat' => $k])) ?>#news" class="<?= trim(($k === $cat ? 'on' : '') . ($k === 'hot' ? ' hot' : '')) ?>" <?= $k === $cat ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
    <?php if (!$lead): ?>
      <p class="empty">ยังไม่มีข่าวในหมวด<?= e(NEWS_CATEGORIES[$cat]) ?></p>
    <?php else: [$tag, $color, $tint] = news_tag($lead); ?>
    <div class="news-lead-wrap">
      <a class="news-lead ph" href="<?= e(news_url($lead)) ?>">
        <div class="cover"<?= news_cover_attr($lead, $tint) ?>></div>
        <div class="meta" style="color:<?= e($color) ?>"><?= e($tag) ?> <span>· <?= e(thai_date($lead['published_at'], 'short')) ?></span></div>
        <h3 class="hd cap"><?= e($lead['title']) ?></h3>
        <?php if ($lead['excerpt']): ?><p><?= e(str_limit($lead['excerpt'], 160)) ?></p><?php endif; ?>
      </a>
      <div class="news-side">
        <?php foreach ($rest as $n): [$tag, $color, $tint] = news_tag($n); ?>
          <a class="news-row ph" href="<?= e(news_url($n)) ?>">
            <div class="th"<?= news_cover_attr($n, $tint) ?>></div>
            <div><div class="m"><span style="color:<?= e($color) ?>;font-weight:600"><?= e($tag) ?></span> · <?= e(thai_date($n['published_at'], 'short')) ?></div>
            <div class="t cap"><?= e($n['title']) ?></div></div>
          </a>
        <?php endforeach; ?>
        <a class="morelink" href="<?= e(url('news.php', ['cat' => $cat])) ?>">ข่าวทั้งหมดในหมวดนี้ →</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php if (Facebook::due()): ?><span hidden data-fb-sync="<?= e(url('fb_sync.php')) ?>"></span><?php endif; ?>
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
      <div class="photo"<?= ($ph = Settings::get('director_photo')) ? ' data-sk' : '' ?> style="<?= $ph ? 'background-image:url(' . e(upload_url($ph)) . ')' : '' ?>" role="img" aria-label="ภาพผู้อำนวยการ"></div>
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
        <a class="ph <?= $i === 0 ? 'big' : '' ?>" href="<?= e(news_url($n)) ?>" data-sk style="background-image:url(<?= e(upload_url($n['cover'])) ?>)"><span class="cap"><?= e($n['title']) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require BASE_PATH . '/app/views/footer.php';
