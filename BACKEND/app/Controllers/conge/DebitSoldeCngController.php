<?php
namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\DebitSoldeCngModel;
use CodeIgniter\API\ResponseTrait;

class DebitSoldeCngController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = 'App\Models\conge\DebitSoldeCngModel';
    protected $format    = 'json';

    // GET all debits
    public function index()
    {
        $debitModel = new DebitSoldeCngModel();
        $allDebits = $debitModel->findAll();
        return $this->respond($allDebits);
    }

    // GET debit by id
    public function show($id = null)
    {
        $debitModel = new DebitSoldeCngModel();
        $debit = $debitModel->find($id);
        if (!$debit) {
            return $this->failNotFound('Débit non trouvé');
        }
        return $this->respond($debit);
    }

    // POST create new debit
   public function create()
{
    $debitModel = new DebitSoldeCngModel();
    $data = $this->request->getJSON(true);
    $id = $debitModel->insert($data);
    if ($id === false) {
        return $this->fail('Impossible de créer le mouvement débit');
    }
    return $this->respondCreated($debitModel->find($id));
}

    // PUT/PATCH update a debit
    public function update($id = null)
    {
        $debitModel = new DebitSoldeCngModel();
        $data = $this->request->getJSON(true);
        if (!$debitModel->find($id)) {
            return $this->failNotFound('Débit à modifier non trouvé');
        }
        $debitModel->update($id, $data);
        return $this->respond($debitModel->find($id));
    }

    // DELETE debit
    public function delete($id = null)
    {
        $debitModel = new DebitSoldeCngModel();
        if (!$debitModel->find($id)) {
            return $this->failNotFound('Débit à supprimer non trouvé');
        }
        $debitModel->delete($id);
        return $this->respondDeleted(['id' => $id, 'message' => 'Débit supprimé']);
    }
}
