<?php

namespace App\Controllers\conge;
use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\TypeCongeModel;

class TypeCongeController extends ResourceController
{
    protected $modelName = TypeCongeModel::class;
    protected $format    = 'json';

    // GET /api/type_conge/
    public function index()
    {
        $model = new TypeCongeModel();
        $result = $model->findAll();
        // On ne sélectionne que typ_code et typ_appelation si tu veux alléger
        // $result = $model->select(['typ_code', 'typ_appelation'])->findAll();
        return $this->respond($result);
    }
}
