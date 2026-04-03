<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class SortieTypeController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = model('App\Models\referentiel\SortieTypeModel');
        $types = $model->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($types),
            'data' => $types
        ], 200);
    }
}

