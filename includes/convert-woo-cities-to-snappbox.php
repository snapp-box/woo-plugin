<?php 
namespace Snappbox;
if ( ! defined( 'ABSPATH' ) ) exit; 

class SnappBoxCityHelper {
    public static function snappb_get_city_to_state_map() {
        return [
            'THR' => 'tehran',
            'ABZ' => 'karaj',
            'RKH' => 'mashhad',
            'ESF' => 'isfahan',
            'EAZ' => 'tabriz',
            'FRS' => 'shiraz',
            'QHM' => 'qom',
            'KRN' => 'kerman',
            'YZD' => 'yazd',
            'ZJN' => 'zanjan',
            'ADL' => 'ardabil',
            'KHZ' => 'ahvaz',
            'WAZ' => 'urmia',
            'GIL' => 'rasht',
            'MZN' => 'sari',
            'HDN' => 'hamedan',
            'KRH' => 'kermanshah',
            'KRD' => 'sanandaj',
            'BHR' => 'bushehr',
            'GZN' => 'qazvin',
            'GLS' => 'gorgan',
            'NKH' => 'bojnourd',
            'SKH' => 'birjand',
            'MKZ' => 'arak',
            'LRS' => 'khorramabad',
            'HRZ' => 'bandar abbas',
            'SBN' => 'zabol',
        ];
    }
    public static function snappb_get_city_to_cityname_map() {
        return [
            'تهران' => 'tehran',
            'بوجنورد' => 'bojnourd',
            'بوشهر' => 'bushehr',
            'یزد' => 'yazd',
            'هرمزگان' => 'hormozgan',
            'قم' => 'qom',
            'مشهد' => 'mashhad',
            'قزوین' => 'qazvin',
            'گلستان' => 'golestan',
            'کرج' => 'karaj',
            'طبس' => 'tabas',
            'کرمان' => 'kerman',
            'تبریز' => 'tabriz',
            'همدان' => 'hamedan',
            'شیراز' => 'shiraz',
            'لرستان' => 'lorestan',
            'اردبیل' => 'ardabil',
            'اصفهان' => 'isfahan',
            'زنجان' => 'zanjan',
            'گیلان' => 'gilan',
            'مازندران' => 'mazandaran',
            'ارومیه' => 'urmia',
            'اهواز' => 'ahvaz',
            'کردستان' => 'kordestan',
            'اراک' => 'arak',
            'کرمانشاه' => 'kermanshah',
            'بیرجند' => 'birjand',
        ];
    }
}

?>