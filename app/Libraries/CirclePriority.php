<?php

namespace App\Libraries;

final class CirclePriority
{
    public const OPTIONS = [
        'normal' => '普通',
        'high' => '優先',
        'must' => '必看',
        'ignore' => '忽略',
        'blacklist' => '黑名單',
    ];

    public static function mapKey(array $row): string
    {
        $priority = $row['priority'] ?? 'normal';
        if (in_array($priority, ['must', 'high', 'ignore', 'blacklist'], true)) {
            return $priority;
        }
        return !empty($row['local_circle_id']) ? 'known' : 'unknown';
    }

    public static function mapRank(array $row): int
    {
        return ['unknown' => 0, 'known' => 1, 'ignore' => 2, 'high' => 3, 'must' => 4, 'blacklist' => 5][self::mapKey($row)];
    }
}
