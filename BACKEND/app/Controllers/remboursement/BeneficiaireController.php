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
     * Liste les conjoints d'un employé
     */
    public function getConjointes($emp_code = null)
    {
        $db = \Config\Database::connect();
        $conjoints = $db->table('emp_conj')
            ->select('conjointe.*')
            ->join('conjointe', 'conjointe.conj_code = emp_conj.conj_code')
            ->where('emp_conj.emp_code', $emp_code)
            ->get()
            ->getResultArray();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($conjoints);
    }

    /**
     * Liste les enfants d'un employé
     */
    public function getEnfants($emp_code = null)
    {
        $db = \Config\Database::connect();
        $enfants = $db->table('emp_enfant')
            ->select('enfant.*')
            ->join('enfant', 'enfant.enf_code = emp_enfant.enf_code')
            ->where('emp_enfant.emp_code', $emp_code)
            ->get()
            ->getResultArray();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($enfants);
    }

    /**
     * Ajouter un conjoint à un employé
     */
    public function addConjoint($emp_code = null)
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['conj_nom'])) {
            return $this->fail('Nom du conjoint obligatoire');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Créer le conjoint
            $model = new ConjointeModel();
            $conj_id = $model->insert([
                'conj_nom' => $data['conj_nom'],
                'conj_sexe' => $data['conj_sexe'] ?? null
            ]);

            if ($conj_id === false) {
                throw new \Exception('Impossible de créer le conjoint');
            }

            // Lier à l'employé
            $db->table('emp_conj')->insert([
                'emp_code' => $emp_code,
                'conj_code' => $conj_id
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Erreur lors de la transaction');
            }

            $created = $model->find($conj_id);
            return $this->respondCreated($created);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 500);
        }
    }

    /**
     * Ajouter un enfant à un employé
     */
    public function addEnfant($emp_code = null)
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['enf_nom'])) {
            return $this->fail('Nom de l\'enfant obligatoire');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Créer l'enfant
            $model = new EnfantModel();
            $enf_id = $model->insert([
                'enf_nom' => $data['enf_nom'],
                'enf_num' => $data['enf_num'] ?? null,
                'date_naissance' => $data['date_naissance'] ?? null
            ]);

            if ($enf_id === false) {
                throw new \Exception('Impossible de créer l\'enfant');
            }

            // Lier à l'employé
            $db->table('emp_enfant')->insert([
                'emp_code' => $emp_code,
                'enf_code' => $enf_id
            ]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Erreur lors de la transaction');
            }

            $created = $model->find($enf_id);
            return $this->respondCreated($created);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 500);
        }
    }
}
