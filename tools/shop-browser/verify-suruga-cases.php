<?php
require '/var/www/html/doujin/app/Libraries/ShopProductReader.php';
require '/var/www/html/doujin/app/Libraries/SurugaSnapshotReader.php';
$reader = new App\Libraries\SurugaSnapshotReader();
foreach (['ZHOA91774'=>'available', 'ZHOU27935'=>'unavailable'] as $id=>$expected) {
    $snapshot=json_decode(file_get_contents('/var/lib/doujin-shop-browser/case-'.$id.'.json'),true,512,JSON_THROW_ON_ERROR);
    $result=$reader->parse($snapshot,'https://www.suruga-ya.jp/product/detail/'.$id,"\u{99ff}\u{6cb3}\u{5c4b}\u{672c}\u{9928}");
    echo $id.' '.json_encode($result).PHP_EOL;
    if ($result['status'] !== $expected) throw new RuntimeException('Live fixture mismatch');
}
