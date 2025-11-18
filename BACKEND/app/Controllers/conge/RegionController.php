<?php
namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\RegionModel;

class RegionController extends ResourceController
{
    protected $modelName = RegionModel::class;
    protected $format    = 'json';

    // GET /api/region/
    public function index()
    {
        $model = new RegionModel();
        $result = $model->findAll();
        return $this->respond($result);
    }
}
