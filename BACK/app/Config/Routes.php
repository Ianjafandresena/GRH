<?php
use CodeIgniter\Router\RouteCollection;

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->post('api/auth/login', 'AuthController::login');

$routes->group('api', ['filter' => 'jwtauth'], function($routes) {
    $routes->post('auth/logout', 'AuthController::logout');
    $routes->get('auth/me', 'AuthController::me');
});