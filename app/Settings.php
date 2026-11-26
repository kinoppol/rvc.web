<?php
declare(strict_types=1);

/** ค่าตั้งค่าเว็บไซต์จากตาราง settings (โหลดครั้งเดียวต่อคำขอ) */
final class Settings
{
    private static ?array $cache = null;

    /** ค่าเริ่มต้น + คำอธิบายฟิลด์ในหน้าตั้งค่า */
    public const FIELDS = [
        'ข้อมูลวิทยาลัย' => [
            'site_name'       => ['ชื่อวิทยาลัย', 'วิทยาลัยอาชีวศึกษาร้อยเอ็ด', 'text'],
            'site_name_en'    => ['ชื่อภาษาอังกฤษ', 'Roi Et Vocational College', 'text'],
            'site_short'      => ['ชื่อย่อ', 'วอศ.ร้อยเอ็ด', 'text'],
            'affiliation'     => ['สังกัด', 'สังกัดสำนักงานคณะกรรมการการอาชีวศึกษา กระทรวงศึกษาธิการ', 'text'],
            'hero_lead'       => ['คำแนะนำในฉากเปิดหน้าแรก', 'สถานศึกษาสายบริหารธุรกิจ คหกรรม ท่องเที่ยวและการโรงแรม และเทคโนโลยีสารสนเทศ ของจังหวัดร้อยเอ็ด', 'textarea'],
            'apply_label'     => ['ข้อความปุ่มสมัครเรียน', 'สมัครเรียน ปีการศึกษา 2570', 'text'],
            'apply_url'       => ['ลิงก์สมัครเรียน', '', 'url'],
        ],
        'สารจากผู้อำนวยการ' => [
            'director_name'   => ['ชื่อ–สกุลผู้อำนวยการ', '', 'text'],
            'director_quote'  => ['ข้อความสั้น (2–3 บรรทัด)', '', 'textarea'],
            'director_photo'  => ['รูปผู้อำนวยการ', '', 'image'],
            'stat_students'   => ['จำนวนนักเรียน นักศึกษา', '', 'text'],
            'stat_staff'      => ['จำนวนครูและบุคลากร', '', 'text'],
            'stat_partners'   => ['จำนวนสถานประกอบการคู่ความร่วมมือ', '', 'text'],
        ],
        'ติดต่อ' => [
            'address'         => ['ที่อยู่', '', 'textarea'],
            'phone'           => ['โทรศัพท์', '', 'text'],
            'email'           => ['อีเมล', '', 'text'],
            'map_embed_url'   => ['ลิงก์ฝังแผนที่ Google Maps (src ของ iframe)', '', 'url'],
            'facebook_url'    => ['Facebook', '', 'url'],
        ],
        'ลิงก์ด่วนแถบบนสุด' => [
            'link_ita'        => ['ITA', '', 'url'],
            'link_complaint'  => ['ร้องเรียน', '', 'url'],
            'link_rms'        => ['ระบบ RMS', '', 'url'],
        ],
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            $defaults = [];
            foreach (self::FIELDS as $fields) {
                foreach ($fields as $k => [, $def]) {
                    $defaults[$k] = $def;
                }
            }
            try {
                $rows = Database::all('SELECT `key`, `value` FROM settings');
                self::$cache = array_merge($defaults, array_column($rows, 'value', 'key'));
            } catch (Throwable) {
                self::$cache = $defaults;
            }
        }
        return self::$cache;
    }

    public static function get(string $key, string $default = ''): string
    {
        $v = self::all()[$key] ?? '';
        return ($v === '' || $v === null) ? $default : (string) $v;
    }

    public static function set(string $key, string $value): void
    {
        Database::run('INSERT INTO settings (`key`, `value`, updated_at) VALUES (?, ?, NOW())
                       ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()', [$key, $value]);
        self::$cache = null;
    }
}
