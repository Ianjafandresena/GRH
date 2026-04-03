<?php
namespace App\Controllers\affectation;

use CodeIgniter\RESTful\ResourceController;

class AffectationController extends ResourceController
{
    protected $modelName = 'App\Models\affectation\AffectationModel';
    protected $format = 'json';
    protected $affectationModel;
    protected $motifModel;
    protected $posEmpModel;
    protected $occupationPosteModel;


    public function stats()
    {
        $db = \Config\Database::connect();
        $total = $this->affectationModel->countAllResults();
        $actives = $this->affectationModel->where('affec_etat', 'active')->countAllResults();
        $cloturees = $this->affectationModel->where('affec_etat', 'cloture')->countAllResults();

        $startOfMonth = date('Y-m-01');
        $endOfMonth = date('Y-m-t');
        $mouvements = $this->affectationModel
            ->where("affec_date_debut BETWEEN '$startOfMonth' AND '$endOfMonth'")
            ->orWhere("affec_date_fin BETWEEN '$startOfMonth' AND '$endOfMonth'")
            ->countAllResults();

        $parContrat = $db->table('affectation')
            ->select('type_contrat.tcontrat_nom as label, count(*) as value')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code')
            ->groupBy('type_contrat.tcontrat_nom')
            ->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'actives' => $actives,
                'cloturees' => $cloturees,
                'par_contrat' => $parContrat,
                'mouvements' => $mouvements
            ]
        ]);
    }

    public function __construct()
    {
        $this->affectationModel = new \App\Models\affectation\AffectationModel();
        $this->motifModel = new \App\Models\affectation\MotifAffectationModel();
        $this->posEmpModel = new \App\Models\employe\PosEmpModel();
        $this->occupationPosteModel = new \App\Models\poste\OccupationPosteModel();
    }

    public function index()
    {
        $filters = $this->request->getGet();

        $builder = $this->affectationModel
            ->select('affectation.*, employe.emp_nom, employe.emp_prenom, poste.pst_fonction, motif_affectation.m_aff_motif, type_contrat.tcontrat_nom')
            ->join('employe', 'employe.emp_code = affectation.emp_code')
            ->join('poste', 'poste.pst_code = affectation.pst_code', 'left')
            ->join('type_contrat', 'type_contrat.tcontrat_code = affectation.tcontrat_code', 'left')
            ->join('motif_affectation', 'motif_affectation.m_aff_code = affectation.m_aff_code', 'left');

        if (!empty($filters['emp'])) {
            $q = trim((string) $filters['emp']);
            $builder->groupStart()
                ->like('employe.emp_nom', $q)
                ->orLike('employe.emp_prenom', $q)
                ->orLike("CONCAT(employe.emp_nom, ' ', employe.emp_prenom)", $q)
                ->orLike("CONCAT(employe.emp_prenom, ' ', employe.emp_nom)", $q)
                ->groupEnd();
        }

        if (!empty($filters['poste'])) {
            $builder->like('poste.pst_fonction', trim((string) $filters['poste']));
        }

        if (!empty($filters['motif'])) {
            $builder->like('motif_affectation.m_aff_motif', trim((string) $filters['motif']));
        }

        if (!empty($filters['emp_code'])) {
            $builder->where('affectation.emp_code', $filters['emp_code']);
        }

        if (!empty($filters['tcontrat_code'])) {
            $builder->where('affectation.tcontrat_code', $filters['tcontrat_code']);
        }

        if (!empty($filters['date_debut'])) {
            $builder->where('affectation.affec_date_debut >=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $builder->where('affectation.affec_date_fin <=', $filters['date_fin']);
        }

        $data = $builder
            ->orderBy('affectation.affec_date_debut', 'DESC')
            ->orderBy('affectation.affec_code', 'DESC')
            ->findAll();

        return $this->respond($data);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        // Convertir chaînes vides en NULL pour les dates
        if (isset($data['affec_date_fin']) && $data['affec_date_fin'] === '') {
            $data['affec_date_fin'] = null;
        }

        if (!empty($data['emp_code'])) {
            $existingOpen = $this->affectationModel
                ->where('emp_code', $data['emp_code'])
                ->where('affec_etat', 'active')
                ->first();

            if ($existingOpen) {
                return $this->failValidationErrors([
                    'emp_code' => "Cet employé a déjà une affectation en cours non clôturée. Veuillez d'abord clôturer l'affectation existante."
                ]);
            }

            $position = $this->posEmpModel
                ->where('emp_code', $data['emp_code'])
                ->orderBy('date_', 'DESC')
                ->first();

            if (!$position || (int) $position['pos_code'] !== 2) {
                return $this->failValidationErrors([
                    'emp_code' => 'Seuls les employés en cessation (pos_code=2) peuvent être affectés.'
                ]);
            }
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Par défaut, l'affectation est active à la création
        if (!isset($data['affec_etat'])) {
            $data['affec_etat'] = 'active';
        }

        if (!$this->affectationModel->insert($data)) {
            $db->transRollback();
            return $this->failValidationErrors($this->affectationModel->errors());
        }

        // Mettre l'employé en position "En service" (pos_code=1)
        if (!empty($data['emp_code'])) {
            $this->posEmpModel->where('emp_code', $data['emp_code'])->delete();
            $this->posEmpModel->insert([
                'emp_code' => $data['emp_code'],
                'pos_code' => 1,
                'date_' => date('Y-m-d'),
            ]);
        }

        // Mettre à jour occupation_poste : incrémenter nb_occupe
        if (!empty($data['pst_code'])) {
            $this->occupationPosteModel->incrementerOccupe($data['pst_code']);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Erreur lors de la création de l\'affectation');
        }

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Affectation créée avec succès'
        ]);
    }

    public function cloturer($id = null)
    {
        $affectation = $this->affectationModel->find($id);
        if (!$affectation) {
            return $this->failNotFound('Affectation introuvable');
        }

        $data = $this->request->getJSON(true);
        $dateFin = $data['affec_date_fin'] ?? date('Y-m-d');
        $statutPoste = $data['statut_poste'] ?? 'vacant'; // 'vacant' ou 'cessation'

        // Validation du statut
        if (!in_array($statutPoste, ['vacant', 'cessation'])) {
            return $this->failValidationErrors([
                'statut_poste' => 'Le statut doit être "vacant" ou "cessation"'
            ]);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $updateData = [
            'affec_date_fin' => $dateFin,
            'affec_etat' => 'cloture'
        ];

        if (!$this->affectationModel->update($id, $updateData)) {
            $db->transRollback();
            return $this->failValidationErrors($this->affectationModel->errors());
        }

        // Mettre l'employé en position "En cessation" (pos_code=2) lors de la clôture
        $empCode = $affectation['emp_code'] ?? null;
        if ($empCode) {
            // Lorsqu'on clôture une affectation, l'employé passe toujours en cessation (pos_code=2)
            $this->posEmpModel->where('emp_code', $empCode)->delete();
            $this->posEmpModel->insert([
                'emp_code' => $empCode,
                'pos_code' => 2, // 2 = En cessation
                'date_' => $dateFin,
            ]);
        }

        // Mettre à jour occupation_poste : décrémenter nb_occupe et incrémenter vacant ou cessation
        $pstCode = $affectation['pst_code'] ?? null;
        if ($pstCode) {
            $this->occupationPosteModel->decrementerOccupe($pstCode, $statutPoste);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Erreur lors de la clôture de l\'affectation');
        }

        $affectation = $this->affectationModel->find($id);

        return $this->respond([
            'status' => 'success',
            'message' => 'Affectation clôturée avec succès',
            'data' => $affectation
        ], 200);
    }

    public function motifs()
    {
        $motifs = $this->motifModel->findAll();
        return $this->respond($motifs, 200);
    }
}
