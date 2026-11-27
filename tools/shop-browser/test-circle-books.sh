set -eu
cd /var/www/html/doujin
sudo -u www-data php <<'PHP'
<?php
define('ENVIRONMENT','production');define('FCPATH',getcwd().'/public/');
require 'app/Config/Paths.php';$p=new Config\Paths();require $p->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($p);
$db=db_connect();
$book=$db->table('books')->where('circle_id IS NOT NULL',null,false)->where('type !=','comic')->where('cover_url !=','')->get(1)->getRowArray();
if(!$book)throw new RuntimeException('Missing test book');
$id=bin2hex(random_bytes(16));$cfg=config('Session');$file=$cfg->savePath.'/'.$cfg->cookieName.$id;
$get=static function($path,$cookie=''){
    $c=curl_init('https://doujin.artick.tw'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_COOKIE=>$cookie,CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest']]);
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    return [$status,(string)$body];
};
try {
    foreach([false,true] as $hidden){
        file_put_contents($file,'admin_unlocked|b:1;hide_covers|b:'.($hidden?'1':'0').';__ci_last_regenerate|i:'.time().';');chmod($file,0600);
        [$status,$html]=$get('/circles/'.$book['circle_id'].'/books',$cfg->cookieName.'='.$id);
        if($status!==200)throw new RuntimeException('HTTP '.$status);
        $count=$db->table('books')->where('circle_id',$book['circle_id'])->where('type !=','comic')->countAllResults();
        if(substr_count($html,'class="circle-book"')!==$count)throw new RuntimeException('Count mismatch');
        $cover=$book['cover_url'];
        $decoded=html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($hidden && str_contains($decoded,$cover))throw new RuntimeException('Hidden cover leaked');
        if(!$hidden && !str_contains($decoded,$cover))throw new RuntimeException('Visible cover missing');
        if($hidden)file_put_contents('/tmp/circle-books-fragment.html',$html);
        echo 'Books endpoint: '.($hidden?'masked':'visible').' OK, '.$count." books\n";
    }
    [$status,$html]=$get('/circles',$cfg->cookieName.'='.$id);
    if($status!==200 || !str_contains($html,'circle-books-dialog'))throw new RuntimeException('Page failed');
    file_put_contents('/tmp/circle-books-page.html',$html);
    [$status]=$get('/circles/2147483647/books',$cfg->cookieName.'='.$id);
    if($status!==404)throw new RuntimeException('Missing circle status');
    [$status]=$get('/circles/'.$book['circle_id'].'/books');
    if($status!==403)throw new RuntimeException('Auth guard failed');
    echo "Missing circle 404 and anonymous 403 OK\n";
} finally {if(is_file($file))unlink($file);}
PHP
