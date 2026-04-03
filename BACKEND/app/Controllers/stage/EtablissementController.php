<?php
namespace App\Controllers\stage;

use CodeIgniter\RESTful\ResourceController;
use App\Models\stage\EtablissementModel;

class EtablissementController extends ResourceController
{
    protected $modelName = EtablissementModel::class;
    protected $format    = 'json';

    public function index()
    {
        $etablissements = $this->model->orderBy('etab_nom', 'ASC')->findAll();
        return $this->respond($etablissements);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $etablissement = $this->model->find($id);

        return $this->respondCreated([
            'status'  => 'success',
            'message' => 'Établissement créé avec succès',
            'data'    => $etablissement,
        ]);
    }

    public function show($id = null)
    {
        $etablissement = $this->model->find($id);
        if (!$etablissement) {
            return $this->failNotFound('Établissement introuvable');
        }

        return $this->respond($etablissement);
    }

    public function update($id = null)
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $etablissement = $this->model->find($id);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Établissement mis à jour avec succès',
            'data'    => $etablissement,
        ]);
    }

    public function delete($id = null)
    {
        $etablissement = $this->model->find($id);
        if (!$etablissement) {
            return $this->failNotFound('Établissement introuvable');
        }

        $this->model->delete($id);

        return $this->respondDeleted([
            'status'  => 'success',
            'message' => 'Établissement supprimé avec succès',
        ]);
    }
}

