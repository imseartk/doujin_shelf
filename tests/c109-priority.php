<?php
require __DIR__ . '/../app/Libraries/CirclePriority.php';
use App\Libraries\CirclePriority;
foreach ([false, true] as $tracked) {
    foreach (['normal' => 'known', 'high' => 'high', 'must' => 'must', 'ignore' => 'ignore', 'blacklist' => 'blacklist'] as $priority => $expected) {
        $row = ['priority' => $priority, 'local_circle_id' => 1, 'is_tracked' => $tracked];
        if (CirclePriority::mapKey($row) !== $expected) throw new RuntimeException('Incorrect priority color');
    }
    if (CirclePriority::mapKey(['is_tracked' => $tracked]) !== 'unknown') throw new RuntimeException('Tracking must not set color');
}
foreach (['map', 'export_map', 'index'] as $view) {
    $source = file_get_contents(__DIR__ . '/../app/Views/c109/' . $view . '.php');
    foreach (['ignore', 'blacklist'] as $value) {
        if (!str_contains($source, 'value="' . $value . '"')) throw new RuntimeException('Missing filter');
    }
}
echo "C109 priority colors and filters passed.\n";
