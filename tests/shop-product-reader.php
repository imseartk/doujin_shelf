<?php

require __DIR__ . '/../app/Libraries/ShopProductReader.php';
use App\Libraries\ShopProductReader;
$reader = new ShopProductReader();
$url = 'https://www.melonbooks.co.jp/detail/detail.php?product_id=3062038';
function check($expected, $actual): void {
    if ($expected !== $actual) throw new RuntimeException(var_export([$expected, $actual], true));
}
$head = '<link rel="canonical" href="' . $url . '">';
$page = $head . '<div class="item-meta3"><span class="price--value">5,280&nbsp;</span></div><span class="product-info__inventory-status__text">残りわずか</span>';
check(['status'=>'available','price'=>5280], $reader->parse($page . '<aside>売り切れ 100円</aside>', $url));
check(['status'=>'unavailable'], $reader->parse(str_replace('残りわずか','在庫なし',$page), $url));
check(['status'=>'unknown'], $reader->parse(str_replace('残りわずか','予約受付中',$page), $url));
check(['status'=>'verification'], $reader->parse('<script>window._cf_chl_opt = {}</script>', $url));
check(['status'=>'identity'], $reader->parse(str_replace('3062038','999',$page), $url));
check(['status'=>'unknown'], $reader->parse($head . '<p>おすすめ 100円 売り切れ</p>', $url));
check(null, ShopProductReader::identify('http://127.0.0.1/test'));
check(null, ShopProductReader::identify('https://www.melonbooks.co.jp.evil.test/detail/detail.php?product_id=1'));
check(null, ShopProductReader::identify('https://user:pass@www.melonbooks.co.jp/detail/detail.php?product_id=1'));
check(null, ShopProductReader::identify('https://www.melonbooks.co.jp:443/detail/detail.php?product_id=1'));
foreach (['https://www.suruga-ya.jp/product/detail/123?tenpo_cd=1','https://order.mandarake.co.jp/order/detailPage/item?itemCode=123'] as $offerUrl) {
    $data = ['@type'=>'Product','url'=>$offerUrl,'offers'=>['@type'=>'Offer','url'=>$offerUrl,'seller'=>['name'=>'まんだらけ SAHRA'],'priceCurrency'=>'JPY','price'=>'1200','availability'=>'https://schema.org/InStock']];
    $wrap = fn ($data) => '<link rel="canonical" href="'.$offerUrl.'"><script type="application/ld+json">'.json_encode($data).'</script>';
    check(['status'=>'available','price'=>1200],$reader->parse($wrap($data),$offerUrl,'まんだらけ SAHRA'));
    $data['offers']['availability']='https://schema.org/OutOfStock';
    check(['status'=>'unavailable'],$reader->parse($wrap($data),$offerUrl,'まんだらけ SAHRA'));
    $data['offers']['url']=$url;
    check(['status'=>'unknown'],$reader->parse($wrap($data),$offerUrl));
}
echo "Product reader safety and parsing checks passed.\n";
