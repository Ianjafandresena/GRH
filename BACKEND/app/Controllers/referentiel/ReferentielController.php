<?php
namespace App\Controllers\referentiel;

use CodeIgniter\RESTful\ResourceController;

class ReferentielController extends ResourceController
{
    protected $format = 'json';


    /**
     * Liste des rangs hiérarchiques
     */
    public function rangs()
    {
        $model = model('App\Models\referentiel\RangHierarchiqueModel');
        $rangs = $model->orderBy('rhq_rang', 'ASC')->findAll();

        return $this->respond([
            'status' => 'success',
            'count' => count($rangs),
            'data' => $rangs
        ], 200);
    }
}
