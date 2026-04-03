<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class ServiceController extends ResourceController
{
    protected $modelName = 'App\Models\referentiel\ServiceModel';
    protected $format = 'json';

    /**
     * Liste de tous les services
     */
    public function index()
    {
        $model = model($this->modelName);
        $services = $model->orderBy('srvc_nom', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($services),
            'data' => $services
        ], 200);
    }
}
