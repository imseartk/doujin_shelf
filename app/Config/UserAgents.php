<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class UserAgents extends BaseConfig
{
    public array $platforms = [
        'windows nt 10.0' => 'Windows 10',
        'windows nt 6.3' => 'Windows 8.1',
        'windows nt 6.2' => 'Windows 8',
        'windows nt 6.1' => 'Windows 7',
        'windows nt 6.0' => 'Windows Vista',
        'windows nt 5.2' => 'Windows 2003',
        'windows nt 5.1' => 'Windows XP',
        'windows nt 5.0' => 'Windows 2000',
        'windows' => 'Unknown Windows OS',
        'android' => 'Android',
        'blackberry' => 'BlackBerry',
        'iphone' => 'iOS',
        'ipad' => 'iOS',
        'ipod' => 'iOS',
        'os x' => 'Mac OS X',
        'linux' => 'Linux',
        'debian' => 'Debian',
        'freebsd' => 'FreeBSD',
        'unix' => 'Unknown Unix OS',
    ];

    public array $browsers = [
        'OPR' => 'Opera',
        'Edg' => 'Edge',
        'Edge' => 'Spartan',
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
        'Opera' => 'Opera',
        'MSIE' => 'Internet Explorer',
        'Trident.* rv' => 'Internet Explorer',
        'Mozilla' => 'Mozilla',
    ];

    public array $mobiles = [
        'android' => 'Android',
        'iphone' => 'Apple iPhone',
        'ipad' => 'iPad',
        'ipod' => 'Apple iPod Touch',
        'blackberry' => 'BlackBerry',
        'mobile' => 'Generic Mobile',
        'wireless' => 'Generic Mobile',
        'smartphone' => 'Generic Mobile',
    ];

    public array $robots = [
        'googlebot' => 'Googlebot',
        'bingbot' => 'Bing',
        'duckduckbot' => 'DuckDuckBot',
        'bot' => 'Generic Bot',
        'crawler' => 'Generic Crawler',
        'spider' => 'Generic Spider',
    ];
}
