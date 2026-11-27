<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Controllers/BaseController.php';
require __DIR__ . '/../app/Controllers/C108.php';
require __DIR__ . '/../app/Controllers/C109.php';
function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/C109.php');
check(!str_contains($controller, "table('c108_circles"), 'C109 queries must not read C108');
check(!str_contains($controller, 'event_230'), 'C109 must not use C108 map files');
foreach (['map','index','export_map'] as $view) {
    $source=file_get_contents($root.'/app/Views/c109/'.$view.'.php');
    check(!str_contains($source, '/c108'), 'C109 links must not point to C108');
    check(!str_contains($source, '\\Controllers\\C108::'), 'C109 must use isolated geometry');
}
$rows=[];
foreach (range(1,48) as $n) {
    foreach (['a','b'] as $sub) $rows[]=['day'=>3,'map_filename'=>'E7','block_name'=>'A','space_no'=>$n,'space_no_sub'=>$sub,'xpos2'=>$n*40,'ypos2'=>0];
}
$image=['marker_offset_x'=>20,'marker_offset_y'=>20];
check(App\Controllers\C108::markerPositions($rows,$image) === App\Controllers\C109::markerPositions($rows,$image),'Unchanged hall geometry must retain corrections');
echo "C109 isolation and retained geometry checks passed.\n";
