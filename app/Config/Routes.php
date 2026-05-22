<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Books::index');

$routes->get('books', 'Books::index');
$routes->get('books/new', 'Books::new');
$routes->get('books/taxonomy/(:segment)/search', 'Books::taxonomySearch/$1');
$routes->post('books/taxonomy/(:segment)', 'Books::taxonomyStore/$1');
$routes->post('books', 'Books::create');
$routes->get('books/(:num)/edit', 'Books::edit/$1');
$routes->post('books/(:num)', 'Books::update/$1');
$routes->post('books/(:num)/delete', 'Books::delete/$1');

$routes->get('wishlist', 'Wishlist::index');
$routes->post('wishlist/books/(:num)/sources', 'Wishlist::createSource/$1');
$routes->post('wishlist/sources/(:num)', 'Wishlist::updateSource/$1');
$routes->post('wishlist/sources/(:num)/delete', 'Wishlist::deleteSource/$1');
$routes->get('sources', 'Sources::index');

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
