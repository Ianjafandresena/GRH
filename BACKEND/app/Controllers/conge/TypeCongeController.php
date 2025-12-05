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
        
       return $this->response
                ->setContentType('application/json; charset=UTF-8')
                ->setJSON($result);
    }
}
