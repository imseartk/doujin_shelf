set -eu
cd /var/www/html/doujin
sudo -u www-data php <<'PHP'
<?php
define('ENVIRONMENT','production');define('FCPATH',getcwd().'/public/');
require 'app/Config/Paths.php';$p=new Config\Paths();require $p->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($p);
$db=db_connect();
echo json_encode($db->query('SELECT event_id,day,COUNT(*) count,SUM(circle_id IS NOT NULL) linked,SUM(cut_image_url IS NOT NULL) images FROM c109_circles GROUP BY event_id,day')->getResultArray())."\n";
$id=bin2hex(random_bytes(16));$cfg=config('Session');$file=$cfg->savePath.'/'.$cfg->cookieName.$id;
file_put_contents($file,'admin_unlocked|b:1;__ci_last_regenerate|i:'.time().';');chmod($file,0600);
try {
    $urls=['/circles?circlems=missing-with-booth','/circles?circlems=missing-with-booth&page=2'];
    $local=$db->table('c109_circles')->where('circle_id IS NOT NULL',null,false)->get(1)->getRowArray();
    if($local)$urls[]='/circles/'.$local['circle_id'].'/c109/candidates?day=3&q='.rawurlencode($local['circle_name']);
    foreach($urls as $url){
        $c=curl_init('https://doujin.artick.tw'.$url);
        curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_COOKIE=>$cfg->cookieName.'='.$id]);
        $html=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        echo json_encode(['path'=>explode('?',$url)[0],'http'=>$code,'bytes'=>strlen((string)$html),'third_day'=>str_contains((string)$html,'3日目')],JSON_UNESCAPED_UNICODE)."\n";
        if($code!==200)throw new RuntimeException('HTTP smoke test failed');
        if(str_contains($url,'circlems=missing-with-booth')) {
            preg_match('/共\s*([\d,]+)\s*個社團/u',$html,$m);
            echo 'Matching circles: '.($m[1] ?? 'unknown')."\n";
            if(preg_match('/Circle\.ms\s+[0-9]{4,}/',$html))throw new RuntimeException('Filter includes a bound Circle.ms ID');
        }
        if(str_contains($url,'c109=unlinked') && str_contains($html,'href="/c109/map?'))throw new RuntimeException('Unlinked filter includes linked placements');
        if(str_contains($url,'c109=linked') && !str_contains($html,'href="/c109/map?'))throw new RuntimeException('Linked filter missing placements');
        if($url==='/circles')file_put_contents('/tmp/c109-circles-test.html',$html);
        if($url==='/c109/map?day=3&map=E123')file_put_contents('/tmp/c109-map-test.html',$html);
    }
} finally { if(is_file($file))unlink($file); }
PHP
