<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class TypeEntreeController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = model('App\Models\referentiel\TypeEntreeModel');
        $types = $model->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($types),
            'data' => $types
        ], 200);
    }
}
