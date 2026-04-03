<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class TypeContratController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = model('App\Models\referentiel\TypeContratModel');
        $types = $model->orderBy('tcontrat_nom', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($types),
            'data' => $types
        ], 200);
    }
}

