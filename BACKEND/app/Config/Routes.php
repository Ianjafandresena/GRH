<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// ========== API Routes ==========
$routes->group('api', ['namespace' => 'App\Controllers\auth'], function($routes) {
    
    // ===== Routes publiques (sans JWT) =====
    $routes->post('auth/login', 'AuthController::login');
    $routes->post('auth/logout', 'AuthController::logout');
    
    // ===== Routes protégées (avec JWT) =====
    $routes->group('', ['filter' => 'jwtauth'], function($routes) {
        // Déconnexion
        $routes->post('auth/logout', 'AuthController::logout');
        
        //  Route de test Hello World
        $routes->get('test/hello', 'TestController::hello');
        
        // Ajoute ici toutes tes autres routes protégées...
    });
});
