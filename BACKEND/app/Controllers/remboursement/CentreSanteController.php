<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\CentreSanteModel;
use App\Models\remboursement\ConventionModel;

class CentreSanteController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste tous les centres de santé avec leur convention
     */
    public function index()
    {
        $model = new CentreSanteModel();
        $centres = $model->select('centre_sante.*, convention.cnv_taux_couver, convention.cnv_date_debut, convention.cnv_date_fin')
            ->join('convention', 'convention.cnv_code = centre_sante.cnv_code', 'left')
            ->findAll();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($centres);
    }

    /**
     * Détail d'un centre de santé
     */
    public function show($id = null)
    {
        $model = new CentreSanteModel();
        $centre = $model->select('centre_sante.*, convention.cnv_taux_couver, convention.cnv_date_debut, convention.cnv_date_fin')
            ->join('convention', 'convention.cnv_code = centre_sante.cnv_code', 'left')
            ->where('centre_sante.cen_code', $id)
            ->first();

        if (!$centre) {
            return $this->failNotFound('Centre de santé non trouvé');
        }

        return $this->respond($centre);
    }

    /**
     * Créer un nouveau centre de santé
     */
    public function create()
    {
        $data = $this->request->getJSON(true);

        if (!isset($data['cen_nom'], $data['cnv_code'])) {
            return $this->fail('Données obligatoires manquantes (cen_nom, cnv_code)');
        }

        // Vérifier que la convention existe
        $conventionModel = new ConventionModel();
        $convention = $conventionModel->find($data['cnv_code']);
        if (!$convention) {
            return $this->failValidationErrors('Convention non trouvée');
        }

        $model = new CentreSanteModel();
        $id = $model->insert($data);

        if ($id === false) {
            return $this->fail('Impossible de créer le centre de santé');
        }

        $created = $model->find($id);
        return $this->respondCreated($created);
    }

    /**
     * Modifier un centre de santé
     */
    public function update($id = null)
    {
        $model = new CentreSanteModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->failNotFound('Centre de santé non trouvé');
        }

        $data = $this->request->getJSON(true);

        // Vérifier convention si modifiée
        if (isset($data['cnv_code'])) {
            $conventionModel = new ConventionModel();
            if (!$conventionModel->find($data['cnv_code'])) {
                return $this->failValidationErrors('Convention non trouvée');
            }
        }

        $model->update($id, $data);
        $updated = $model->find($id);

        return $this->respond($updated);
    }

    /**
     * Supprimer un centre de santé
     */
    public function delete($id = null)
    {
        $model = new CentreSanteModel();
        $existing = $model->find($id);

        if (!$existing) {
            return $this->failNotFound('Centre de santé non trouvé');
        }

        $model->delete($id);

        return $this->respond(['message' => 'Centre de santé supprimé']);
    }
}
