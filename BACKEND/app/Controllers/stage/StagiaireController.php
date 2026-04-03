<?php
namespace App\Controllers\stage;

use CodeIgniter\RESTful\ResourceController;
use App\Models\stage\StagiaireModel;

class StagiaireController extends ResourceController
{
    protected $modelName = StagiaireModel::class;
    protected $format = 'json';

    public function stats()
    {
        $total = $this->model->countAllResults();

        $db = \Config\Database::connect();

        $filieres = $db->table('stagiaire')
            ->select('stgr_filiere as label, count(*) as value')
            ->groupBy('stgr_filiere')
            ->orderBy('value', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        $niveaux = $db->table('stagiaire')
            ->select('stgr_niveau as label, count(*) as value')
            ->groupBy('stgr_niveau')
            ->orderBy('value', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return $this->respond([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'filieres' => $filieres,
                'niveaux' => $niveaux
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
                ->like('stgr_nom', $q)
                ->orLike('stgr_prenom', $q)
                ->orLike('stgr_nom_prenom', $q)
                ->groupEnd();
        }

        if (!empty($filters['contact'])) {
            $builder->like('stgr_contact', trim((string) $filters['contact']));
        }

        if (!empty($filters['filiere'])) {
            $builder->like('stgr_filiere', trim((string) $filters['filiere']));
        }

        if (!empty($filters['niveau'])) {
            $builder->like('stgr_niveau', trim((string) $filters['niveau']));
        }

        $stagiaires = $builder
            ->orderBy('stgr_code', 'DESC')
            ->findAll();

        return $this->respond($stagiaires);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $stagiaire = $this->model->find($id);

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Stagiaire créé avec succès',
            'data' => $stagiaire,
        ]);
    }

    public function show($id = null)
    {
        $stagiaire = $this->model->find($id);
        if (!$stagiaire) {
            return $this->failNotFound('Stagiaire introuvable');
        }

        return $this->respond($stagiaire);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $stagiaire = $this->model->find($id);

        return $this->respond([
            'status' => 'success',
            'message' => 'Stagiaire mis à jour avec succès',
            'data' => $stagiaire,
        ]);
    }

    public function delete($id = null)
    {
        $stagiaire = $this->model->find($id);
        if (!$stagiaire) {
            return $this->failNotFound('Stagiaire introuvable');
        }

        $this->model->delete($id);

        return $this->respondDeleted([
            'status' => 'success',
            'message' => 'Stagiaire supprimé avec succès',
        ]);
    }
}

