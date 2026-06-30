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

$routes->get('wishlist', 'Wishlist::index');
$routes->post('wishlist/books/(:num)/sources', 'Wishlist::createSource/$1');
$routes->post('wishlist/sources/(:num)', 'Wishlist::updateSource/$1');
$routes->post('wishlist/sources/(:num)/delete', 'Wishlist::deleteSource/$1');
$routes->get('sources', 'Sources::index');

$routes->get('circles', 'Circles::index');
$routes->get('circles/(:num)/circlems', 'Circles::circlems/$1');
$routes->post('circles/(:num)', 'Circles::update/$1');
$routes->post('circles/(:num)/track', 'Circles::toggleTrack/$1');
$routes->post('circles/(:num)/circlems/bind', 'Circles::bindCirclems/$1');

$routes->get('circlems', 'Circlems::index');
$routes->get('circlems/connect', 'Circlems::connect');
$routes->get('oauth/circlems/callback', 'Circlems::callback');
$routes->post('circlems/refresh', 'Circlems::refresh');
$routes->post('circlems/test', 'Circlems::test');
$routes->post('circlems/search-circle', 'Circlems::searchCircle');
$routes->post('circlems/sample-circles', 'Circlems::sampleCircles');
$routes->post('circlems/circle-detail', 'Circlems::circleDetail');
$routes->post('circlems/circle-books', 'Circlems::circleBooks');
$routes->post('circlems/catalog-base', 'Circlems::catalogBase');

$routes->get('orders', 'Orders::index');
$routes->post('orders', 'Orders::create');
$routes->get('orders/(:num)', 'Orders::show/$1');

$routes->get('shops', 'Shops::index');
$routes->post('shops', 'Shops::create');
$routes->post('shops/(:num)', 'Shops::update/$1');
$routes->post('shops/(:num)/delete', 'Shops::delete/$1');

$routes->get('locations', 'Locations::index');
$routes->post('locations', 'Locations::create');
$routes->post('locations/(:num)/delete', 'Locations::delete/$1');
