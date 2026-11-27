<?php
/**
 * ภาพสัญลักษณ์จังหวัดร้อยเอ็ด 4 อย่างในซุ้มโค้งของส่วนบนหน้าแรก (วาดเป็น SVG ของเว็บเอง)
 * ซุ้มเรียงจากซ้ายไปขวา สีตามกลุ่มสาขา
 * @return list<array{group:string, height:string, delay:string, label:string, view:string, art:string}>
 */
$g = 'var(--gold)';

// รวงข้าวหอมมะลิ (ทุ่งกุลาร้องไห้)
$grains = function (array $pts): string {
    $out = '';
    foreach ($pts as [$x, $y, $r]) {
        $out .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="3.1" ry="6.4" transform="rotate(' . $r . ' ' . $x . ' ' . $y . ')"/>';
    }
    return $out;
};
$rice = '<g fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round">'
    . '<path d="M50 140C50 104 47 66 62 40C70 27 80 26 85 36"/><path d="M45 140C43 108 38 80 25 62C18 53 11 54 9 63"/><path d="M55 140C58 114 66 96 79 86C86 81 91 83 93 89"/></g>'
    . '<path fill="#fff" opacity=".5" d="M49 140C34 122 20 116 6 118C22 108 42 116 51 134ZM53 140C64 120 80 112 96 112C80 120 68 128 56 140Z"/>'
    . '<g fill="' . $g . '">'
    . $grains([[63, 32, 28], [72, 25, 70], [80, 28, 112], [88, 38, 150], [84, 48, 175], [78, 40, 15], [68, 40, -35], [58, 48, 20], [66, 54, -25]])
    . $grains([[26, 56, -40], [17, 51, -80], [9, 57, -140], [8, 70, 180], [16, 68, -5], [24, 71, 35], [34, 70, -20]])
    . $grains([[80, 80, 40], [89, 80, 100], [94, 91, 160], [86, 95, 5], [76, 93, -30]])
    . '</g>';

// พระมหาเจดีย์ชัยมงคล
$stupa = function (float $x, float $y, float $s) use ($g): string {
    return '<g transform="translate(' . $x . ' ' . $y . ') scale(' . $s . ')">'
        . '<path fill="#fff" d="M-13 0h26v-7h-3c0-13-5-20-10-21-5 1-10 8-10 21h-3z"/>'
        . '<path fill="' . $g . '" d="M-5-28h10l-1.5-5h-7zM-3-34h6L0-58z"/></g>';
};
$chedi = $stupa(14, 150, .72) . $stupa(86, 150, .72)
    . '<g fill="#fff"><path d="M14 190h72v-9H14zM19 179h62v-9H19zM24 168h52v-14H24z"/>'
    . '<path d="M29 152h42c-2-5-6-7-6-12 0-30-8-47-15-49-7 2-15 19-15 49 0 5-4 7-6 12z"/></g>'
    . '<g fill="' . $g . '"><path d="M14 179h72v2.5H14zM19 168h62v2.5H19zM24 152h52v2.5H24z"/>'
    . '<path d="M39 90h22l-3-10H42zM42.5 79h15L55 67H45zM45.5 66h9L50 14z"/><circle cx="50" cy="11" r="3.2"/></g>'
    . '<g fill="var(--arch)"><path d="M45 168v-7a5 5 0 0 1 10 0v7zM30 168v-5a3.5 3.5 0 0 1 7 0v5zM63 168v-5a3.5 3.5 0 0 1 7 0v5z"/></g>'
    . $stupa(28, 168, .62) . $stupa(72, 168, .62)
    . '<path fill="#fff" opacity=".55" d="M0 200v-10h100v10z"/>';

// บึงพลาญชัย กับศาลหลักเมืองบนเกาะกลางบึง
$lake = '<g fill="' . $g . '"><path d="M50 8l3.5 22h-7zM38 44c7-3 10-9 12-16 2 7 5 13 12 16zM28 62c10-3 15-9 18-17h8c3 8 8 14 18 17zM16 82c13-3 20-9 25-19h18c5 10 12 16 25 19z"/></g>'
    . '<g fill="#fff"><path d="M24 82h52v6H24zM29 88h6v30h-6zM42 88h5v30h-5zM53 88h5v30h-5zM65 88h6v30h-6zM22 118h56v6H22z"/>'
    . '<path d="M12 132c6-6 20-8 38-8s32 2 38 8z"/></g>'
    . '<g fill="#fff" opacity=".45"><circle cx="11" cy="118" r="9"/><circle cx="20" cy="124" r="7"/><circle cx="89" cy="118" r="9"/><circle cx="80" cy="124" r="7"/></g>'
    . '<g fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round">'
    . '<path d="M4 141q8-6 15.5 0t15.5 0 15.5 0 15.5 0 15.5 0 14 0"/><path opacity=".65" d="M12 153q7.5-6 15 0t15 0 15 0 15 0 15 0"/><path opacity=".35" d="M22 164q7-5 14 0t14 0 14 0 14 0"/></g>';

// หอโหวด ๑๐๑
$pipes = '';
foreach ([[27, 78], [34, 62], [41, 46], [48, 30], [55, 46], [62, 62], [69, 78]] as $i => [$x, $top]) {
    $pipes .= '<rect x="' . $x . '" y="' . $top . '" width="5" height="' . (122 - $top) . '" rx="2.5" fill="' . ($i % 2 ? $g : '#fff') . '"/>';
}
$tower = '<path fill="' . $g . '" d="M49.2 8h1.6v24h-1.6z"/><circle cx="50" cy="7" r="3.2" fill="' . $g . '"/>' . $pipes
    . '<path fill="#fff" d="M22 118h57l-6 12H28z"/><path fill="' . $g . '" d="M22 112h57v6H22z"/>'
    . '<path fill="#fff" d="M38 130h25c-6 40-5 72 5 106H33c10-34 11-66 5-106z"/>'
    . '<g fill="none" stroke="var(--arch)" stroke-width="1.6"><path d="M45 130c-2 40-3 72-5 106M50.5 130v106M56 130c2 40 3 72 5 106"/></g>'
    . '<path fill="' . $g . '" d="M27 236h47l3 7H24z"/><path fill="#fff" d="M16 243h69l5 9H11z"/><path fill="#fff" opacity=".55" d="M0 252h101v8H0z"/>';

return [
    ['group' => 'biz',  'height' => '68%',  'delay' => '.3s',  'label' => 'ข้าวหอมมะลิ',          'view' => '0 0 100 140', 'art' => $rice],
    ['group' => 'home', 'height' => '90%',  'delay' => '.45s', 'label' => 'พระมหาเจดีย์<wbr>ชัยมงคล', 'view' => '0 0 100 200', 'art' => $chedi],
    ['group' => 'tour', 'height' => '78%',  'delay' => '.6s',  'label' => 'บึงพลาญชัย',           'view' => '0 0 100 170', 'art' => $lake],
    ['group' => 'it',   'height' => '100%', 'delay' => '.75s', 'label' => 'หอโหวด ๑๐๑',           'view' => '0 0 101 260', 'art' => $tower],
];
