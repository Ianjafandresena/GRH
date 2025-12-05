<?php

namespace App\Controllers\permission;
use CodeIgniter\RESTful\ResourceController;
use App\Models\permission\SoldePermissionModel;

class SoldePermissionController extends ResourceController
{
    protected $modelName = 'App\Models\permission\SoldePermissionModel';
    protected $format = 'json';

    // GET /solde_permission
    public function index()
    {
        $model = new SoldePermissionModel();
        $emp_code = $this->request->getGet('emp_code');
        $builder = $model->select('solde_permission.*');
        
        if ($emp_code) {
            $builder->where('solde_permission.emp_code', $emp_code);
        }
        
        $soldes = $builder->orderBy('solde_permission.sld_prm_anne', 'DESC')->findAll();
        return $this->respond($soldes);
    }

    // GET /solde_permission/{id}
    public function show($id = null)
    {
        $model = new SoldePermissionModel();
        $solde = $model->find($id);
        if (!$solde) {
            return $this->failNotFound('Solde non trouvé');
        }
        return $this->respond($solde);
    }

    // GET /solde_permission/last_dispo/{emp_code} : le reliquat à défalquer
    public function lastDispo($emp_code = null)
    {
        if (!$emp_code) {
            return $this->fail('Paramètre employé manquant');
        }
        $db = \Config\Database::connect();
        $builder = $db->table('solde_permission');
        $builder->select('solde_permission.sld_prm_dispo, solde_permission.sld_prm_anne');
        $builder->where('solde_permission.emp_code', $emp_code);
        $builder->where('solde_permission.sld_prm_dispo >', 0);
        $builder->orderBy('solde_permission.sld_prm_anne', 'ASC'); // Plus ancien reliquat
        $solde = $builder->get(1)->getRowArray();
        
        if (!$solde) {
            return $this->failNotFound('Aucun reliquat positif pour cet employé');
        }
        return $this->respond($solde);
    }

    // POST /solde_permission
    public function create()
    {
        $model = new SoldePermissionModel();
        $data = $this->request->getJSON(true);
        $id = $model->insert($data);
        if ($id === false) {
            return $this->fail('Erreur création solde');
        }
        return $this->respondCreated($model->find($id));
    }

    // PUT /solde_permission/{id}
    public function update($id = null)
    {
        $model = new SoldePermissionModel();
        $data = $this->request->getJSON(true);
        if ($model->update($id, $data)) {
            return $this->respond($model->find($id));
        }
        return $this->fail('Erreur modification solde');
    }

    // DELETE /solde_permission/{id}
    public function delete($id = null)
    {
        $model = new SoldePermissionModel();
        if ($model->delete($id)) {
            return $this->respondDeleted(['id' => $id]);
        }
        return $this->fail('Erreur suppression solde');
    }
}
