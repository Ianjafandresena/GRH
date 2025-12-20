<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\EtatRembModel;

class EtatRembController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste tous les états de remboursement
     */
    public function index()
    {
        $model = new EtatRembModel();
        $etats = $model->findAll();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($etats);
    }
}
