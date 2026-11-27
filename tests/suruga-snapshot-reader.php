<?php
require __DIR__ . '/../app/Libraries/ShopProductReader.php';
require __DIR__ . '/../app/Libraries/SurugaSnapshotReader.php';

use App\Libraries\SurugaSnapshotReader;

$url = 'https://www.suruga-ya.jp/product/detail/ZHOA91774';
$shop = "\u{99ff}\u{6cb3}\u{5c4b}\u{672c}\u{9928}";
$button = ['tag'=>'button', 'class'=>'btn_buy btn cart1', 'text'=>"\u{30ab}\u{30fc}\u{30c8}\u{306b}\u{5165}\u{308c}\u{308b}",
    'visible'=>true, 'disabled'=>false, 'ariaDisabled'=>false, 'classDisabled'=>false, 'pointerEvents'=>'auto',
    'ancestors'=>[['tag'=>'form', 'class'=>'cart add-cart']]];
$price = ['class'=>'price-buy', 'text'=>"1,000\u{5186} (\u{7a0e}\u{8fbc})", 'visible'=>true,
    'ancestors'=>[['class'=>'selected_price'], ['id'=>'cart']]];
$base = ['schemaVersion'=>2, 'url'=>$url, 'canonical'=>$url, 'challenge'=>false,
    'suruga'=>['buttons'=>[$button], 'prices'=>[$price], 'soldOut'=>[]],
    'structured'=>[['url'=>$url, 'offers'=>[['url'=>'', 'price'=>'1000','priceCurrency'=>'JPY','availability'=>'https://schema.org/InStock']]]]];
$reader = new SurugaSnapshotReader();
$count = 0;
$check = function (array $data, string $expected, string $label, ?string $name = null) use ($reader, $url, $shop, &$count): array {
    $result = $reader->parse($data, $url, $name ?? $shop);
    if ($result['status'] !== $expected) throw new RuntimeException($label . ': ' . json_encode($result));
    if ($expected === 'unknown' && isset($result['price'])) throw new RuntimeException('Unknown must not propose a price');
    $count++;
    return $result;
};
if ($check($base, 'available', 'active')['price'] !== 1000) throw new RuntimeException('Wrong price');
foreach (['disabled','ariaDisabled','classDisabled'] as $field) {
    $case=$base; $case['suruga']['buttons'][0][$field]=true; $check($case,'unknown',$field);
    unset($case['suruga']['buttons'][0][$field]); $check($case,'unknown','missing '.$field);
}
$case=$base; $case['suruga']['buttons'][0]['visible']=false; $check($case,'unknown','hidden');
$case=$base; $case['suruga']['buttons'][0]['pointerEvents']='none'; $check($case,'unknown','pointer');
$case=$base; $case['suruga']['buttons']=[]; $check($case,'unknown','no button');
$case=$base; $case['suruga']['buttons'][0]['ancestors']=[]; $check($case,'unknown','recommendation button');
$case=$base; $case['suruga']['prices'][0]['text']="1,,000\u{5186}"; $check($case,'unknown','malformed price');
$case=$base; $other=$price; $other['text']="500\u{5186}"; $case['suruga']['prices'][]=$other; $check($case,'unknown','two prices');
$case=$base; $case['suruga']['prices'][0]['ancestors']=[]; $check($case,'unknown','recommendation price');
$case=$base; $case['structured'][0]['offers'][0]['price']='800'; $check($case,'unknown','price conflict');
$case=$base; $case['structured'][0]['offers'][0]['availability']='https://schema.org/OutOfStock'; $check($case,'unknown','stock conflict');
$case=$base; $case['structured'][0]['url']=$url.'OTHER'; $check($case,'unknown','unrelated product');
$case=$base; $case['structured'][0]['offers'][0]['url']=$url.'?tenpo_cd=123'; $check($case,'unknown','other seller offer');
$case=$base; $case['canonical']=$url.'OTHER'; $check($case,'identity','canonical');
$case=$base; $case['challenge']=true; $check($case,'verification','challenge');
$check($base,'seller','other shop','Other shop');
$sold=$base; $sold['suruga']['buttons']=[]; $sold['suruga']['prices']=[];
$sold['suruga']['soldOut']=[['class'=>'out-of-stock-text', 'visible'=>true,
    'text'=>"\u{7533}\u{3057}\u{8a33}\u{3054}\u{3056}\u{3044}\u{307e}\u{305b}\u{3093}\u{3002}\u{54c1}\u{5207}\u{308c}\u{4e2d}\u{3067}\u{3059}\u{3002}", 'ancestors'=>[['id'=>'cart']]]];
$sold['structured'][0]['offers'][0]['availability']='https://schema.org/OutOfStock';
if (isset($check($sold,'unavailable','sold out')['price'])) throw new RuntimeException('Historical price must not be proposed');
$case=$sold; $case['suruga']['soldOut'][0]['ancestors']=[]; $check($case,'unknown','unrelated sold out');
$case=$sold; $case['suruga']['buttons']=[$button]; $check($case,'unknown','contradictory DOM');
$case=$sold; $case['structured']=[]; $check($case,'unknown','no corroboration');
echo "Surugaya snapshot checks passed: $count\n";
