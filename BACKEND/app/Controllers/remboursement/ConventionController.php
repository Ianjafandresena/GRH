<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\ConventionModel;

class ConventionController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste toutes les conventions
     */
    public function index()
    {
        $model = new ConventionModel();
        $conventions = $model->findAll();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($conventions);
    }

    /**
     * Détail d'une convention
     */
    public function show($id = null)
    {
        $model = new ConventionModel();
        $convention = $model->find($id);

        if (!$convention) {
            return $this->failNotFound('Convention non trouvée');
        }

        return $this->respond($convention);
    }

    /**
     * Créer une nouvelle convention
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cnv_taux_couver'], $data['cnv_date_debut'])) {
            return $this->fail('Données obligatoires manquantes (cnv_taux_couver, cnv_date_debut)');
        }

        // Validation du taux de couverture (0-100)
        if ($data['cnv_taux_couver'] < 0 || $data['cnv_taux_couver'] > 100) {
            return $this->failValidationErrors('Le taux de couverture doit être entre 0 et 100');
        }

        $model = new ConventionModel();
        $id = $model->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer la convention');
        }

        $created = $model->find($id);
        return $this->respondCreated($created);
    }

    /**
     * Modifier une convention
     */
    public function update($id = null)
    {
        $model = new ConventionModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->failNotFound('Convention non trouvée');
        }

        $data = $this->request->getJSON(true);

        // Validation du taux si présent
        if (isset($data['cnv_taux_couver'])) {
            if ($data['cnv_taux_couver'] < 0 || $data['cnv_taux_couver'] > 100) {
                return $this->failValidationErrors('Le taux de couverture doit être entre 0 et 100');
            }
        }

        $model->update($id, $data);
        $updated = $model->find($id);

        return $this->respond($updated);
    }

    /**
     * Supprimer une convention
     */
    public function delete($id = null)
    {
        $model = new ConventionModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->failNotFound('Convention non trouvée');
        }

        $model->delete($id);

        return $this->respond(['message' => 'Convention supprimée']);
    }
}
