<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class PositionController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = model('App\Models\referentiel\PositionModel');
        $positions = $model->orderBy('pos_code', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($positions),
            'data' => $positions
        ], 200);
    }
}
