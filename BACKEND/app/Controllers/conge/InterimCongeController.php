<?php

namespace App\Controllers\conge;

use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\InterimCongeModel;
use CodeIgniter\API\ResponseTrait;

class InterimCongeController extends ResourceController
{
    use ResponseTrait;

    // Création d’un intérim de congé
    public function createInterimConge()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['emp_code'], $data['cng_code'], $data['int_debut'], $data['int_fin'])) {
            return $this->fail('Données obligatoires manquantes');
        }

        $model = new InterimCongeModel();
        $id = $model->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer l’intérim');
        }

        $created = $model->find($id);
        return $this->respondCreated($created);
    }

    // Liste tous les interims de congés
    public function getAllInterimConges()
    {
        $model = new InterimCongeModel();
        $list = $model->findAll();
        return $this->respond($list);
    }
}
