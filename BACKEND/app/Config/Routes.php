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
      
    });
    
    
        $routes->group('employee', ['namespace' => 'App\Controllers\employee', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'EmployeeController::getAllEmployees');
        $routes->get('(:num)', 'EmployeeController::getEmployee/$1');
        $routes->post('/', 'EmployeeController::createEmployee');
        
    });

    $routes->group('conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('/', 'CongeController::createConge');
        $routes->get('/', 'CongeController::getAllConges');
        $routes->get('(:num)', 'CongeController::getConge/$1');
        $routes->get('detail/(:num)', 'CongeController::getCongeDetail/$1');
        $routes->get('attestation/(:num)', 'CongeController::exportAttestationPdf/$1');
        $routes->get('export', 'CongeController::exportCsv');
        $routes->post('import', 'CongeController::importCsv');
        $routes->get('export-excel', 'CongeController::exportExcel');
    });

    // Validation congé (workflow multi-étapes: CHEF -> RRH -> DAAF -> DG)
    $routes->group('validation_conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('status/(:num)', 'ValidationCongeController::getStatus/$1');
        $routes->get('current/(:num)', 'ValidationCongeController::getCurrentStep/$1');
        $routes->post('approve', 'ValidationCongeController::approveStep');
        $routes->post('reject', 'ValidationCongeController::reject');
        $routes->get('pending/(:num)', 'ValidationCongeController::getPendingForSigner/$1');
    });

    $routes->group('permission', ['namespace' => 'App\Controllers\permission', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('/', 'PermissionController::createPermission');
        $routes->get('/', 'PermissionController::getAllPermissions');
        $routes->get('(:num)', 'PermissionController::getPermission/$1');
    });

    $routes->group('interim_conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('/', 'InterimCongeController::createInterimConge');
        $routes->get('/', 'InterimCongeController::getAllInterimConges');
    });

    $routes->group('type_conge', ['namespace' => 'App\Controllers\conge','filter' => 'jwtauth'],function($routes) {
        $routes->get('/', 'TypeCongeController::index');
    });

    $routes->group('region', ['namespace' => 'App\Controllers\conge','filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'RegionController::index');
    });

    $routes->group('solde_conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'SoldeCongeController::index');
        $routes->get('(:num)', 'SoldeCongeController::show/$1');
        $routes->post('/', 'SoldeCongeController::create');
        $routes->put('(:num)', 'SoldeCongeController::update/$1');
        $routes->delete('(:num)', 'SoldeCongeController::delete/$1');
        $routes->get('last_dispo/(:any)', 'SoldeCongeController::lastDispo/$1');
    });

    $routes->group('debit_solde_cng', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'DebitSoldeCngController::index');
        $routes->get('(:num)', 'DebitSoldeCngController::show/$1');
        $routes->post('/', 'DebitSoldeCngController::create');
        $routes->put('(:num)', 'DebitSoldeCngController::update/$1');
        $routes->delete('(:num)', 'DebitSoldeCngController::delete/$1');
    });

    $routes->group('decision', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'DecisionController::index');
        $routes->get('(:num)', 'DecisionController::show/$1');
        $routes->post('/', 'DecisionController::create');
        $routes->put('(:num)', 'DecisionController::update/$1');
        $routes->delete('(:num)', 'DecisionController::delete/$1');
    });

    // Interruption de congé
    $routes->group('interruption', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('conge/(:num)', 'InterruptionController::getByConge/$1');
        $routes->get('active/(:num)', 'InterruptionController::getActiveLeavesForEmployee/$1');
        $routes->post('/', 'InterruptionController::create');
        $routes->post('preview', 'InterruptionController::previewRestoration');
        $routes->get('attestation/(:num)', 'InterruptionController::generateAttestation/$1');
    });

    $routes->group('solde_permission', ['namespace' => 'App\Controllers\permission', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'SoldePermissionController::index');
        $routes->get('(:num)', 'SoldePermissionController::show/$1');
        $routes->post('/', 'SoldePermissionController::create');
        $routes->put('(:num)', 'SoldePermissionController::update/$1');
        $routes->delete('(:num)', 'SoldePermissionController::delete/$1');
        $routes->get('last_dispo/(:any)', 'SoldePermissionController::lastDispo/$1');
    });

    $routes->group('debit_solde_prm', ['namespace' => 'App\Controllers\permission', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'DebitSoldePrmController::index');
        $routes->get('(:num)', 'DebitSoldePrmController::show/$1');
        $routes->post('/', 'DebitSoldePrmController::create');
        $routes->put('(:num)', 'DebitSoldePrmController::update/$1');
        $routes->delete('(:num)', 'DebitSoldePrmController::delete/$1');
    });

    // ========== REMBOURSEMENT MODULE ==========

    // Centres de santé
    $routes->group('centre_sante', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'CentreSanteController::index');
        $routes->get('(:num)', 'CentreSanteController::show/$1');
        $routes->post('/', 'CentreSanteController::create');
        $routes->put('(:num)', 'CentreSanteController::update/$1');
        $routes->delete('(:num)', 'CentreSanteController::delete/$1');
    });

    // Conventions
    $routes->group('convention', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'ConventionController::index');
        $routes->get('(:num)', 'ConventionController::show/$1');
        $routes->post('/', 'ConventionController::create');
        $routes->put('(:num)', 'ConventionController::update/$1');
        $routes->delete('(:num)', 'ConventionController::delete/$1');
    });

    // Demandes de remboursement - Workflow Agent
    $routes->group('remboursement', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'DemandeRembController::getAllDemandes');
        $routes->get('(:num)', 'DemandeRembController::getDemande/$1');
        $routes->get('family/(:num)', 'DemandeRembController::getFamilyMembers/$1');
        $routes->post('/indirect', 'DemandeRembController::createIndirect');
        $routes->post('(:num)/valider-rrh', 'DemandeRembController::validerRRH/$1');
        $routes->post('(:num)/valider-daaf', 'DemandeRembController::validerDAAF/$1');
        $routes->post('(:num)/engager', 'DemandeRembController::engager/$1');
        $routes->post('(:num)/payer', 'DemandeRembController::payer/$1');
        $routes->post('(:num)/rejeter', 'DemandeRembController::rejeter/$1');
        $routes->get('(:num)/pdf', 'DemandeRembController::exportPdf/$1');
        $routes->get('etat/agent/pdf', 'DemandeRembController::exportEtatAgentPdf');
    });

    // Prises en charge
    $routes->group('prise_en_charge', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'PrisEnChargeController::getAll');
        $routes->get('(:num)', 'PrisEnChargeController::get/$1');
        $routes->post('/', 'PrisEnChargeController::create');
        $routes->post('(:num)/valider', 'PrisEnChargeController::valider/$1');
        $routes->get('(:num)/bulletin', 'PrisEnChargeController::genererBulletin/$1');
        $routes->get('employee/(:num)', 'PrisEnChargeController::getByEmployee/$1');
    });

    // Bénéficiaires (conjoints/enfants)
    $routes->group('beneficiaire', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('conjoints/(:num)', 'BeneficiaireController::getConjointes/$1');
        $routes->get('enfants/(:num)', 'BeneficiaireController::getEnfants/$1');
        $routes->post('conjoint/(:num)', 'BeneficiaireController::addConjoint/$1');
        $routes->post('enfant/(:num)', 'BeneficiaireController::addEnfant/$1');
    });

    // États de remboursement
    $routes->group('etat_remb', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'EtatRembController::index');
    });

});
