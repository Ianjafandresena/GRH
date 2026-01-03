<?php
namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\ObjetRemboursementModel;

class ObjetRemboursementController extends ResourceController
{
    use ResponseTrait;

    public function index()
    {
        $model = new ObjetRemboursementModel();
        $objets = $model->orderBy('obj_article', 'ASC')->findAll();
        return $this->respond($objets);
    }

    public function show($id = null)
    {
        $model = new ObjetRemboursementModel();
        $objet = $model->find($id);
        if (!$objet) {
            return $this->failNotFound('Objet non trouvé');
        }
        return $this->respond($objet);
    }

    public function create()
    {
        $data = $this->request->getJSON(true);
        
        if (empty($data['obj_article'])) {
            return $this->failValidationErrors('Article requis');
        }

        $model = new ObjetRemboursementModel();
        
        // Check if article already exists
        $existing = $model->where('obj_article', $data['obj_article'])->first();
        if ($existing) {
            return $this->respond($existing); // Return existing instead of error
        }

        $id = $model->insert(['obj_article' => $data['obj_article']]);
        
        if ($id === false) {
            return $this->fail('Impossible de créer l\'objet');
        }

        return $this->respondCreated($model->find($id));
    }

    public function delete($id = null)
    {
        $model = new ObjetRemboursementModel();
        if (!$model->find($id)) {
            return $this->failNotFound('Objet non trouvé');
        }
        $model->delete($id);
        return $this->respondDeleted(['message' => 'Objet supprimé']);
    }
}
