set -eu
cd /var/www/html/doujin
stage=/home/artick/shop-browser-stage
backup=/var/backups/circle-books-$(date +%Y%m%d-%H%M%S)
mkdir -p "$backup"
cp --parents app/Controllers/Circles.php app/Views/circles/index.php app/Config/Routes.php "$backup"
php -l "$stage/Circles-books.php"
php -l "$stage/circles-books-index.php"
php -l "$stage/circle-books-view.php"
cp "$stage/Circles-books.php" app/Controllers/Circles.php
cp "$stage/circles-books-index.php" app/Views/circles/index.php
cp "$stage/circle-books-view.php" app/Views/circles/books.php
cp "$stage/circle-books.js" public/assets/circle-books.js
cp "$stage/circle-books.css" public/assets/circle-books.css
php <<'PHP'
<?php
$path='app/Config/Routes.php';$text=file_get_contents($path);
$route="\$routes->get('circles/(:num)/books', 'Circles::books/\$1');";
if(!str_contains($text,$route)){
    $anchor="\$routes->get('circles', 'Circles::index');";
    if(substr_count($text,$anchor)!==1)throw new RuntimeException('Route anchor mismatch');
    file_put_contents($path,str_replace($anchor,$anchor."\n".$route,$text));
}
PHP
chown www-data:www-data app/Controllers/Circles.php app/Views/circles/index.php app/Views/circles/books.php public/assets/circle-books.js public/assets/circle-books.css
php -l app/Config/Routes.php
echo "Backup: $backup"
