<?php

namespace App\Controllers\remboursement;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\remboursement\CentreSanteModel;
use App\Models\remboursement\TypeCentreModel;

class CentreSanteController extends ResourceController
{
    use ResponseTrait;

    /**
     * Liste tous les centres de santé avec leur type
     * Supports filtering by tp_cen_code and search by cen_nom
     */
    public function index()
    {
        $model = new CentreSanteModel();
        $builder = $model->select('centre_sante.*, type_centre.tp_cen')
            ->join('type_centre', 'type_centre.tp_cen_code = centre_sante.tp_cen_code', 'left');

        // Filter by type if provided
        $typeCode = $this->request->getGet('tp_cen_code');
        if ($typeCode) {
            $builder->where('centre_sante.tp_cen_code', $typeCode);
        }

        // Search by name if provided
        $search = $this->request->getGet('search');
        if ($search) {
            $builder->like('centre_sante.cen_nom', $search, 'both');
        }

        $centres = $builder->orderBy('centre_sante.cen_nom', 'ASC')->findAll();

        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($centres);
    }

    /**
     * Liste tous les types de centre
     */
    public function getTypes()
    {
        $model = new TypeCentreModel();
        $types = $model->findAll();
        return $this->respond($types);
    }

    /**
     * Détail d'un centre de santé
     */
    public function show($id = null)
    {
        $model = new CentreSanteModel();
        $centre = $model->select('centre_sante.*, type_centre.tp_cen')
            ->join('type_centre', 'type_centre.tp_cen_code = centre_sante.tp_cen_code', 'left')
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

        if (!isset($data['cen_nom']) || !isset($data['tp_cen_code'])) {
            return $this->fail('Données obligatoires manquantes (cen_nom, tp_cen_code)');
        }

        // Vérifier que le type existe
        $typeModel = new TypeCentreModel();
        $type = $typeModel->find($data['tp_cen_code']);
        if (!$type) {
            return $this->failValidationErrors('Type de centre non trouvé');
        }

        $model = new CentreSanteModel();
        $insertData = [
            'cen_nom' => $data['cen_nom'],
            'cen_adresse' => $data['cen_adresse'] ?? null,
            'tp_cen_code' => $data['tp_cen_code']
        ];

        $id = $model->insert($insertData);

        if ($id === false) {
            return $this->fail('Impossible de créer le centre de santé');
        }

        $created = $model->select('centre_sante.*, type_centre.tp_cen')
            ->join('type_centre', 'type_centre.tp_cen_code = centre_sante.tp_cen_code', 'left')
            ->find($id);
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

        // Vérifier type si modifié
        if (isset($data['tp_cen_code'])) {
            $typeModel = new TypeCentreModel();
            if (!$typeModel->find($data['tp_cen_code'])) {
                return $this->failValidationErrors('Type de centre non trouvé');
            }
        }

        $model->update($id, $data);
        $updated = $model->select('centre_sante.*, type_centre.tp_cen')
            ->join('type_centre', 'type_centre.tp_cen_code = centre_sante.tp_cen_code', 'left')
            ->find($id);

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

        // Check if center is used in PEC or demande_remb
        $db = \Config\Database::connect();
        
        $pecUsage = $db->table('pris_en_charge')->where('cen_code', $id)->countAllResults();
        $rembUsage = $db->table('demande_remb')->where('cen_code', $id)->countAllResults();
        
        if ($pecUsage > 0 || $rembUsage > 0) {
            return $this->fail('Impossible de supprimer: ce centre est utilisé dans des prises en charge ou demandes');
        }

        $model->delete($id);

        return $this->respond(['message' => 'Centre de santé supprimé']);
    }
}
