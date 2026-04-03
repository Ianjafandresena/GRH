<?php
namespace App\Controllers\utilitaire;

use CodeIgniter\RESTful\ResourceController;

class SeedController extends ResourceController
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function index()
    {
        // 1. Créer des services
        $services = [
            ['srvc_nom' => 'Direction Générale'],
            ['srvc_nom' => 'Ressources Humaines'],
            ['srvc_nom' => 'Informatique & SI'],
            ['srvc_nom' => 'Comptabilité & Finances'],
            ['srvc_nom' => 'Logistique'],
            ['srvc_nom' => 'Communication']
        ];

        $srvcModel = $this->db->table('service');
        $existing = $srvcModel->countAllResults();
        
        if ($existing == 0) {
            $srvcModel->insertBatch($services);
            echo "Services insérés.<br>";
        } else {
            echo "Services déjà présents ($existing).<br>";
        }

        // 2. Créer des postes (si services existent)
        // Récupérer un ID de service
        $srvc = $srvcModel->get()->getRow();
        if ($srvc) {
            $posteModel = $this->db->table('poste');
            if ($posteModel->countAllResults() == 0) {
                // Il faut des clés étrangères valides (nivhq, rhq, ctgr, idrec)
                // Créons d'abord les référentiels requis s'ils n'existent pas
                $this->ensureRef('niveau_hierarchique', 'nivhq_code', 'nivhq_niveau', 'A');
                $this->ensureRef('rang_hierarchique', 'rhq_code', 'rhq_rang', '1');
                $this->ensureRef('categ_poste_recrutement', 'ctgr_code', 'ctgr_statut', 'Cadre');
                $this->ensureRef('indice_recrutement', 'idrec_code', 'idrec_nom', '1000');
                
                // Insérer un poste
                $poste = [
                    'pst_fonction' => 'Développeur Fullstack',
                    'pst_mission' => 'Développement SI',
                    'srvc_code' => $srvc->srvc_code,
                    'nivhq_code' => 1, // Supposons ID 1 créé
                    'rhq_code' => 1,
                    'ctgr_code' => 1,
                    'idrec_code' => 1
                ];
                $posteModel->insert($poste);
                echo "Poste inséré.<br>";
            } else {
                echo "Postes déjà présents.<br>";
            }
        }

        return $this->respond(['status' => 'success', 'message' => 'Seeding terminé']);
    }

    private function ensureRef($table, $pk, $field, $value) {
        $builder = $this->db->table($table);
        if ($builder->countAllResults() == 0) {
            $builder->insert([$field => $value]);
        }
    }
}

