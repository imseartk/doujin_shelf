<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Books::index');

$routes->get('books', 'Books::index');
$routes->get('books/new', 'Books::new');
$routes->get('books/circles/search', 'Books::circleSearch');
$routes->get('books/taxonomy/(:segment)/search', 'Books::taxonomySearch/$1');
$routes->post('books/taxonomy/(:segment)', 'Books::taxonomyStore/$1');
$routes->post('books', 'Books::create');
$routes->get('books/(:num)/edit', 'Books::edit/$1');
$routes->post('books/(:num)', 'Books::update/$1');
$routes->post('books/(:num)/cover', 'BookCovers::upload/$1');
$routes->post('books/(:num)/delete', 'Books::delete/$1');

$routes->post('preferences/cover-privacy', 'Preferences::coverPrivacy');

$routes->get('manage', 'Admin::manage');
$routes->post('manage', 'Admin::unlock');

$routes->get('tools/book-tags', 'BookTagBatch::index');
$routes->post('tools/book-tags/apply', 'BookTagBatch::apply');

$routes->get('wishlist', 'Wishlist::index');
$routes->post('wishlist/books/(:num)/sources', 'Wishlist::createSource/$1');
$routes->post('wishlist/sources/(:num)', 'Wishlist::updateSource/$1');
$routes->post('wishlist/sources/(:num)/delete', 'Wishlist::deleteSource/$1');
$routes->get('sources', 'Sources::index');

$routes->get('circles', 'Circles::index');
$routes->get('circles/(:num)/circlems', 'Circles::circlems/$1');
$routes->get('circles/(:num)/circlems/candidates', 'Circles::circlemsCandidatesJson/$1');
$routes->get('circles/(:num)/c108/candidates', 'Circles::c108CandidatesJson/$1');
$routes->post('circles/(:num)', 'Circles::update/$1');
$routes->post('circles/(:num)/track', 'Circles::toggleTrack/$1');
$routes->post('circles/(:num)/circlems/bind', 'Circles::bindCirclems/$1');
$routes->post('circles/(:num)/c108/bind', 'Circles::bindC108/$1');

$routes->get('c108', 'C108::index');
$routes->get('c108/map', 'C108::map');
$routes->get('c108/export-map', 'C108::exportMap');
$routes->get('c108/circle/(:num)', 'C108::circle/$1');
$routes->get('c108/works/(:num)', 'C108::works/$1');
$routes->post('c108/notices/(:num)/read', 'C108::readNotice/$1');
$routes->post('c108/notices/read-all', 'C108::readAllNotices');

$routes->get('api/app/c108/summary', 'C108::appSummary');
$routes->get('api/app/c108/maps', 'C108::appMaps');
$routes->get('api/app/c108/map', 'C108::appMap');
$routes->get('api/app/c108/notices', 'C108::appNotices');
$routes->get('api/app/books', 'AppApi::books');
$routes->get('api/app/circles', 'AppApi::circles');
$routes->post('api/app/circles/(:num)/track', 'AppApi::toggleCircleTracking/$1');

$routes->get('circlems', 'Circlems::index');
$routes->get('circlems/connect', 'Circlems::connect');
$routes->get('oauth/circlems/callback', 'Circlems::callback');
$routes->post('circlems/refresh', 'Circlems::refresh');
$routes->post('circlems/test', 'Circlems::test');
$routes->post('circlems/convert-bindings', 'Circlems::convertBindings');
$routes->post('circlems/search-circle', 'Circlems::searchCircle');
$routes->post('circlems/sample-circles', 'Circlems::sampleCircles');
$routes->post('circlems/circle-detail', 'Circlems::circleDetail');
$routes->post('circlems/circle-books', 'Circlems::circleBooks');
$routes->post('circlems/catalog-base', 'Circlems::catalogBase');
$routes->post('circlems/catalog-download-text', 'Circlems::catalogDownloadText');
$routes->post('circlems/catalog-download-image', 'Circlems::catalogDownloadImage');
$routes->post('circlems/catalog-export-common-images', 'Circlems::catalogExportCommonImages');
$routes->post('circlems/catalog-lookup', 'Circlems::catalogLookup');
$routes->post('circlems/import-c108', 'Circlems::importC108');

$routes->get('orders', 'Orders::index');
$routes->post('orders', 'Orders::create');
$routes->get('orders/(:num)', 'Orders::show/$1');
$routes->post('orders/(:num)/complete', 'Orders::complete/$1');

$routes->get('shops', 'Shops::index');
$routes->post('shops', 'Shops::create');
$routes->post('shops/(:num)', 'Shops::update/$1');
$routes->post('shops/(:num)/delete', 'Shops::delete/$1');

$routes->get('locations', 'Locations::index');
$routes->post('locations', 'Locations::create');
$routes->post('locations/(:num)/delete', 'Locations::delete/$1');
