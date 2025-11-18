<?php
namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\DecisionModel;
use CodeIgniter\API\ResponseTrait;

class DecisionController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = 'App\Models\conge\DecisionModel';
    protected $format    = 'json';

    // GET all decisions
    public function index()
    {
        $decisionModel = new DecisionModel();
        $allDecisions = $decisionModel->findAll();
        return $this->respond($allDecisions);
    }

    // GET a decision by id
    public function show($id = null)
    {
        $decisionModel = new DecisionModel();
        $decision = $decisionModel->find($id);
        if (!$decision) {
            return $this->failNotFound('Décision non trouvée');
        }
        return $this->respond($decision);
    }

    // POST create new decision
    public function create()
    {
        $decisionModel = new DecisionModel();
        $data = $this->request->getJSON(true);
        $id = $decisionModel->insert($data);
        if ($id === false) {
            return $this->fail('Impossible de créer la décision');
        }
        return $this->respondCreated($decisionModel->find($id));
    }

    // PUT/PATCH update a decision
    public function update($id = null)
    {
        $decisionModel = new DecisionModel();
        $data = $this->request->getJSON(true);
        if (!$decisionModel->find($id)) {
            return $this->failNotFound('Décision à modifier non trouvée');
        }
        $decisionModel->update($id, $data);
        return $this->respond($decisionModel->find($id));
    }

    // DELETE a decision
    public function delete($id = null)
    {
        $decisionModel = new DecisionModel();
        if (!$decisionModel->find($id)) {
            return $this->failNotFound('Décision à supprimer non trouvée');
        }
        $decisionModel->delete($id);
        return $this->respondDeleted(['id' => $id, 'message' => 'Décision supprimée']);
    }
}
