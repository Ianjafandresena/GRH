<?php
namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\FactureModel;

class FactureController extends ResourceController
{
    use ResponseTrait;

    public function index()
    {
        $model = new FactureModel();
        $factures = $model->orderBy('fac_date', 'DESC')->findAll();
        return $this->respond($factures);
    }

    public function show($id = null)
    {
        $model = new FactureModel();
        $facture = $model->find($id);
        if (!$facture) {
            return $this->failNotFound('Facture non trouvée');
        }
        return $this->respond($facture);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);
        
        if (empty($data['fac_num'])) {
            return $this->failValidationErrors('Numéro de facture requis');
        }

        $model = new FactureModel();

        $insert = [
            'fac_num' => $data['fac_num'],
            'fac_date' => $data['fac_date'] ?? date('Y-m-d')
        ];

        $id = $model->insert($insert);
        
        if ($id === false) {
            return $this->fail('Impossible de créer la facture');
        }

        return $this->respondCreated($model->find($id));
    }

    public function delete($id = null)
    {
        $model = new FactureModel();
        if (!$model->find($id)) {
            return $this->failNotFound('Facture non trouvée');
        }
        $model->delete($id);
        return $this->respondDeleted(['message' => 'Facture supprimée']);
    }
}
