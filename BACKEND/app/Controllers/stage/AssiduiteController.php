<?php
namespace App\Controllers\stage;

use CodeIgniter\RESTful\ResourceController;
use App\Models\stage\AssiduiteModel;

class AssiduiteController extends ResourceController
{
    protected $modelName = AssiduiteModel::class;
    protected $format    = 'json';

    public function index()
    {
        $assiduites = $this->model->orderBy('asdt_code', 'DESC')->findAll();
        return $this->respond($assiduites);
    }

    public function show($id = null)
    {
        $assiduite = $this->model->find($id);
        if (!$assiduite) {
            return $this->failNotFound('Assiduité introuvable');
        }
        return $this->respond($assiduite);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $id = $this->model->getInsertID();
        $assiduite = $this->model->find($id);

        return $this->respondCreated([
            'status'  => 'success',
            'message' => 'Assiduité créée avec succès',
            'data'    => $assiduite,
        ]);
    }

    public function update($id = null)
    {
        $assiduite = $this->model->find($id);
        if (!$assiduite) {
            return $this->failNotFound('Assiduité introuvable');
        }

        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $assiduite = $this->model->find($id);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Assiduité mise à jour avec succès',
            'data'    => $assiduite,
        ]);
    }

    public function delete($id = null)
    {
        $assiduite = $this->model->find($id);
        if (!$assiduite) {
            return $this->failNotFound('Assiduité introuvable');
        }

        $this->model->delete($id);

        return $this->respondDeleted([
            'status'  => 'success',
            'message' => 'Assiduité supprimée avec succès',
        ]);
    }
}

