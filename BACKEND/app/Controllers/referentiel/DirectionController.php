<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class DirectionController extends ResourceController
{
    protected $modelName = 'App\Models\referentiel\DirectionModel';
    protected $format = 'json';

    /**
     * Liste de toutes les directions
     */
    public function index()
    {
        $model = model($this->modelName);
        $directions = $model->orderBy('dir_nom', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($directions),
            'data' => $directions
        ], 200);
    }
}
