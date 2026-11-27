<?php
/**
 * ภาพถ่ายสัญลักษณ์จังหวัดร้อยเอ็ด 4 อย่างในซุ้มโค้งของส่วนบนหน้าแรก (เรียงจากซ้ายไปขวา)
 *
 * ภาพมาจาก Wikimedia Commons (ย่อกว้าง 960px เก็บใน assets/roiet/) — สัญญาอนุญาต CC BY-SA
 * กำหนดให้แสดงชื่อผู้ถ่าย สัญญาอนุญาต และลิงก์ต้นฉบับ จึงต้องคงบรรทัดเครดิตใต้ภาพไว้
 * เปลี่ยนภาพ: วางไฟล์ใหม่ใน assets/roiet/ แล้วแก้ file, position (จุดที่ต้องการให้อยู่กลางซุ้ม) และเครดิต
 * หมายเหตุ: ภาพรวงข้าวเป็นภาพประกอบทั่วไป ไม่ได้ถ่ายในร้อยเอ็ด
 *
 * @return list<array{height:string, delay:string, label:string, file:string, position:string, author:string, license:string, source:string}>
 */
$commons = 'https://commons.wikimedia.org/wiki/File:';

return [
    ['height' => '68%',  'delay' => '.3s',  'label' => 'รวงข้าว', 'file' => 'rice.jpg', 'position' => '50% 50%',
        'author' => 'Charipearl', 'license' => 'CC BY-SA 4.0', 'source' => $commons . 'Ripe_Rice_Grains.jpg'],
    ['height' => '90%',  'delay' => '.45s', 'label' => 'พระมหาเจดีย์<wbr>ชัยมงคล', 'file' => 'chedi.jpg', 'position' => '56% 50%',
        'author' => 'Pawyilee', 'license' => 'สาธารณสมบัติ', 'source' => $commons . 'Phra_Maha_Chedi_Chai_Mongkol.JPG'],
    ['height' => '78%',  'delay' => '.6s',  'label' => 'บึงพลาญชัย', 'file' => 'lake.jpg', 'position' => '63% 50%',
        'author' => 'คีโต', 'license' => 'CC BY-SA 4.0', 'source' => $commons . rawurlencode('ศาลหลักเมืองร้อยเอ็ด.jpg')],
    ['height' => '100%', 'delay' => '.75s', 'label' => 'หอโหวด ๑๐๑', 'file' => 'tower.jpg', 'position' => '50% 50%',
        'author' => 'คีโต', 'license' => 'CC BY-SA 4.0', 'source' => $commons . rawurlencode('หอโหวตและบึงพลาญชัย_(cropped).jpg')],
];
