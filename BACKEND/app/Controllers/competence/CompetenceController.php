<?php
namespace App\Controllers\competence;

use CodeIgniter\RESTful\ResourceController;
use App\Models\competence\CompetenceModel;

class CompetenceController extends ResourceController
{
    protected $modelName = CompetenceModel::class;
    protected $format = 'json';

    public function stats()
    {
        $db = \Config\Database::connect();
        $total = $this->model->countAllResults();

        $parDomaine = $db->table('competence')
            ->select('comp_domaine as label, count(*) as value')
            ->groupBy('comp_domaine')
            ->orderBy('value', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'par_domaine' => $parDomaine
            ]
        ]);
    }

    public function index()
    {
        $filters = $this->request->getGet();
        $builder = $this->model;

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $builder->groupStart()
                ->like('comp_intitule', $q)
                ->orLike('comp_domaine', $q)
                ->orLike('comp_description', $q)
                ->groupEnd();
        }

        if (!empty($filters['intitule'])) {
            $builder->like('comp_intitule', trim((string) $filters['intitule']));
        }

        if (!empty($filters['domaine'])) {
            $builder->like('comp_domaine', trim((string) $filters['domaine']));
        }

        $competences = $builder
            ->orderBy('comp_code', 'DESC')
            ->findAll();

        return $this->respond($competences);
    }

    public function show($id = null)
    {
        $competence = $this->model->find($id);
        if (!$competence) {
            return $this->failNotFound('Compétence introuvable');
        }
        return $this->respond($competence);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $competence = $this->model->find($id);

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Compétence créée avec succès',
            'data' => $competence,
        ]);
    }

    public function update($id = null)
    {
        $competence = $this->model->find($id);
        if (!$competence) {
            return $this->failNotFound('Compétence introuvable');
        }

        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $competence = $this->model->find($id);

        return $this->respond([
            'status' => 'success',
            'message' => 'Compétence mise à jour avec succès',
            'data' => $competence,
        ]);
    }

    public function delete($id = null)
    {
        $competence = $this->model->find($id);
        if (!$competence) {
            return $this->failNotFound('Compétence introuvable');
        }

        $this->model->delete($id);

        return $this->respondDeleted([
            'status' => 'success',
            'message' => 'Compétence supprimée avec succès',
        ]);
    }

    public function domaines()
    {
        $domaines = $this->model
            ->select('DISTINCT comp_domaine')
            ->where('comp_domaine IS NOT NULL')
            ->where('comp_domaine !=', '')
            ->findAll();

        $result = array_map(fn($d) => $d['comp_domaine'], $domaines);

        return $this->respond($result);
    }
}

