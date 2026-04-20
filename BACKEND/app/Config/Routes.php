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
    
    
        // Route principale : /api/employe
        $routes->group('employe', ['namespace' => 'App\Controllers\employee', 'filter' => 'jwtauth'], function($routes) {
            $routes->get('/', 'EmployeeController::getAllEmployees');
            $routes->get('(:num)', 'EmployeeController::getEmployee/$1');
            $routes->post('/', 'EmployeeController::createEmployee');
        });

        // Alias : /api/employee (compatibilité frontend SI-GPRH)
        $routes->group('employee', ['namespace' => 'App\Controllers\employee', 'filter' => 'jwtauth'], function($routes) {
            $routes->get('/', 'EmployeeController::getAllEmployees');
            $routes->get('(:num)', 'EmployeeController::getEmployee/$1');
            $routes->post('/', 'EmployeeController::createEmployee');
        });

    $routes->group('conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('/', 'CongeController::createConge');
        $routes->get('/', 'CongeController::getAllConges');
        $routes->get('by-date-range', 'CongeController::getByDateRange');
        $routes->get('(:num)', 'CongeController::getConge/$1');
        $routes->get('detail/(:num)', 'CongeController::getCongeDetail/$1');
        $routes->get('attestation/(:num)', 'CongeController::exportAttestationPdf/$1');
        $routes->get('export', 'CongeController::exportCsv');
        $routes->post('import', 'CongeController::importCsv');
        $routes->get('export-excel', 'CongeController::exportExcel');
    });

    // Dashboard endpoints
    $routes->group('dashboard', ['namespace' => 'App\Controllers', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('stats', 'DashboardController::getDashboardStats');
        $routes->get('evolution', 'DashboardController::getEvolutionStats');
        $routes->get('employees-on-leave', 'DashboardController::getEmployeesOnLeave');
        $routes->get('pending-reimbursements', 'DashboardController::getPendingReimbursements');
        $routes->get('recent-activity', 'DashboardController::getRecentActivity');
        $routes->get('reimbursement-distribution', 'DashboardController::getReimbursementDistribution');
        $routes->get('top-absent', 'DashboardController::getTopAbsentEmployees');
        $routes->get('top-reimbursements', 'DashboardController::getTopReimbursements');
        $routes->get('absence-kpis', 'DashboardController::getAbsenceKPIs');
    });

    // Validation congé (workflow multi-étapes: CHEF -> RRH -> DAAF -> DG)
    $routes->group('validation_conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('status/(:num)', 'ValidationCongeController::getStatus/$1');
        $routes->get('current/(:num)', 'ValidationCongeController::getCurrentStep/$1');
        $routes->post('approve', 'ValidationCongeController::approveStep');
        $routes->post('reject', 'ValidationCongeController::reject');
        $routes->get('pending/(:num)', 'ValidationCongeController::getPendingForSigner/$1');
        $routes->get('steps/(:num)', 'ValidationCongeController::getStepsForEmployee/$1');
    });

    // Validation par email (sans JWT - lien public sécurisé par token)
    $routes->get('conge/email-validate', '\App\Controllers\conge\ValidationEmailController::handleEmailValidation');

    $routes->group('permission', ['namespace' => 'App\Controllers\permission', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('/', 'PermissionController::createPermission');
        $routes->get('/', 'PermissionController::getAllPermissions');
        $routes->get('(:num)', 'PermissionController::getPermission/$1');
        $routes->get('(:num)/pdf', 'PermissionController::exportPermissionPdf/$1');
        $routes->post('(:num)/validate', 'PermissionController::validatePermission/$1');
        $routes->post('(:num)/reject', 'PermissionController::rejectPermission/$1');
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
        $routes->get('last_dispo/(:any)', 'SoldeCongeController::lastDispo/$1');
        $routes->post('attribuer', 'SoldeCongeController::attribuerManuellement');
        $routes->post('/', 'SoldeCongeController::create');
        $routes->put('(:num)', 'SoldeCongeController::update/$1');
        $routes->delete('(:num)', 'SoldeCongeController::delete/$1');
    });

    // État de Congé (Suivi soldes multi-années)
    $routes->group('etat_conge', ['namespace' => 'App\Controllers\conge', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'EtatCongeController::index');
        $routes->get('years', 'EtatCongeController::getAvailableYears');
        $routes->get('(:num)', 'EtatCongeController::show/$1');
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
        $routes->post('indirect', 'DemandeRembController::createIndirect');
        $routes->post('batch', 'DemandeRembController::createBatch');
        $routes->post('(:num)/valider-rrh', 'DemandeRembController::validerRRH/$1');
        $routes->post('(:num)/valider-daaf', 'DemandeRembController::validerDAAF/$1');
        $routes->post('(:num)/engager', 'DemandeRembController::engager/$1');
        $routes->post('(:num)/payer', 'DemandeRembController::payer/$1');
        $routes->post('(:num)/rejeter', 'DemandeRembController::rejeter/$1');
        $routes->post('(:num)/traiter', 'DemandeRembController::traiter/$1');
        $routes->get('(:num)/pdf', 'DemandeRembController::exportPdf/$1');
        $routes->get('etat/agent/pdf', 'DemandeRembController::exportEtatAgentPdf');
        $routes->get('export/excel', 'DemandeRembController::exportExcel');
        $routes->post('import/excel', 'DemandeRembController::importExcel');
    });

    // États de Remboursement
    $routes->group('etat_remb', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'EtatRembController::index');
        $routes->get('(:num)', 'EtatRembController::show/$1');
        $routes->get('agent/(:num)', 'EtatRembController::getByAgent/$1');
        $routes->post('/', 'EtatRembController::create');
        $routes->post('(:num)/mandater', 'EtatRembController::mandater/$1');
        $routes->post('(:num)/agent-comptable', 'EtatRembController::agentComptable/$1');
        $routes->get('(:num)/pdf', 'EtatPdfController::generateEtatPdf/$1');  // PDF
        $routes->get('(:num)/excel', 'EtatRembController::exportExcel/$1');  // Excel
    });

    // Prises en charge
    $routes->group('prise_en_charge', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'PrisEnChargeController::getAll');
        $routes->get('(:num)', 'PrisEnChargeController::get/$1');
        $routes->post('/', 'PrisEnChargeController::create');
        $routes->post('(:num)/approuver', 'PrisEnChargeController::approuver/$1');
        $routes->get('(:num)/bulletin', 'PrisEnChargeController::genererBulletin/$1');
        $routes->get('employee/(:num)', 'PrisEnChargeController::getByEmployee/$1');
    });

    // Centres de santé
    $routes->group('centre_sante', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'CentreSanteController::index');
        $routes->get('types', 'CentreSanteController::getTypes');
        $routes->get('(:num)', 'CentreSanteController::show/$1');
        $routes->post('/', 'CentreSanteController::create');
        $routes->put('(:num)', 'CentreSanteController::update/$1');
        $routes->delete('(:num)', 'CentreSanteController::delete/$1');
    });

    // Bénéficiaires (conjoints/enfants)
    $routes->group('beneficiaire', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('familles', 'BeneficiaireController::getFamilyList');
        $routes->get('conjoints/(:num)', 'BeneficiaireController::getConjointes/$1');
        $routes->get('enfants/(:num)', 'BeneficiaireController::getEnfants/$1');
        $routes->post('conjoint/(:num)', 'BeneficiaireController::addConjoint/$1');
        $routes->get('conjoint/statuses', 'BeneficiaireController::getStatuses');
        $routes->put('conjoint/status/(:num)', 'BeneficiaireController::updateStatus/$1');
        $routes->post('enfant/(:num)', 'BeneficiaireController::addEnfant/$1');
        $routes->delete('enfant/(:num)', 'BeneficiaireController::deleteEnfant/$1');
    });

    // États de remboursement
    $routes->group('etat_remb', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'EtatRembController::index');
    });

    // Objets de remboursement (articles)
    $routes->group('objet_remboursement', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'ObjetRemboursementController::index');
        $routes->get('(:num)', 'ObjetRemboursementController::show/$1');
        $routes->post('/', 'ObjetRemboursementController::create');
        $routes->delete('(:num)', 'ObjetRemboursementController::delete/$1');
    });

    // Factures
    $routes->group('facture', ['namespace' => 'App\Controllers\remboursement', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'FactureController::index');
        $routes->get('(:num)', 'FactureController::show/$1');
        $routes->post('/', 'FactureController::create');
        $routes->delete('(:num)', 'FactureController::delete/$1');
    });
    
    
    // ========== CHATBOT routes (MODULE BONUS - ISOLÉ) ==========
    $routes->group('chatbot', ['namespace' => 'App\Controllers\chatbot', 'filter' => 'jwtauth'], function($routes) {
        $routes->post('message', 'ChatbotController::sendMessage');
        $routes->get('suggestions', 'ChatbotController::getSuggestions');
        $routes->get('health', 'ChatbotController::health');
    });

    // ========== NOTIFICATIONS ==========
    $routes->group('notifications', ['namespace' => 'App\Controllers\notification', 'filter' => 'jwtauth'], function($routes) {
        $routes->get('/', 'NotificationController::getAll');
        $routes->get('count', 'NotificationController::count');
    });

    // =====================================================================
    // MODULE CARRIÈRE-STAGIAIRE (intégré depuis carriere-stagiaire)
    // =====================================================================

    // Dashboard Carrière
    $routes->group('', ['namespace' => '\\App\\Controllers\\dashboard', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('dashboard-carriere', 'DashboardController::index');
    });

    // Postes (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\poste', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('postes', 'PosteController::index');
        $routes->get('postes/list', 'PosteController::index');
        $routes->get('postes/stats', 'PosteController::stats');
        $routes->get('postes/fonctions', 'PosteController::fonctions');
        $routes->get('postes/(:num)', 'PosteController::show/$1');
        $routes->post('postes/(:num)/competences', 'PosteController::addCompetence/$1');
        $routes->delete('postes/(:num)/competences/(:num)', 'PosteController::removeCompetence/$1/$2');
        $routes->put('postes/(:num)/quota', 'PosteController::updateQuota/$1');
        $routes->get('postes/by-service/(:num)', 'PosteController::byService/$1');
    });

    // Référentiels (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\referentiel', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('directions/list', 'DirectionController::index');
        $routes->get('services/list', 'ServiceController::index');
        $routes->get('positions/list', 'PositionController::index');
        $routes->get('rangs/list', 'ReferentielController::rangs');
        $routes->get('types-entree/list', 'TypeEntreeController::index');
        $routes->get('types-document/list', 'TypeDocumentController::index');
        $routes->get('sorties-type/list', 'SortieTypeController::index');
        $routes->get('types-contrat/list', 'TypeContratController::index');
        $routes->get('referentiels/statuts-armp', 'StatutArmpController::index');
    });

    // Employés (Gestion Carrière — table employe)
    $routes->group('', ['namespace' => '\\App\\Controllers\\employe', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('employes/encadreurs', 'EmployeController::getEncadreurs');
        $routes->get('employes/export/xlsx', 'EmployeController::exportXlsx');
        $routes->get('employes/stats', 'EmployeController::stats');
        $routes->get('employes', 'EmployeController::index');
        $routes->get('employes/list', 'EmployeController::index');
        $routes->get('employes/(:num)/parcours', 'EmployeController::parcours/$1');
        $routes->get('employes/(:num)', 'EmployeController::show/$1');
        $routes->post('employes', 'EmployeController::create');
        $routes->put('employes/(:num)', 'EmployeController::update/$1');
        $routes->get('employes/(:num)/competences', 'EmployeController::getCompetences/$1');
        $routes->post('employes/(:num)/competences', 'EmployeController::addCompetence/$1');
        $routes->delete('employes/(:num)/competences/(:num)', 'EmployeController::removeCompetence/$1/$2');
        $routes->put('employes/(:num)/finir-carriere', 'EmployeController::finirCarriere/$1');
        $routes->get('employes/(:num)/sorties', 'EmployeController::getSorties/$1');
        $routes->post('employes/(:num)/reintegration', 'EmployeController::reintegration/$1');
    });

    // Affectations (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\affectation', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('affectations/stats', 'AffectationController::stats');
        $routes->get('affectations/list', 'AffectationController::index');
        $routes->post('affectations', 'AffectationController::create');
        $routes->get('motifs-affectation', 'AffectationController::motifs');
        $routes->put('affectations/(:num)/cloturer', 'AffectationController::cloturer/$1');
    });

    // Stagiaires (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\stage', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('stagiaires/stats', 'StagiaireController::stats');
        $routes->get('stagiaires', 'StagiaireController::index');
        $routes->post('stagiaires', 'StagiaireController::create');
        $routes->get('stagiaires/(:num)', 'StagiaireController::show/$1');
        $routes->put('stagiaires/(:num)', 'StagiaireController::update/$1');
        $routes->delete('stagiaires/(:num)', 'StagiaireController::delete/$1');
    });

    // Stages (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\stage', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('stages/stats', 'StageController::stats');
        $routes->get('stages', 'StageController::index');
        $routes->post('stages', 'StageController::create');
        $routes->get('stages/(:num)', 'StageController::show/$1');
        $routes->put('stages/(:num)', 'StageController::update/$1');
        $routes->delete('stages/(:num)', 'StageController::delete/$1');
        $routes->post('stages/(:num)/carriere', 'StageController::assignCarriere/$1');
        $routes->get('stages/(:num)/convention', 'StageController::telechargerConvention/$1');
        $routes->get('stages/(:num)/demande-attestation', 'StageController::telechargerDemandeAttestation/$1');
        $routes->get('stages/demandes', 'StageController::listerDemandesStage');
        $routes->get('stages/demandes/stats', 'StageController::statsDemandes');
        $routes->put('stages/demandes/(:num)/valider', 'StageController::validerDemandeStage/$1');
    });

    // Établissements (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\stage', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('etablissements', 'EtablissementController::index');
        $routes->post('etablissements', 'EtablissementController::create');
        $routes->get('etablissements/(:num)', 'EtablissementController::show/$1');
        $routes->put('etablissements/(:num)', 'EtablissementController::update/$1');
        $routes->delete('etablissements/(:num)', 'EtablissementController::delete/$1');
    });

    // Assiduité (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\stage', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('assiduites', 'AssiduiteController::index');
        $routes->post('assiduites', 'AssiduiteController::create');
        $routes->get('assiduites/(:num)', 'AssiduiteController::show/$1');
        $routes->put('assiduites/(:num)', 'AssiduiteController::update/$1');
        $routes->delete('assiduites/(:num)', 'AssiduiteController::delete/$1');
    });

    // Évaluation de stages (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\stage', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('eval-stages', 'EvalStageController::index');
        $routes->post('eval-stages', 'EvalStageController::create');
        $routes->get('eval-stages/(:num)', 'EvalStageController::show/$1');
        $routes->put('eval-stages/(:num)', 'EvalStageController::update/$1');
        $routes->delete('eval-stages/(:num)', 'EvalStageController::delete/$1');
        $routes->get('stages/(:num)/eval', 'EvalStageController::getByStage/$1');
    });

    // Compétences (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\competence', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('competences/stats', 'CompetenceController::stats');
        $routes->get('competences', 'CompetenceController::index');
        $routes->post('competences', 'CompetenceController::create');
        $routes->get('competences/(:num)', 'CompetenceController::show/$1');
        $routes->put('competences/(:num)', 'CompetenceController::update/$1');
        $routes->delete('competences/(:num)', 'CompetenceController::delete/$1');
        $routes->get('competences/domaines', 'CompetenceController::domaines');
    });

    // Documents (Gestion Carrière)
    $routes->group('', ['namespace' => '\\App\\Controllers\\document', 'filter' => 'jwtauth'], function ($routes) {
        $routes->get('documents/stats', 'DocumentController::stats');
        $routes->post('documents/demande', 'DocumentController::creerDemande');
        $routes->get('documents/demandes', 'DocumentController::listerDemandes');
        $routes->put('documents/demandes/(:num)/valider', 'DocumentController::validerDemande/$1');
        $routes->get('documents/demandes/(:num)/pdf', 'DocumentController::telechargerPdfDemande/$1');
    });

});
