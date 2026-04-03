<?php
namespace App\Controllers\employe;

use CodeIgniter\RESTful\ResourceController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class EmployeController extends ResourceController
{
    protected $employeModel;
    protected $format = 'json';

    public function __construct()
    {
        $this->employeModel = model('App\Models\employe\EmployeModel');
    }

    /**
     * Récupère les statistiques rapides pour la liste des employés
     */
    public function stats()
    {
        $db = \Config\Database::connect();

        // Total
        $total = $this->employeModel->countAllResults();

        // Sortis
        $sortis = $this->employeModel->where('date_sortie IS NOT NULL')->countAllResults();

        // En service (pos_code = 1) et En cessation (pos_code = 2) via pos_emp
        // On prend la dernière position de chaque employé
        $subquery = "SELECT DISTINCT ON (emp_code) pos_code FROM pos_emp ORDER BY emp_code, date_ DESC NULLS LAST";
        $results = $db->query("SELECT pos_code, count(*) as count FROM ($subquery) as last_pos GROUP BY pos_code")->getResultArray();

        $enService = 0;
        $enCessation = 0;

        foreach ($results as $res) {
            if ($res['pos_code'] == 1)
                $enService = (int) $res['count'];
            if ($res['pos_code'] == 2)
                $enCessation = (int) $res['count'];
        }

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'en_service' => $enService,
                'en_cessation' => $enCessation,
                'sortie' => $sortis
            ]
        ]);
    }

    /**
     * Créer un nouvel employé avec affectation initiale optionnelle
     */
    public function create()
    {
        try {
            $data = $this->request->getJSON(true);

            log_message('info', '=== CREATION EMPLOYE ===');
            log_message('info', 'Données reçues: ' . json_encode($data));

            // Extraction du contact, de l'affectation initiale et du statut ARMP
            $contactNumero = $data['emp_contact'] ?? null;
            $affectationInitiale = $data['affectation_initiale'] ?? null;
            $sttArmpCode = $data['stt_armp_code'] ?? null;

            // L'affectation est maintenant obligatoire
            if (!$affectationInitiale) {
                return $this->failValidationErrors([
                    'affectation_initiale' => 'L\'affectation initiale est obligatoire lors de la création d\'un employé'
                ]);
            }

            // Le statut ARMP est obligatoire
            if (!$sttArmpCode) {
                return $this->failValidationErrors([
                    'stt_armp_code' => 'Le statut ARMP est obligatoire lors de la création d\'un employé'
                ]);
            }

            if (isset($data['emp_contact'])) {
                unset($data['emp_contact']);
            }
            if (isset($data['affectation_initiale'])) {
                unset($data['affectation_initiale']);
            }
            if (isset($data['stt_armp_code'])) {
                unset($data['stt_armp_code']);
            }

            $db = \Config\Database::connect();
            $db->transStart();

            // 1. Création de l'employé (sans matricule pour l'instant)
            // Le matricule sera généré après l'insertion avec l'ID généré
            if (isset($data['emp_matricule'])) {
                unset($data['emp_matricule']); // Ne pas envoyer de matricule, il sera généré
            }

            if (!$this->employeModel->insert($data)) {
                $errors = $this->employeModel->errors();
                if (empty($errors)) {
                    $dbError = $this->employeModel->db->error();
                    log_message('error', 'Erreur DB Employé: ' . json_encode($dbError));
                    return $this->failServerError('Erreur insertion Employé SQL: ' . ($dbError['message'] ?? 'Erreur inconnue'));
                }
                log_message('error', 'Erreurs validation Employé: ' . json_encode($errors));
                return $this->failValidationErrors($errors);
            }

            $empCode = $this->employeModel->getInsertID();

            // Générer le matricule au format EMPXXX basé sur l'ID généré
            $matricule = 'EMP' . str_pad($empCode, 3, '0', STR_PAD_LEFT);

            // Mettre à jour l'employé avec le matricule généré
            $this->employeModel->update($empCode, ['emp_matricule' => $matricule]);

            // 2. Création du contact si fourni
            if ($contactNumero) {
                $contactModel = model('App\Models\employe\ContactModel');

                // Vérifier si le numéro existe déjà
                $existingContact = $contactModel->where('numero', $contactNumero)->first();
                if ($existingContact) {
                    $db->transRollback();
                    return $this->failValidationErrors([
                        'emp_contact' => 'Ce numéro de téléphone est déjà utilisé par un autre employé'
                    ]);
                }

                $contactData = [
                    'numero' => $contactNumero,
                    'emp_code' => $empCode
                ];

                if (!$contactModel->insert($contactData)) {
                    $errors = $contactModel->errors();
                    if (empty($errors)) {
                        $dbError = $contactModel->db->error();
                        // Vérifier si c'est une erreur de contrainte unique
                        if (isset($dbError['code']) && (string) $dbError['code'] === '23505') {
                            $db->transRollback();
                            return $this->failValidationErrors([
                                'emp_contact' => 'Ce numéro de téléphone est déjà utilisé par un autre employé'
                            ]);
                        }
                        $db->transRollback();
                        return $this->failServerError('Erreur insertion Contact: ' . ($dbError['message'] ?? 'Erreur inconnue'));
                    }
                    $db->transRollback();
                    return $this->failValidationErrors($errors);
                }
            }

            // 3. Création de l'affectation initiale (obligatoire)
            $affectationModel = model('App\Models\affectation\AffectationModel');

            // Vérifier qu'aucune affectation active n'existe (normalement impossible pour un nouvel employé)
            $existingAffectation = $affectationModel
                ->where('emp_code', $empCode)
                ->where('affec_etat', 'active')
                ->first();

            if ($existingAffectation) {
                return $this->failValidationErrors([
                    'affectation_initiale' => 'Une affectation active existe déjà pour cet employé'
                ]);
            }

            // Copier la date d'entrée de l'employé vers l'affectation si non fournie
            $dateEntreeEmploye = $data['date_entree'] ?? null;
            if (!$affectationInitiale['affec_date_debut'] && $dateEntreeEmploye) {
                $affectationInitiale['affec_date_debut'] = $dateEntreeEmploye;
            }

            // Validation selon le type de contrat (on récupère le nom par le code)
            $tcontrat_code = $affectationInitiale['tcontrat_code'] ?? null;
            $typeContratNom = '';
            if ($tcontrat_code) {
                $tc = $db->table('type_contrat')->where('tcontrat_code', $tcontrat_code)->get()->getRowArray();
                $typeContratNom = $tc['tcontrat_nom'] ?? '';
            }

            $dateDebut = $affectationInitiale['affec_date_debut'] ?? $dateEntreeEmploye;

            if ($typeContratNom === 'Fonctionnaire') {
                // Fonctionnaire : pas de date de fin
                $affectationInitiale['affec_date_fin'] = null;
            } elseif ($typeContratNom === 'ELD' || $typeContratNom === 'EFA') {
                // ELD et EFA se comportent comme un "Fonctionnaire" au niveau du workflow (pas de fin auto)
                if (empty($affectationInitiale['affec_date_fin'])) {
                    $affectationInitiale['affec_date_fin'] = null;
                }
            }

            // Préparer les données d'affectation
            $affectationData = [
                'emp_code' => $empCode,
                'pst_code' => $affectationInitiale['pst_code'],
                'm_aff_code' => $affectationInitiale['m_aff_code'],
                'affec_date_debut' => $affectationInitiale['affec_date_debut'],
                'affec_date_fin' => $affectationInitiale['affec_date_fin'] ?? null,
                'tcontrat_code' => $tcontrat_code,
                'affec_commentaire' => $affectationInitiale['affec_commentaire'] ?? null,
                'affec_etat' => 'active'
            ];

            if (!$affectationModel->insert($affectationData)) {
                $errors = $affectationModel->errors();
                return $this->failValidationErrors($errors);
            }

            // Mettre à jour occupation_poste : incrémenter nb_occupe
            $occupationPosteModel = model('App\Models\poste\OccupationPosteModel');
            $occupationPosteModel->incrementerOccupe($affectationData['pst_code']);

            // 4. Créer une entrée dans pos_emp pour indiquer que l'employé est en service (pos_code = 1)
            try {
                $posEmpData = [
                    'emp_code' => $empCode,
                    'pos_code' => 1, // En service
                    'date_' => $dateEntreeEmploye ?? date('Y-m-d')
                ];

                $db->table('pos_emp')->insert($posEmpData);
            } catch (\Exception $e) {
                log_message('error', 'Erreur création pos_emp: ' . $e->getMessage());
                // Ne pas faire échouer la transaction pour cette erreur, mais la logger
            }

            // 5. Créer une entrée dans statut_emp pour enregistrer le statut ARMP initial
            try {
                $statutEmpData = [
                    'emp_code' => $empCode,
                    'stt_armp_code' => $sttArmpCode,
                    'date_' => $dateEntreeEmploye ?? date('Y-m-d')
                ];

                $db->table('statut_emp')->insert($statutEmpData);
                log_message('info', 'Statut ARMP initial créé: ' . json_encode($statutEmpData));
            } catch (\Exception $e) {
                log_message('error', 'Erreur création statut_emp: ' . $e->getMessage());
                $db->transRollback();
                return $this->failServerError('Erreur lors de l\'enregistrement du statut ARMP: ' . $e->getMessage());
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                $error = $db->error();
                return $this->failServerError('Erreur de transaction: ' . ($error['message'] ?? 'Erreur inconnue'));
            }

            $employe = $this->employeModel->find($empCode);

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Employé créé avec succès et affectation initiale créée',
                'data' => $employe
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Lister tous les employés avec filtres optionnels
     */
    public function index()
    {
        $filters = $this->request->getVar();

        log_message('info', '[EmployeController::index] GET filters: ' . json_encode($filters));
        $builder = $this->employeModel->builder();

        // Construire la requête avec jointures pour les filtres
        $builder->select('employe.*, type_entree.e_type_motif, pe.pos_code as pos_code, p.pos_type as pos_type, se.stt_armp_code')
            ->join('type_entree', 'employe.e_type_code = type_entree.e_type_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, pos_code, date_ FROM pos_emp ORDER BY emp_code, date_ DESC NULLS LAST) pe', 'pe.emp_code = employe.emp_code', 'left', false)
            ->join('position_ p', 'p.pos_code = pe.pos_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, stt_armp_code, date_ FROM statut_emp ORDER BY emp_code, date_ DESC NULLS LAST) se', 'se.emp_code = employe.emp_code', 'left', false);

        // Filtres
        $needAffectationJoin = !empty($filters['pst_code']) || !empty($filters['srvc_code']);
        if ($needAffectationJoin) {
            // Filtrage sur affectation active (via la colonne affec_etat)
            $builder->join('affectation', "affectation.emp_code = employe.emp_code AND affectation.affec_etat = 'active'", 'inner');
        }

        if (!empty($filters['srvc_code'])) {
            // Filtrer par service via poste
            $builder->join('poste', 'poste.pst_code = affectation.pst_code', 'inner');
            $builder->where('poste.srvc_code', $filters['srvc_code']);
        }

        if (!empty($filters['pst_code'])) {
            // Filtrer par poste
            $builder->where('affectation.pst_code', $filters['pst_code']);
        }

        if (!empty($filters['statut'])) {
            // Filtrer par statut (actif/inactif basé sur date_sortie)
            if ($filters['statut'] === 'actif') {
                $builder->where('employe.date_sortie IS NULL');
            } elseif ($filters['statut'] === 'inactif') {
                $builder->where('employe.date_sortie IS NOT NULL');
            }
        }

        if (!empty($filters['pos_code'])) {
            $builder->where('pe.pos_code', $filters['pos_code']);
        }

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            if ($q !== '') {
                $builder->groupStart()
                    ->like('employe.emp_nom', $q)
                    ->orLike('employe.emp_prenom', $q)
                    ->orLike('employe.emp_matricule', $q)
                    ->orLike('employe.emp_im_armp', $q)
                    ->orLike('employe.emp_im_etat', $q)
                    ->orLike('employe.emp_mail', $q)
                    ->groupEnd();
            }
        }

        $compiledSql = $builder->getCompiledSelect(false);
        log_message('info', '[EmployeController::index] SQL: ' . $compiledSql);

        $builder->orderBy('employe.date_entree', 'DESC');
        $builder->orderBy('employe.emp_code', 'DESC');
        $employes = $builder->get()->getResultArray();

        log_message('info', '[EmployeController::index] result count: ' . count($employes));

        // Récupérer les contacts pour tous les employés
        $contactModel = model('App\Models\employe\ContactModel');
        $contacts = $contactModel->findAll();
        $contactsMap = [];
        foreach ($contacts as $contact) {
            $contactsMap[$contact['emp_code']] = $contact['numero'];
        }

        // Pour chaque employé, calculer l'ancienneté et l'année de retraite
        foreach ($employes as &$employe) {
            // Ajouter le contact depuis la table contact
            $employe['emp_contact'] = $contactsMap[$employe['emp_code']] ?? null;

            // Calcul de l'ancienneté
            $employe['anciennete'] = $this->calculerAnciennete(
                $employe['date_entree'] ?? null,
                $employe['date_sortie'] ?? null
            );

            // Calcul de l'année de retraite
            $employe['annee_retraite'] = $this->calculerAnneeRetraite(
                $employe['emp_datenaissance'] ?? null,
                $employe['emp_sexe'] ?? null
            );
        }

        // Pour chaque employé, récupérer l'affectation active si demandé
        if (!empty($filters['with_affectation'])) {
            $affectationModel = model('App\Models\affectation\AffectationModel');
            foreach ($employes as &$employe) {
                $affectationResult = $affectationModel
                    ->select('affectation.*, poste.pst_fonction, motif_affectation.m_aff_motif, type_contrat.tcontrat_nom')
                    ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
                    ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
                    ->join('motif_affectation', 'motif_affectation.m_aff_code = affectation.m_aff_code', 'left')
                    ->where('affectation.emp_code', $employe['emp_code'])
                    ->where('affectation.affec_etat', 'active')
                    ->get();
                $affectation = null;
                if ($affectationResult && $affectationResult !== false) {
                    $affectation = $affectationResult->getRowArray();
                }
                $employe['affectation_active'] = $affectation;
            }
        }

        return $this->respond([
            'status' => 'success',
            'count' => count($employes),
            'data' => $employes
        ], 200);
    }

    /**
     * Récupérer un employé par son ID avec son affectation active
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $db = \Config\Database::connect();
        $result = $db->table('employe')
            ->select('employe.*, type_entree.e_type_motif, pe.pos_code as pos_code, p.pos_type as pos_type, sa.stt_armp_statut, se.stt_armp_code')
            ->join('type_entree', 'employe.e_type_code = type_entree.e_type_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, pos_code, date_ FROM pos_emp ORDER BY emp_code, date_ DESC NULLS LAST) pe', 'pe.emp_code = employe.emp_code', 'left', false)
            ->join('position_ p', 'p.pos_code = pe.pos_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, stt_armp_code, date_ FROM statut_emp ORDER BY emp_code, date_ DESC NULLS LAST) se', 'se.emp_code = employe.emp_code', 'left', false)
            ->join('statut_armp sa', 'sa.stt_armp_code = se.stt_armp_code', 'left')
            ->where('employe.emp_code', $id)
            ->get();

        $employe = null;
        if ($result && $result !== false) {
            $employe = $result->getRowArray();
        }

        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        // Récupérer l'affectation active
        $affectationModel = model('App\Models\affectation\AffectationModel');
        $affectationResult = $affectationModel
            ->select('affectation.*, poste.pst_fonction, poste.pst_mission, service.srvc_nom, motif_affectation.m_aff_motif, type_contrat.tcontrat_nom')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('service', 'poste.srvc_code = service.srvc_code', 'left')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
            ->join('motif_affectation', 'motif_affectation.m_aff_code = affectation.m_aff_code', 'left')
            ->where('affectation.emp_code', $id)
            ->where('affectation.affec_etat', 'active')
            ->get();

        $affectation = null;
        if ($affectationResult && $affectationResult !== false) {
            $affectation = $affectationResult->getRowArray();
        }

        $employe['affectation_active'] = $affectation;

        // Récupérer le statut ARMP s'il n'est pas déjà dans $employe
        if (!isset($employe['stt_armp_code'])) {
            $stt = $db->table('statut_emp')
                ->where('emp_code', $id)
                ->orderBy('date_', 'DESC')
                ->get()
                ->getRowArray();
            $employe['stt_armp_code'] = $stt['stt_armp_code'] ?? null;
        }

        // Récupérer le type d'entrée
        $typeEntreeModel = model('App\Models\referentiel\TypeEntreeModel');
        $typeEntree = $typeEntreeModel->find($employe['e_type_code']);
        $employe['type_entree'] = $typeEntree;

        // Récupérer le contact depuis la table contact
        $contactModel = model('App\Models\employe\ContactModel');
        $contact = $contactModel->where('emp_code', $id)->first();
        $employe['emp_contact'] = $contact ? $contact['numero'] : null;

        // Calculer l'ancienneté et l'année de retraite
        $employe['anciennete'] = $this->calculerAnciennete(
            $employe['date_entree'] ?? null,
            $employe['date_sortie'] ?? null
        );

        $employe['annee_retraite'] = $this->calculerAnneeRetraite(
            $employe['emp_datenaissance'] ?? null,
            $employe['emp_sexe'] ?? null
        );

        $this->response->setContentType('application/json; charset=utf-8');
        return $this->respond([
            'status' => 'success',
            'data' => $employe
        ]);
    }

    public function parcours($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $employe = $this->employeModel->find($id);
        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        $db = \Config\Database::connect();
        $builder = $db->table('affectation');

        $builder->select(
            'affectation.*, ' .
            'poste.pst_fonction, poste.pst_mission, ' .
            'service.srvc_nom, ' .
            'motif_affectation.m_aff_motif, ' .
            'type_contrat.tcontrat_nom as affec_type_contrat, ' .
            'COALESCE(d_direct.dir_nom, d_service.dir_nom) as dir_nom'
        )
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('service', 'poste.srvc_code = service.srvc_code', 'left')
            ->join('motif_affectation', 'motif_affectation.m_aff_code = affectation.m_aff_code', 'left')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
            ->join('direction as d_direct', 'poste.dir_code = d_direct.dir_code', 'left')
            ->join('direction as d_service', 'service.dir_code = d_service.dir_code', 'left')
            ->where('affectation.emp_code', $id)
            ->orderBy('affectation.affec_date_debut', 'DESC')
            ->orderBy('affectation.affec_code', 'DESC');

        $result = $builder->get();
        $rows = [];
        if ($result && $result !== false) {
            $rows = $result->getResultArray();
        }

        // Normaliser les données pour le frontend
        foreach ($rows as &$row) {
            // Compatibilité avec l'interface frontend qui attend un tableau de directions
            if (!empty($row['dir_nom'])) {
                $row['directions'] = [$row['dir_nom']];
            } else {
                $row['directions'] = [];
            }

            // Ajouter un type pour identifier les affectations
            $row['type'] = 'affectation';
        }

        // Récupérer les sorties de l'employé
        $sortieModel = model('App\Models\employe\SortieModel');
        $sorties = $sortieModel->getSortiesByEmploye($id);

        // Convertir les sorties en format compatible avec le parcours
        foreach ($sorties as $sortie) {
            $rows[] = [
                'type' => 'sortie',
                'date_sortie' => $sortie['date_sortie'],
                's_type_code' => $sortie['s_type_code'],
                's_type_motif' => $sortie['s_type_motif'] ?? null,
                'commentaire' => $sortie['commentaire'] ?? null,
                'affec_date_debut' => $sortie['date_sortie'], // Pour le tri
                'affec_date_fin' => $sortie['date_sortie'], // Pour le tri
            ];
        }

        // Trier par date (plus récent en premier)
        usort($rows, function ($a, $b) {
            $dateA = $a['affec_date_debut'] ?? $a['date_sortie'] ?? '';
            $dateB = $b['affec_date_debut'] ?? $b['date_sortie'] ?? '';
            return strcmp($dateB, $dateA); // Tri décroissant
        });

        return $this->respond([
            'status' => 'success',
            'count' => count($rows),
            'data' => $rows,
        ], 200);
    }

    /**
     * Mettre à jour un employé
     */
    public function update($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $employe = $this->employeModel->find($id);
        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        try {
            $data = $this->request->getJSON(true);
            log_message('info', '=== MISE À JOUR EMPLOYÉ ID: ' . $id . ' ===');
            log_message('info', 'Données reçues: ' . json_encode($data));

            if (empty($data)) {
                log_message('warning', 'Tentative de mise à jour avec des données vides pour ID: ' . $id);
                return $this->failValidationErrors(['message' => 'Aucune donnée fournie']);
            }

            // Extraction du contact et du statut ARMP si présents
            $contactNumero = $data['emp_contact'] ?? null;
            $newStatutCode = $data['stt_armp_code'] ?? null;

            if (isset($data['emp_contact'])) {
                unset($data['emp_contact']);
            }
            if (isset($data['stt_armp_code'])) {
                unset($data['stt_armp_code']);
            }

            // Plus besoin de vérifier l'unicité ici car EmployeModel s'en charge avec is_unique[...,{emp_code}]
            // Cela évite les erreurs de validation manuelles redondantes.

            $db = \Config\Database::connect();
            $db->transStart();

            // Ajouter l'ID aux données pour permettre à la validation is_unique d'ignorer cet enregistrement
            $data['emp_code'] = $id;

            // Mise à jour de l'employé
            if (!$this->employeModel->update($id, $data)) {
                $db->transRollback();
                $errors = $this->employeModel->errors();
                log_message('error', 'Erreur validation EmployeModel pour ID ' . $id . ': ' . json_encode($errors));
                if (empty($errors)) {
                    $dbError = $this->employeModel->db->error();
                    log_message('error', 'Erreur SQL critique lors de l\'update ID ' . $id . ': ' . json_encode($dbError));
                    return $this->failServerError('Erreur lors de la mise à jour de l\'employé: ' . ($dbError['message'] ?? 'Erreur inconnue'));
                }
                return $this->failValidationErrors($errors);
            }

            // Mise à jour du contact si fourni
            if ($contactNumero !== null) {
                try {
                    $contactModel = model('App\Models\employe\ContactModel');
                    $existingContact = $contactModel->where('emp_code', $id)->first();

                    if ($existingContact) {
                        // Récupérer l'ID du contact (PostgreSQL retourne en minuscules)
                        $contactId = $existingContact['id_contact'] ?? $existingContact['Id_contact'] ?? null;

                        if (!$contactId) {
                            $db->transRollback();
                            log_message('error', 'ID contact introuvable. Données: ' . json_encode($existingContact));
                            return $this->failServerError('Erreur: ID du contact introuvable');
                        }

                        if ($contactNumero && trim($contactNumero) !== '') {
                            // Mettre à jour le contact existant
                            if (
                                !$contactModel->update($contactId, [
                                    'id_contact' => $contactId,
                                    'numero' => trim($contactNumero)
                                ])
                            ) {
                                $db->transRollback();
                                $contactErrors = $contactModel->errors();
                                log_message('error', 'Erreur mise à jour contact: ' . json_encode($contactErrors));
                                return $this->failValidationErrors($contactErrors ?: [
                                    'emp_contact' => 'Erreur lors de la mise à jour du contact'
                                ]);
                            }
                        } else {
                            // Supprimer le contact si vide
                            $contactModel->delete($contactId);
                        }
                    } elseif ($contactNumero && trim($contactNumero) !== '') {
                        // Créer un nouveau contact
                        if (
                            !$contactModel->insert([
                                'numero' => trim($contactNumero),
                                'emp_code' => $id
                            ])
                        ) {
                            $db->transRollback();
                            $contactErrors = $contactModel->errors();
                            log_message('error', 'Erreur création contact: ' . json_encode($contactErrors));
                            return $this->failValidationErrors($contactErrors ?: [
                                'emp_contact' => 'Erreur lors de la création du contact'
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    $db->transRollback();
                    log_message('error', 'Exception gestion contact: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
                    return $this->failServerError('Erreur lors de la gestion du contact: ' . $e->getMessage());
                }
            }

            // Mise à jour du statut ARMP si fourni (non vide) et différent
            if ($newStatutCode !== null && $newStatutCode !== '') {
                try {
                    // Récupérer le statut actuel
                    $currentStatut = $db->table('statut_emp')
                        ->where('emp_code', $id)
                        ->orderBy('date_', 'DESC')
                        ->get()
                        ->getRowArray();

                    // Si le statut a changé, créer une nouvelle entrée
                    if (!$currentStatut || $currentStatut['stt_armp_code'] != $newStatutCode) {
                        $statutEmpData = [
                            'emp_code' => $id,
                            'stt_armp_code' => $newStatutCode,
                            'date_' => date('Y-m-d')
                        ];

                        $db->table('statut_emp')->insert($statutEmpData);
                        log_message('info', 'Statut ARMP mis à jour pour employé ' . $id . ': ' . json_encode($statutEmpData));
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Erreur mise à jour statut_emp: ' . $e->getMessage());
                    $db->transRollback();
                    return $this->failServerError('Erreur lors de la mise à jour du statut ARMP: ' . $e->getMessage());
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                return $this->failServerError('Erreur de transaction');
            }

            $employe = $this->employeModel->find($id);

            return $this->respond([
                'status' => 'success',
                'message' => 'Employé mis à jour avec succès',
                'data' => $employe
            ]);
        } catch (\Exception $e) {
            log_message('error', 'EXCEPTION CRITIQUE (update employe ' . $id . '): ' . $e->getMessage());
            log_message('error', 'Trace: ' . $e->getTraceAsString());
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Récupérer les compétences d'un employé
     */
    public function getCompetences($empCode = null)
    {
        if (!$empCode) {
            return $this->failNotFound('ID employé manquant');
        }

        $db = \Config\Database::connect();
        $builder = $db->table('comp_employe');

        $competences = $builder
            ->select('comp_employe.*, competence.comp_intitule, competence.comp_domaine, competence.comp_description')
            ->join('competence', 'competence.comp_code = comp_employe.comp_code')
            ->where('comp_employe.emp_code', $empCode)
            ->orderBy('competence.comp_intitule', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data' => $competences
        ]);
    }

    /**
     * Ajouter/Modifier une compétence pour un employé
     */
    public function addCompetence($empCode = null)
    {
        if (!$empCode) {
            return $this->failNotFound('ID employé manquant');
        }

        $json = $this->request->getJSON(true);
        $compCode = $json['comp_code'] ?? null;
        $niveauAcquis = $json['niveau_acquis'] ?? null;

        if (!$compCode || !$niveauAcquis) {
            return $this->failValidationErrors([
                'comp_code' => 'Code compétence requis',
                'niveau_acquis' => 'Niveau acquis requis'
            ]);
        }

        $compEmployeModel = model('App\Models\employe\CompEmployeModel');

        // Supprimer si existe déjà (upsert)
        $compEmployeModel
            ->where('emp_code', $empCode)
            ->where('comp_code', $compCode)
            ->delete();

        $data = [
            'emp_code' => $empCode,
            'comp_code' => $compCode,
            'niveau_acquis' => $niveauAcquis
        ];

        if (!$compEmployeModel->insert($data)) {
            return $this->failValidationErrors($compEmployeModel->errors());
        }

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Compétence assignée avec succès'
        ]);
    }

    /**
     * Supprimer une compétence d'un employé
     */
    public function removeCompetence($empCode = null, $compCode = null)
    {
        if (!$empCode || !$compCode) {
            return $this->failNotFound('ID employé ou compétence manquant');
        }

        $compEmployeModel = model('App\Models\employe\CompEmployeModel');

        $deleted = $compEmployeModel
            ->where('emp_code', $empCode)
            ->where('comp_code', $compCode)
            ->delete();

        if (!$deleted) {
            return $this->failNotFound('Compétence non trouvée pour cet employé');
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Compétence retirée avec succès'
        ]);
    }

    /**
     * Calculer l'ancienneté d'un employé en années, mois et jours
     * 
     * @param string|null $dateEntree Date d'entrée (format YYYY-MM-DD)
     * @param string|null $dateSortie Date de sortie (format YYYY-MM-DD) ou null si actif
     * @return array ['annees' => int, 'mois' => int, 'jours' => int, 'total_jours' => int, 'formatted' => string]
     */
    protected function calculerAnciennete(?string $dateEntree, ?string $dateSortie = null): array
    {
        if (!$dateEntree) {
            return [
                'annees' => 0,
                'mois' => 0,
                'jours' => 0,
                'total_jours' => 0,
                'formatted' => 'Non disponible'
            ];
        }

        try {
            $dateDebut = new \DateTime($dateEntree);
            $dateFin = $dateSortie ? new \DateTime($dateSortie) : new \DateTime();

            // Calculer la différence
            $interval = $dateDebut->diff($dateFin);

            $totalJours = $dateDebut->diff($dateFin)->days;

            return [
                'annees' => (int) $interval->y,
                'mois' => (int) $interval->m,
                'jours' => (int) $interval->d,
                'total_jours' => $totalJours,
                'formatted' => $this->formaterAnciennete($interval->y, $interval->m, $interval->d)
            ];
        } catch (\Exception $e) {
            log_message('error', 'Erreur calcul ancienneté: ' . $e->getMessage());
            return [
                'annees' => 0,
                'mois' => 0,
                'jours' => 0,
                'total_jours' => 0,
                'formatted' => 'Erreur de calcul'
            ];
        }
    }

    /**
     * Formater l'ancienneté en texte lisible
     */
    protected function formaterAnciennete(int $annees, int $mois, int $jours): string
    {
        $parts = [];

        if ($annees > 0) {
            $parts[] = $annees . ' an' . ($annees > 1 ? 's' : '');
        }

        if ($mois > 0) {
            $parts[] = $mois . ' mois';
        }

        if ($jours > 0 && $annees == 0 && $mois == 0) {
            $parts[] = $jours . ' jour' . ($jours > 1 ? 's' : '');
        }

        if (empty($parts)) {
            return '0 jour';
        }

        return implode(', ', $parts);
    }

    /**
     * Calculer l'année de retraite d'un employé
     * 
     * @param string|null $dateNaissance Date de naissance (format YYYY-MM-DD)
     * @param bool|null $sexe true = Homme, false = Femme (ou null si inconnu)
     * @return array ['annee' => int|null, 'age_actuel' => int|null, 'age_retraite' => int, 'formatted' => string]
     */
    protected function calculerAnneeRetraite(?string $dateNaissance, ?bool $sexe = null): array
    {
        if (!$dateNaissance) {
            return [
                'annee' => null,
                'age_actuel' => null,
                'age_retraite' => 60,
                'formatted' => 'Non disponible'
            ];
        }

        try {
            $dateNaiss = new \DateTime($dateNaissance);
            $aujourdhui = new \DateTime();

            // Calculer l'âge actuel
            $ageActuel = $dateNaiss->diff($aujourdhui)->y;

            // Déterminer l'âge de retraite (60 ans pour femmes, 65 ans pour hommes par défaut)
            // À Madagascar, l'âge de retraite est généralement 60 ans pour tous
            // Vous pouvez ajuster selon vos règles spécifiques
            $ageRetraite = 60; // Par défaut

            // Optionnel : différencier selon le sexe
            // if ($sexe === false) { // Femme
            //     $ageRetraite = 60;
            // } elseif ($sexe === true) { // Homme
            //     $ageRetraite = 65;
            // }

            // Calculer l'année de retraite
            $anneeRetraite = (int) $dateNaiss->format('Y') + $ageRetraite;

            // Vérifier si déjà à la retraite
            $dejaRetraite = $ageActuel >= $ageRetraite;

            $formatted = $dejaRetraite
                ? "Retraité depuis " . ($ageActuel - $ageRetraite) . " an(s)"
                : "En " . $anneeRetraite . " (dans " . ($ageRetraite - $ageActuel) . " an(s))";

            return [
                'annee' => $anneeRetraite,
                'age_actuel' => $ageActuel,
                'age_retraite' => $ageRetraite,
                'deja_retraite' => $dejaRetraite,
                'formatted' => $formatted
            ];
        } catch (\Exception $e) {
            log_message('error', 'Erreur calcul année retraite: ' . $e->getMessage());
            return [
                'annee' => null,
                'age_actuel' => null,
                'age_retraite' => 60,
                'formatted' => 'Erreur de calcul'
            ];
        }
    }

    /**
     * Finir la carrière d'un employé (créer une entrée dans la table sortie)
     */
    public function finirCarriere($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $employe = $this->employeModel->find($id);
        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        // Vérifier que l'employé est en cessation (pos_code = 2)
        $db = \Config\Database::connect();
        $result = $db->table('pos_emp')
            ->where('emp_code', $id)
            ->orderBy('date_', 'DESC')
            ->get();

        $position = null;
        if ($result && $result !== false) {
            $position = $result->getRowArray();
        }

        if (!$position || $position['pos_code'] != 2) {
            return $this->failValidationErrors([
                'position' => 'Cet employé n\'est pas en cessation. Seuls les employés en cessation peuvent terminer leur carrière.'
            ]);
        }

        $data = $this->request->getJSON(true);

        if (empty($data['date_sortie'])) {
            return $this->failValidationErrors([
                'date_sortie' => 'La date de sortie est obligatoire'
            ]);
        }

        if (empty($data['s_type_code'])) {
            return $this->failValidationErrors([
                's_type_code' => 'Le type de sortie est obligatoire'
            ]);
        }

        // Vérifier que le type de sortie existe
        $sortieTypeModel = model('App\Models\referentiel\SortieTypeModel');
        $sortieType = $sortieTypeModel->find($data['s_type_code']);
        if (!$sortieType) {
            return $this->failValidationErrors([
                's_type_code' => 'Type de sortie invalide'
            ]);
        }

        $db->transStart();

        // Créer l'entrée dans la table sortie
        $sortieModel = model('App\Models\employe\SortieModel');
        $sortieData = [
            'emp_code' => $id,
            's_type_code' => $data['s_type_code'],
            'date_sortie' => $data['date_sortie'],
            'commentaire' => !empty($data['commentaire']) ? $data['commentaire'] : null
        ];

        // Vérifier si une sortie avec la même clé primaire existe déjà
        $existingSortie = null;
        try {
            $result = $db->table('sortie')
                ->where('emp_code', $id)
                ->where('s_type_code', $data['s_type_code'])
                ->where('date_sortie', $data['date_sortie'])
                ->get();

            if ($result && $result !== false) {
                $existingSortie = $result->getRowArray();
            }
        } catch (\Exception $e) {
            log_message('error', 'Erreur lors de la vérification de sortie existante: ' . $e->getMessage());
            $existingSortie = null;
        }

        if ($existingSortie) {
            // Mettre à jour le commentaire si l'entrée existe déjà
            try {
                $updateResult = $db->table('sortie')
                    ->where('emp_code', $id)
                    ->where('s_type_code', $data['s_type_code'])
                    ->where('date_sortie', $data['date_sortie'])
                    ->set('commentaire', $sortieData['commentaire'])
                    ->update();

                if ($updateResult === false) {
                    $dbError = $db->error();
                    $db->transRollback();
                    log_message('error', 'Erreur mise à jour sortie: ' . json_encode($dbError));
                    return $this->failServerError('Erreur lors de la mise à jour de la sortie: ' . ($dbError['message'] ?? 'Erreur inconnue'));
                }
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Exception mise à jour sortie: ' . $e->getMessage());
                return $this->failServerError('Erreur lors de la mise à jour de la sortie: ' . $e->getMessage());
            }
        } else {
            // Insérer une nouvelle entrée directement avec la table
            try {
                $insertResult = $db->table('sortie')->insert($sortieData);
                if ($insertResult === false) {
                    $dbError = $db->error();
                    $db->transRollback();
                    log_message('error', 'Erreur insertion sortie: ' . json_encode($dbError));
                    return $this->failServerError('Erreur lors de l\'insertion de la sortie: ' . ($dbError['message'] ?? 'Erreur inconnue'));
                }
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Exception insertion sortie: ' . $e->getMessage());
                return $this->failServerError('Erreur lors de l\'insertion de la sortie: ' . $e->getMessage());
            }
        }

        // Mettre à jour la date de sortie dans la table employe (pour compatibilité)
        $this->employeModel->update($id, [
            'date_sortie' => $data['date_sortie'],
            's_type_code' => $data['s_type_code']
        ]);

        // Mettre l'employé en position "Sortie" (pos_code = 3)
        // Mettre à jour pos_emp avec pos_code = 3
        try {
            // Supprimer l'ancienne position de l'employé
            $db->table('pos_emp')->where('emp_code', $id)->delete();

            // Insérer la nouvelle position "Sortie" (pos_code = 3)
            $db->table('pos_emp')->insert([
                'emp_code' => $id,
                'pos_code' => 3, // Position "Sortie"
                'date_' => $data['date_sortie']
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Erreur mise à jour position pos_emp: ' . $e->getMessage());
            // Ne pas faire échouer la transaction si la position ne peut pas être mise à jour
            // mais loguer l'erreur pour le débogage
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Erreur lors de l\'enregistrement de la sortie');
        }

        // Récupérer la sortie créée avec les détails
        try {
            $sortie = $sortieModel->getDerniereSortie($id);
            if (!$sortie) {
                // Si aucune sortie n'est trouvée, créer un objet avec les données insérées
                $sortie = [
                    'emp_code' => $id,
                    's_type_code' => $data['s_type_code'],
                    'date_sortie' => $data['date_sortie'],
                    'commentaire' => $sortieData['commentaire'],
                    's_type_motif' => $sortieType['s_type_motif'] ?? null
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'Erreur lors de la récupération de la sortie: ' . $e->getMessage());
            // En cas d'erreur, retourner les données de base
            $sortie = [
                'emp_code' => $id,
                's_type_code' => $data['s_type_code'],
                'date_sortie' => $data['date_sortie'],
                'commentaire' => $sortieData['commentaire'],
                's_type_motif' => $sortieType['s_type_motif'] ?? null
            ];
        }

        return $this->respond([
            'status' => 'success',
            'message' => 'Carrière terminée avec succès',
            'data' => $sortie
        ], 200);
    }

    /**
     * Réintégrer un employé
     */
    public function reintegration($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $employe = $this->employeModel->find($id);
        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        // Vérifier que l'employé est en sortie
        // Nous vérifions si la dernière position est 3 (Sortie)
        $db = \Config\Database::connect();
        $result = $db->table('pos_emp')
            ->where('emp_code', $id)
            ->orderBy('date_', 'DESC')
            ->get();

        $position = null;
        if ($result && $result !== false) {
            $position = $result->getRowArray();
        }

        if (!$position || $position['pos_code'] != 3) {
            return $this->failValidationErrors([
                'position' => 'Cet employé n\'est pas en position "Sortie". Impossible de le réintégrer.'
            ]);
        }

        $data = $this->request->getJSON(true);

        // Validation des données
        $validationRules = [
            'pst_code' => 'required|integer', // Le nouveau poste
            'date_reintegration' => 'required|valid_date', // Date de réintégration
            'tcontrat_code' => 'required|integer', // Type de contrat
            'm_aff_code' => 'required|integer' // Motif d'affectation
        ];

        if (!$this->validate($validationRules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $pstCode = $data['pst_code'];
        $dateReintegration = $data['date_reintegration'];
        $tcontratCode = $data['tcontrat_code'];
        $mAffCode = $data['m_aff_code'];
        $commentaire = $data['commentaire'] ?? 'Réintégration';

        $db->transStart();

        try {
            // 1. Mettre à jour l'employé pour annuler la sortie (date_sortie = NULL, s_type_code = NULL)
            // Note: Nous gardons l'historique des sorties dans la table 'sortie', donc c'est sûr de nettoyer ici.
            $updateEmp = $this->employeModel->update($id, [
                'date_sortie' => null,
                's_type_code' => null
            ]);

            if (!$updateEmp) {
                throw new \Exception('Erreur lors de la mise à jour de l\'employé');
            }

            // 2. Ajouter une nouvelle position "En service" (pos_code = 1)
            $db->table('pos_emp')->insert([
                'emp_code' => $id,
                'pos_code' => 1, // En service
                'date_' => $dateReintegration
            ]);

            // 3. Créer la nouvelle affectation
            $affectationModel = model('App\Models\affectation\AffectationModel');

            // Clôturer toute affectation active précédente (sécurité, même si théoriquement il ne devrait pas y en avoir)
            $affectationModel->where('emp_code', $id)
                ->where('affec_etat', 'active')
                ->set(['affec_etat' => 'cloturee', 'affec_date_fin' => $dateReintegration])
                ->update();

            // Gestion de la date de fin selon le type de contrat (optionnel)
            $dateFin = null;
            // Si le contrat a une durée déterminée, on pourrait le gérer ici, mais restons simple pour l'instant.

            $affectationData = [
                'emp_code' => $id,
                'pst_code' => $pstCode,
                'm_aff_code' => $mAffCode,
                'affec_date_debut' => $dateReintegration,
                'affec_date_fin' => $dateFin, // NULL par défaut
                'tcontrat_code' => $tcontratCode,
                'affec_commentaire' => $commentaire,
                'affec_etat' => 'active'
            ];

            if (!$affectationModel->insert($affectationData)) {
                $errors = $affectationModel->errors();
                throw new \Exception('Erreur validation affectation: ' . json_encode($errors));
            }

            // 4. Mettre à jour l'occupation du poste
            $occupationPosteModel = model('App\Models\poste\OccupationPosteModel');
            $occupationPosteModel->incrementerOccupe($pstCode);

            $db->transComplete();

            if ($db->transStatus() === false) {
                $error = $db->error();
                return $this->failServerError('Transaction échouée: ' . ($error['message'] ?? 'Erreur inconnue'));
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Employé réintégré avec succès'
            ], 200);

        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Erreur réintégration: ' . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * Récupérer toutes les sorties d'un employé
     */
    public function getSorties($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID de l\'employé manquant');
        }

        $employe = $this->employeModel->find($id);
        if (!$employe) {
            return $this->failNotFound('Employé non trouvé');
        }

        $sortieModel = model('App\Models\employe\SortieModel');
        $sorties = $sortieModel->getSortiesByEmploye($id);

        return $this->respond([
            'status' => 'success',
            'count' => count($sorties),
            'data' => $sorties
        ], 200);
    }
    /**
     * Récupérer les employés pouvant être encadreurs (en service, pos_code = 1)
     */
    public function getEncadreurs()
    {
        $db = \Config\Database::connect();

        $sql = "
            SELECT e.emp_code, e.emp_nom, e.emp_prenom, e.emp_matricule, e.emp_im_armp
            FROM employe e
            JOIN pos_emp pe ON pe.emp_code = e.emp_code
            WHERE pe.pos_code = 1
            AND pe.date_ = (
                SELECT MAX(date_) 
                FROM pos_emp pe2 
                WHERE pe2.emp_code = e.emp_code
            )
            ORDER BY e.emp_nom, e.emp_prenom
        ";

        $query = $db->query($sql);
        $result = $query->getResultArray();

        log_message('info', 'Demande encadreurs (pos_code=1). Trouvés: ' . count($result));

        return $this->respond([
            'status' => 'success',
            'count' => count($result),
            'data' => $result
        ]);
    }

    /**
     * Exporter les employés en format XLSX avec tous les détails et historique
     */
    public function exportXlsx()
    {
        $filters = $this->request->getVar();
        $db = \Config\Database::connect();

        // 1. Récupérer les employés filtrés
        $builder = $this->employeModel->builder();
        $builder->select('employe.*, type_entree.e_type_motif, pe.pos_code as pos_code, p.pos_type as pos_type, statut_armp.stt_armp_statut')
            ->join('type_entree', 'employe.e_type_code = type_entree.e_type_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, pos_code, date_ FROM pos_emp ORDER BY emp_code, date_ DESC NULLS LAST) pe', 'pe.emp_code = employe.emp_code', 'left', false)
            ->join('position_ p', 'p.pos_code = pe.pos_code', 'left')
            ->join('(SELECT DISTINCT ON (emp_code) emp_code, stt_armp_code FROM statut_emp ORDER BY emp_code, date_ DESC NULLS LAST) se', 'se.emp_code = employe.emp_code', 'left', false)
            ->join('statut_armp', 'statut_armp.stt_armp_code = se.stt_armp_code', 'left');

        // Filtres (Identique à index)
        if (!empty($filters['statut'])) {
            if ($filters['statut'] === 'actif')
                $builder->where('employe.date_sortie IS NULL');
            elseif ($filters['statut'] === 'inactif')
                $builder->where('employe.date_sortie IS NOT NULL');
        }
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('employe.emp_nom', $q)
                ->orLike('employe.emp_prenom', $q)
                ->orLike('employe.emp_matricule', $q)
                ->groupEnd();
        }
        // ... autres filtres si nécessaire ...

        $employes = $builder->get()->getResultArray();

        // 2. Récupérer l'historique des affectations pour chaque employé
        $affectationModel = model('App\Models\affectation\AffectationModel');
        $contactModel = model('App\Models\employe\ContactModel');

        foreach ($employes as &$employe) {
            $affectations = $affectationModel
                ->select('affectation.*, poste.pst_fonction, poste.pst_mission, service.srvc_nom, motif_affectation.m_aff_motif, type_contrat.tcontrat_nom,
                         ts.tsup_tache, rh.rhq_niveau, rh.rhq_rang')
                ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
                ->join('service', 'service.srvc_code = poste.srvc_code', 'left')
                ->join('motif_affectation', 'motif_affectation.m_aff_code = affectation.m_aff_code', 'left')
                ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
                ->join('tache_suppl ts', 'ts.tsup_code = poste.tsup_code', 'left')
                ->join('rang_hierarchique rh', 'rh.rhq_code = poste.rhq_code', 'left')
                ->where('affectation.emp_code', $employe['emp_code'])
                ->orderBy('affec_date_debut', 'DESC')
                ->findAll();

            $employe['affectation_history'] = $affectations;

            // Identifier l'affectation active (celle sans date_fin ou avec la date la plus récente)
            $activeAff = null;
            foreach ($affectations as $aff) {
                if ($aff['affec_etat'] === 'active') {
                    $activeAff = $aff;
                    break;
                }
            }
            $employe['current_affectation'] = $activeAff;

            // Contact
            $contact = $contactModel->where('emp_code', $employe['emp_code'])->first();
            $employe['emp_contact'] = $contact['numero'] ?? 'Non renseigné';
        }

        // 3. Création du fichier Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Base de Données Personnel');

        // Style pour l'en-tête
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        // Définition des colonnes
        $columns = [
            'A' => 'Matricule',
            'B' => 'Nom',
            'C' => 'Prénoms',
            'D' => 'Sexe',
            'E' => 'Date Naissance',
            'F' => 'IM ARMP',
            'G' => 'IM ETAT',
            'H' => 'Email',
            'I' => 'Contact',
            'J' => 'Date Entrée',
            'K' => 'Statut Actuel',
            'L' => 'Statut ARMP',
            'M' => 'Poste Actuel',
            'N' => 'Service',
            'O' => 'Mission',
            'P' => 'Rang Hiérarchique',
            'Q' => 'Niveau Hiérarchique',
            'R' => 'Tâche Suppl.',
            'S' => 'Type Contrat',
            'T' => 'Historique des Affectations (Détails)'
        ];

        foreach ($columns as $col => $title) {
            $sheet->setCellValue($col . '1', $title);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:T1')->applyFromArray($headerStyle);

        // Remplissage des données
        $row = 2;
        foreach ($employes as $emp) {
            $sheet->setCellValue('A' . $row, $emp['emp_matricule']);
            $sheet->setCellValue('B' . $row, $emp['emp_nom']);
            $sheet->setCellValue('C' . $row, $emp['emp_prenom']);
            $sheet->setCellValue('D' . $row, $emp['emp_sexe'] ? 'M' : 'F');
            $sheet->setCellValue('E' . $row, $emp['emp_datenaissance']);
            $sheet->setCellValue('F' . $row, $emp['emp_im_armp']);
            $sheet->setCellValue('G' . $row, $emp['emp_im_etat']);
            $sheet->setCellValue('H' . $row, $emp['emp_mail']);
            $sheet->setCellValue('I' . $row, $emp['emp_contact']);
            $sheet->setCellValue('J' . $row, $emp['date_entree']);
            $sheet->setCellValue('K' . $row, $emp['pos_type'] ?? 'Inconnu');
            $sheet->setCellValue('L' . $row, $emp['stt_armp_statut'] ?? 'Non défini');

            // Poste actuel
            $curr = $emp['current_affectation'] ?? null;
            $sheet->setCellValue('M' . $row, $curr ? $curr['pst_fonction'] : 'Non affecté');
            $sheet->setCellValue('N' . $row, $curr ? $curr['srvc_nom'] : '-');
            $sheet->setCellValue('O' . $row, $curr ? $curr['pst_mission'] : '-');
            $sheet->setCellValue('P' . $row, $curr ? $curr['rhq_rang'] : '-');
            $sheet->setCellValue('Q' . $row, $curr ? $curr['rhq_niveau'] : '-');
            $sheet->setCellValue('R' . $row, $curr ? $curr['tsup_tache'] : '-');
            $sheet->setCellValue('S' . $row, $curr ? $curr['tcontrat_nom'] : '-');

            // Détails des affectations (Historique concaténé)
            $histText = "";
            foreach ($emp['affectation_history'] as $aff) {
                $status = ($aff['affec_etat'] === 'active') ? "[ACTUEL]" : "[PASSE]";
                $histText .= "$status {$aff['affec_date_debut']} -> " . ($aff['affec_date_fin'] ?? '...') . " : {$aff['pst_fonction']} ({$aff['srvc_nom']}) - {$aff['m_aff_motif']}\n";
            }
            $sheet->setCellValue('T' . $row, trim($histText));
            $sheet->getStyle('T' . $row)->getAlignment()->setWrapText(true);

            $row++;
        }

        // Bordures pour tout le tableau
        $sheet->getStyle('A1:V' . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // 4. Envoi du fichier
        $writer = new Xlsx($spreadsheet);
        $filename = 'BDD_Personnel_' . date('Y-m-d_H-i') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
