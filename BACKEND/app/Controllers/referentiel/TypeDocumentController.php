<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class TypeDocumentController extends ResourceController
{
    protected $modelName = 'App\Models\referentiel\TypeDocumentModel';
    protected $format = 'json';

    /**
     * Liste de tous les types de documents
     */
    public function index()
    {
        $model = model($this->modelName);
        $types = $model->notLike('tdoc_nom', 'stage', 'both', null, true)
            ->orderBy('tdoc_nom', 'ASC')
            ->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($types),
            'data' => $types
        ], 200);
    }
}

