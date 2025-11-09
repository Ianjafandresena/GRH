<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
// ⚠️ Utilise bien le bon namespace ici, selon ton arborescence !
use App\Models\conge\CongeModel; // <--- corrige le namespace !

use CodeIgniter\API\ResponseTrait;

class CongeController extends ResourceController
{
    use ResponseTrait;

    // Création du congé (POST /api/conge/)
    public function createConge()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cng_nb_jour'], $data['cng_debut'], $data['cng_fin'], $data['emp_code'], $data['typ_code'])) {
            return $this->fail('Données obligatoires manquantes');
        }

        $data['cng_demande'] = date('Y-m-d H:i:s');

        $congeModel = new CongeModel();
        $id = $congeModel->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer le congé');
        }

        $createdConge = $congeModel->find($id);
        return $this->respondCreated($createdConge);
    }

    // Liste tous les congés (GET /api/conge/)
    public function getAllConges()
    {
        $congeModel = new CongeModel();
        $allConges = $congeModel->findAll();
        return $this->respond($allConges);
    }

    // Détail d’un congé selon ID (GET /api/conge/{id})
    public function getConge($id = null)
    {
        $congeModel = new CongeModel();
        $conge = $congeModel->find($id);
        if (!$conge) {
            return $this->failNotFound('Congé non trouvé');
        }
        return $this->respond($conge);
    }
}
