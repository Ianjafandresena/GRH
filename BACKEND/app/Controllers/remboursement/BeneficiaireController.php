<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\ConjointeModel;
use App\Models\remboursement\EnfantModel;

class BeneficiaireController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste globale des familles (pour la nouvelle page Famille)
     */
    public function getFamilyList()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('employe e');
        $builder->select('e.emp_code, e.emp_nom, e.emp_prenom, e.emp_sexe');
        $builder->select('(SELECT COUNT(*) FROM enfant WHERE emp_code = e.emp_code) as nb_enfants');
        
        // Sous-requête pour le conjoint (on peut en avoir plusieurs historiquement, on prend le plus récent)
        $builder->select('(SELECT c.conj_nom || \' \' || c.conj_prenom 
                           FROM conjointe c 
                           JOIN emp_conj ec ON ec.conj_code = c.conj_code 
                           WHERE ec.emp_code = e.emp_code 
                           ORDER BY c.conj_date_statut DESC LIMIT 1) as conj_nom_prenom');
        
        $builder->join('affectation a', 'a.emp_code = e.emp_code AND a.affec_etat = \'active\'', 'left');
        $builder->join('poste p', 'p.pst_code = a.pst_code', 'left');
        $builder->join('direction d', 'd.dir_code = p.dir_code', 'left');
        $builder->select('d.dir_nom');

        $builder->where("EXISTS (SELECT 1 FROM enfant WHERE emp_code = e.emp_code) OR EXISTS (SELECT 1 FROM emp_conj WHERE emp_code = e.emp_code)");
        $builder->orderBy('e.emp_nom', 'ASC');
        
        $results = $builder->get()->getResultArray();
        return $this->respond($results);
    }

    /**
     * Liste les conjoints d'un employé
     */
    public function getConjointes($emp_code = null)
    {
        $db = \Config\Database::connect();
        $conjoints = $db->table('emp_conj')
            ->select('conjointe.*, conj_status.cjs_libelle')
            ->join('conjointe', 'conjointe.conj_code = emp_conj.conj_code')
            ->join('conj_status', 'conj_status.cjs_id = conjointe.cjs_id', 'left')
            ->where('emp_conj.emp_code', $emp_code)
            ->orderBy('conj_date_statut', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond($conjoints);
    }

    /**
     * Liste les statuts possibles pour un conjoint
     */
    public function getStatuses()
    {
        $db = \Config\Database::connect();
        return $this->respond($db->table('conj_status')->get()->getResultArray());
    }

    /**
     * Liste les enfants d'un employé
     */
    public function getEnfants($emp_code = null)
    {
        $db = \Config\Database::connect();
        $enfants = $db->table('enfant')
            ->where('emp_code', $emp_code)
            ->orderBy('date_naissance', 'ASC')
            ->get()
            ->getResultArray();

        return $this->respond($enfants);
    }

    /**
     * Ajouter un conjoint à un employé avec détection auto du sexe et vérification statut
     */
    public function addConjoint($emp_code = null)
    {
        $data = $this->request->getJSON(true);

        if (empty($data['conj_nom'])) {
            return $this->fail('Nom du conjoint obligatoire');
        }

        $db = \Config\Database::connect();

        // 1. Vérifier si l'employé a déjà un(e) conjoint(e) "MARIÉ" (ID 1)
        $existingMarried = $db->table('emp_conj')
            ->join('conjointe', 'conjointe.conj_code = emp_conj.conj_code')
            ->where('emp_conj.emp_code', $emp_code)
            ->where('conjointe.cjs_id', 1) 
            ->countAllResults();

        if ($existingMarried > 0) {
            return $this->fail('L\'employé a déjà un(e) conjoint(e) marié(e). Veuillez d\'abord changer son statut.');
        }

        // 2. Détection automatique du sexe
        $emp = $db->table('employe')->where('emp_code', $emp_code)->get()->getRowArray();
        if (!$emp) return $this->failNotFound('Employé non trouvé');

        $conj_sexe = !($emp['emp_sexe'] === true || $emp['emp_sexe'] === 't' || $emp['emp_sexe'] === 1);

        $db->transStart();
        try {
            $model = new ConjointeModel();
            $conj_id = $model->insert([
                'conj_nom' => $data['conj_nom'],
                'conj_prenom' => $data['conj_prenom'] ?? '',
                'conj_sexe' => $conj_sexe,
                'cjs_id' => 1, // ID pour MARIÉ
                'conj_date_statut' => date('Y-m-d')
            ]);

            $db->table('emp_conj')->insert([
                'emp_code' => $emp_code,
                'conj_code' => $conj_id
            ]);

            $db->transComplete();
            return $this->respondCreated(['id' => $conj_id, 'sexe' => $conj_sexe]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Mettre à jour le statut d'un conjoint
     */
    public function updateStatus($conj_id)
    {
        $data = $this->request->getJSON(true);
        $status_id = $data['cjs_id'] ?? 2; // Par défaut DIVORCÉ (ID 2)

        $model = new ConjointeModel();
        if ($model->update($conj_id, [
            'cjs_id' => $status_id,
            'conj_date_statut' => date('Y-m-d')
        ])) {
            return $this->respond(['message' => 'Statut mis à jour']);
        }
        return $this->fail('Erreur mise à jour');
    }

    /**
     * Ajouter un enfant à un employé
     */
    public function addEnfant($emp_code = null)
    {
        $data = $this->request->getJSON(true);
        if (empty($data['enf_nom'])) return $this->fail('Nom requis');

        $model = new \App\Models\remboursement\EnfantModel();
        
        // Fix: Traiter les chaînes vides comme null pour éviter les erreurs SQL sur type DATE
        $dateNaissance = !empty($data['date_naissance']) ? $data['date_naissance'] : null;

        $id = $model->insert([
            'enf_nom' => $data['enf_nom'],
            'enf_num' => $data['enf_num'] ?? '',
            'date_naissance' => $dateNaissance,
            'emp_code' => $emp_code
        ]);

        return $this->respondCreated(['id' => $id]);
    }

    /**
     * Supprimer un enfant
     */
    public function deleteEnfant($id)
    {
        $model = new \App\Models\remboursement\EnfantModel();
        if ($model->delete($id)) return $this->respondDeleted(['id' => $id]);
        return $this->fail('Erreur suppression');
    }
}
