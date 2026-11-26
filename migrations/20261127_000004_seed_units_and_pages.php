<?php
declare(strict_types=1);

/** ข้อมูลตั้งต้น: แผนกวิชา ฝ่าย/งาน และหน้าเว็บพื้นฐาน (ใส่เฉพาะที่ยังไม่มี) */
return new class {
    public string $description = 'ข้อมูลตั้งต้น: แผนกวิชา ฝ่าย งาน และหน้าเว็บพื้นฐาน';

    private const DEPARTMENTS = [
        // slug, ชื่อ, ชื่ออังกฤษ, กลุ่ม
        ['accounting', 'การบัญชี', 'DEPARTMENT OF ACCOUNTING', 'biz'],
        ['marketing', 'การตลาด', 'DEPARTMENT OF MARKETING', 'biz'],
        ['digital-office', 'การจัดการสำนักงานดิจิทัล', 'DEPARTMENT OF DIGITAL OFFICE MANAGEMENT', 'biz'],
        ['food-nutrition', 'อาหารและโภชนาการ', 'DEPARTMENT OF FOOD AND NUTRITION', 'home'],
        ['home-economics', 'คหกรรมศาสตร์', 'DEPARTMENT OF HOME ECONOMICS', 'home'],
        ['fashion-textile', 'แฟชั่นและสิ่งทอ', 'DEPARTMENT OF FASHION AND TEXTILES', 'home'],
        ['beauty-business', 'ธุรกิจความงาม', 'DEPARTMENT OF BEAUTY BUSINESS', 'home'],
        ['hotel', 'การโรงแรม', 'DEPARTMENT OF HOTEL', 'tour'],
        ['tourism', 'การท่องเที่ยว', 'DEPARTMENT OF TOURISM', 'tour'],
        ['information-technology', 'เทคโนโลยีสารสนเทศ', 'DEPARTMENT OF INFORMATION TECHNOLOGY', 'it'],
        ['digital-business-technology', 'เทคโนโลยีธุรกิจดิจิทัล', 'DEPARTMENT OF DIGITAL BUSINESS TECHNOLOGY', 'it'],
    ];

    private const DIVISIONS = [
        ['resource-division', 'ฝ่ายบริหารทรัพยากร', 'RESOURCE MANAGEMENT DIVISION', [
            ['general-admin', 'งานบริหารงานทั่วไป'], ['personnel', 'งานบุคลากร'], ['finance', 'งานการเงิน'],
            ['supplies', 'งานพัสดุ'], ['registration', 'งานทะเบียน'],
        ]],
        ['strategy-division', 'ฝ่ายยุทธศาสตร์และแผนงาน', 'STRATEGY AND PLANNING DIVISION', [
            ['planning-budget', 'งานยุทธศาสตร์แผนงานและงบประมาณ'], ['quality-assurance', 'งานมาตรฐานและการประกันคุณภาพ'],
            ['information-center', 'งานศูนย์ข้อมูลสารสนเทศ'], ['cooperation', 'งานความร่วมมือ'],
        ]],
        ['student-affairs-division', 'ฝ่ายพัฒนากิจการนักเรียนนักศึกษา', 'STUDENT AFFAIRS DIVISION', [
            ['student-activities', 'งานกิจกรรมนักเรียนนักศึกษา'], ['guidance', 'งานครูที่ปรึกษาและการแนะแนว'],
            ['discipline-safety', 'งานปกครองและความปลอดภัย'], ['student-welfare', 'งานสวัสดิการนักเรียนนักศึกษา'],
        ]],
        ['academic-division', 'ฝ่ายวิชาการ', 'ACADEMIC AFFAIRS DIVISION', [
            ['curriculum', 'งานพัฒนาหลักสูตรการเรียนการสอน'], ['assessment', 'งานวัดผลและประเมินผล'],
            ['dual-vocational', 'งานอาชีวศึกษาระบบทวิภาคี'], ['library', 'งานวิทยบริการและห้องสมุด'],
        ]],
    ];

    private const PAGES = [
        ['about', 'เกี่ยวกับวิทยาลัย'], ['history', 'ประวัติวิทยาลัย'], ['vision', 'วิสัยทัศน์ พันธกิจ'],
        ['structure', 'โครงสร้างการบริหาร'], ['executives', 'ทำเนียบผู้บริหาร'], ['director-message', 'สารจากผู้อำนวยการ'],
        ['curriculum', 'หลักสูตรที่เปิดสอน'], ['contact', 'ติดต่อวิทยาลัย'], ['ita', 'ITA การประเมินคุณธรรมและความโปร่งใส'],
        ['privacy', 'นโยบายคุ้มครองข้อมูลส่วนบุคคล'],
    ];

    public function up(PDO $db): void
    {
        $unit = $db->prepare('INSERT IGNORE INTO units (parent_id, slug, name, name_en, kind, group_key, intro, sort) VALUES (?,?,?,?,?,?,?,?)');
        $sort = 0;
        foreach (self::DEPARTMENTS as [$slug, $name, $en, $group]) {
            $unit->execute([null, $slug, $name, $en, 'department', $group, '', ++$sort]);
        }
        $sort = 0;
        foreach (self::DIVISIONS as [$slug, $name, $en, $works]) {
            $unit->execute([null, $slug, $name, $en, 'division', 'org', '', ++$sort]);
            $pid = (int) $db->query('SELECT id FROM units WHERE slug = ' . $db->quote($slug))->fetchColumn();
            $ws  = 0;
            foreach ($works as [$wslug, $wname]) {
                $unit->execute([$pid, $wslug, $wname, '', 'work', 'org', '', ++$ws]);
            }
        }
        $page = $db->prepare('INSERT IGNORE INTO pages (slug, title, body) VALUES (?,?,?)');
        foreach (self::PAGES as [$slug, $title]) {
            $page->execute([$slug, $title, "[เนื้อหาหน้า {$title}]\n\nแก้ไขได้จากระบบจัดการ › หน้าเว็บ"]);
        }
    }

    public function down(PDO $db): void
    {
        $slugs = array_column(self::DEPARTMENTS, 0);
        foreach (self::DIVISIONS as [$slug, , , $works]) {
            $slugs = [...$slugs, ...array_column($works, 0), $slug];
        }
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $db->prepare("DELETE FROM units WHERE slug IN ($in)")->execute($slugs);
        $ps = array_column(self::PAGES, 0);
        $db->prepare('DELETE FROM pages WHERE slug IN (' . implode(',', array_fill(0, count($ps), '?')) . ')')->execute($ps);
    }
};
