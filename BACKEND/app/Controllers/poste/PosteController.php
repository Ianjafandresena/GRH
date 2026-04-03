<?php
namespace App\Controllers\poste;

use CodeIgniter\RESTful\ResourceController;
use App\Models\poste\PosteModel;

class PosteController extends ResourceController
{
    protected $posteModel;
    protected $compPosteModel;

    public function __construct()
    {
        $this->posteModel = new PosteModel();
        $this->compPosteModel = new \App\Models\poste\CompPosteModel();
    }

    /**
     * Statistiques globales pour la liste des postes
     */
    public function stats()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('occupation_poste');

        $totalPostes = $db->table('poste')->countAllResults();

        $query = $builder->select('
            SUM(quota) as total_quota,
            SUM(nb_occupe) as total_occupe,
            SUM(nb_vacant) as total_vacant,
            SUM(nb_encessation) as total_cessation
        ')->get();

        $stats = $query->getRowArray();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total_postes' => (int) $totalPostes,
                'total_quota' => (int) ($stats['total_quota'] ?? 0),
                'total_occupe' => (int) ($stats['total_occupe'] ?? 0),
                'total_vacant' => (int) ($stats['total_vacant'] ?? 0),
                'total_cessation' => (int) ($stats['total_cessation'] ?? 0)
            ]
        ]);
    }

    /**
     * Liste des postes avec filtres multi-critères
     */
    public function index()
    {
        // Récupérer les filtres GET
        $filters = $this->request->getGet();

        // Obtenir les postes depuis le modèle
        $postes = $this->posteModel->getPostes($filters);

        // 🔹 FORCER l'encodage UTF-8 avec options JSON
        $this->response->setContentType('application/json; charset=utf-8');

        return $this->respond([
            'status' => 'success',
            'count' => count($postes),
            'data' => $postes
        ], 200);
    }

    /**
     * Liste des fonctions de postes (valeurs uniques)
     */
    public function fonctions()
    {
        $db = \Config\Database::connect();
        $query = $db->query("SELECT DISTINCT pst_fonction FROM poste WHERE pst_fonction IS NOT NULL ORDER BY pst_fonction ASC");
        $fonctions = array_column($query->getResultArray(), 'pst_fonction');

        return $this->respond([
            'status' => 'success',
            'count' => count($fonctions),
            'data' => $fonctions
        ], 200);
    }

    /**
     * Retourne les postes d'un service spécifique
     */
    public function byService($srvcCode)
    {
        // On retourne les postes liés au service
        $postes = $this->posteModel->where('srvc_code', $srvcCode)->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($postes),
            'data' => $postes
        ]);
    }

    /**
     * Détail d'un poste avec ses compétences requises
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failNotFound('ID du poste manquant');
        }

        $db = \Config\Database::connect();

        // Récupérer les informations du poste avec jointures
        $builder = $db->table('poste');
        $builder->select('poste.*, 
                         service.srvc_nom, 
                         rang_hierarchique.rhq_niveau, 
                         rang_hierarchique.rhq_rang,
                         tache_suppl.tsup_tache,
                         COALESCE(d_direct.dir_nom, d_service.dir_nom) as dir_nom,
                         COALESCE(occupation_poste.nb_occupe, 0) as nb_occupe,
                         COALESCE(occupation_poste.nb_vacant, 0) as nb_vacant')
            ->join('rang_hierarchique', 'poste.rhq_code = rang_hierarchique.rhq_code', 'left')
            ->join('service', 'poste.srvc_code = service.srvc_code', 'left')
            ->join('tache_suppl', 'poste.tsup_code = tache_suppl.tsup_code', 'left')
            ->join('direction as d_direct', 'poste.dir_code = d_direct.dir_code', 'left')
            ->join('direction as d_service', 'service.dir_code = d_service.dir_code', 'left')
            ->join('occupation_poste', 'poste.pst_code = occupation_poste.pst_code', 'left')
            ->where('poste.pst_code', $id);

        $result = $builder->get();
        $poste = null;
        if ($result && $result !== false) {
            $poste = $result->getRowArray();
        }

        if (!$poste) {
            return $this->failNotFound('Poste non trouvé');
        }

        // Récupérer les compétences requises
        $compBuilder = $db->table('comp_poste');
        $compBuilder->select('comp_poste.*, competence.comp_intitule, competence.comp_domaine, competence.comp_description')
            ->join('competence', 'comp_poste.comp_code = competence.comp_code')
            ->where('comp_poste.pst_code', $id)
            ->orderBy('competence.comp_intitule', 'ASC');

        $competences = $compBuilder->get()->getResultArray();
        $poste['competences'] = $competences;

        return $this->respond([
            'status' => 'success',
            'data' => $poste
        ], 200);
    }

    public function addCompetence($pstCode = null)
    {
        if (!$pstCode) {
            return $this->failNotFound('ID du poste manquant');
        }

        $payload = $this->request->getJSON(true);
        $compCode = $payload['comp_code'] ?? null;
        $niveauRequis = $payload['niveau_requis'] ?? null;

        if (!$compCode || !$niveauRequis) {
            return $this->failValidationErrors([
                'comp_code' => 'comp_code est requis',
                'niveau_requis' => 'niveau_requis est requis'
            ]);
        }

        $niveauRequis = (int) $niveauRequis;
        if ($niveauRequis < 1 || $niveauRequis > 5) {
            return $this->failValidationErrors([
                'niveau_requis' => 'niveau_requis doit être entre 1 et 5'
            ]);
        }

        // Upsert simple: supprimer si existe puis insérer
        $this->compPosteModel
            ->where('pst_code', $pstCode)
            ->where('comp_code', $compCode)
            ->delete();

        if (
            !$this->compPosteModel->insert([
                'pst_code' => $pstCode,
                'comp_code' => $compCode,
                'niveau_requis' => $niveauRequis,
            ])
        ) {
            return $this->failValidationErrors($this->compPosteModel->errors());
        }

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Compétence ajoutée au poste'
        ]);
    }

    public function removeCompetence($pstCode = null, $compCode = null)
    {
        if (!$pstCode || !$compCode) {
            return $this->failNotFound('ID du poste ou compétence manquant');
        }

        $this->compPosteModel
            ->where('pst_code', $pstCode)
            ->where('comp_code', $compCode)
            ->delete();

        return $this->respond([
            'status' => 'success',
            'message' => 'Compétence supprimée du poste'
        ], 200);
    }

    /**
     * Mettre à jour le quota d'un poste
     */
    public function updateQuota($pstCode = null)
    {
        if (!$pstCode) {
            return $this->failNotFound('ID du poste manquant');
        }

        try {
            $data = $this->request->getJSON(true);
            $nouveauQuota = $data['nouveau_quota'] ?? null;

            // Validation du quota
            if ($nouveauQuota === null || !is_numeric($nouveauQuota)) {
                return $this->failValidationErrors(['quota' => 'Le quota doit être un nombre']);
            }

            $nouveauQuota = (int) $nouveauQuota;

            if ($nouveauQuota < 1) {
                return $this->failValidationErrors(['quota' => 'Le quota doit être au moins 1']);
            }

            // Récupérer l'occupation actuelle
            $occupationPosteModel = model('App\\Models\\poste\\OccupationPosteModel');
            $occupation = $occupationPosteModel->where('pst_code', $pstCode)->first();

            if (!$occupation) {
                return $this->failNotFound('Occupation du poste non trouvée');
            }

            // Vérifier la contrainte : nouveau_quota >= nb_occupe + nb_encessation
            $nbOccupe = (int) $occupation['nb_occupe'];
            $nbEnCessation = (int) $occupation['nb_encessation'];
            $minQuota = $nbOccupe + $nbEnCessation;

            if ($nouveauQuota < $minQuota) {
                return $this->failValidationErrors([
                    'quota' => "Impossible de baisser le quota à {$nouveauQuota}. Il y a actuellement {$nbOccupe} poste(s) occupé(s) et {$nbEnCessation} en cessation. Le quota minimum est {$minQuota}."
                ]);
            }

            // Calculer le nouveau nb_vacant
            $nbVacant = $nouveauQuota - $nbOccupe - $nbEnCessation;

            // Mettre à jour
            $updated = $occupationPosteModel->update($occupation['occpst_code'], [
                'quota' => $nouveauQuota,
                'nb_vacant' => $nbVacant
            ]);

            if (!$updated) {
                return $this->failServerError('Erreur lors de la mise à jour du quota');
            }

            // Récupérer les données mises à jour
            $updatedOccupation = $occupationPosteModel->find($occupation['occpst_code']);

            return $this->respond([
                'status' => 'success',
                'message' => 'Quota mis à jour avec succès',
                'data' => $updatedOccupation
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Erreur updateQuota: ' . $e->getMessage());
            return $this->failServerError($e->getMessage());
        }
    }
}

