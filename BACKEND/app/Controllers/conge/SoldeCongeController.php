<?php

namespace App\Controllers\conge;
use CodeIgniter\RESTful\ResourceController;
use App\Models\conge\SoldeCongeModel;

class SoldeCongeController extends ResourceController
{
    protected $modelName = 'App\Models\conge\SoldeCongeModel';
    protected $format = 'json';

    // GET /solde_conge
    public function index()
    {
        $model = new SoldeCongeModel();
        $emp_code = $this->request->getGet('emp_code');
        $builder = $model->select('solde_conge.*, decision.dec_num')
                         ->join('decision', 'decision.dec_code = solde_conge.dec_code', 'left');
        if ($emp_code) {
            $builder->where('solde_conge.emp_code', $emp_code);
        }
        $soldes = $builder->orderBy('solde_conge.sld_anne', 'DESC')->findAll();
        return $this->respond($soldes);
    }

    // GET /solde_conge/{id}
    public function show($id = null)
    {
        $model = new SoldeCongeModel();
        $solde = $model->find($id);
        if (!$solde) {
            return $this->failNotFound('Solde non trouvé');
        }
        return $this->respond($solde);
    }

    // GET /solde_conge/last_dispo/{emp_code} : le reliquat à défalquer
    public function lastDispo($emp_code = null)
    {
        if (!$emp_code) {
            return $this->fail('Paramètre employé manquant');
        }
        $db = \Config\Database::connect();
        $builder = $db->table('solde_conge');
        $builder->select('solde_conge.sld_restant, solde_conge.sld_anne, solde_conge.dec_code, decision.dec_num');
        $builder->join('decision', 'decision.dec_code = solde_conge.dec_code');
        $builder->where('solde_conge.emp_code', $emp_code);
        $builder->where('solde_conge.sld_restant >', 0);
        $builder->orderBy('solde_conge.sld_anne', 'ASC'); // Plus ancien reliquat
        $solde = $builder->get(1)->getRowArray();
        if (!$solde) return $this->failNotFound('Aucun reliquat positif pour cet employé');
        return $this->respond($solde);
    }

    // POST /solde_conge
    public function create()
    {
        $model = new SoldeCongeModel();
        $data = $this->request->getJSON(true);
        $id = $model->insert($data);
        if ($id === false) {
            return $this->fail('Erreur création solde');
        }
        return $this->respondCreated($model->find($id));
    }

    // PUT /solde_conge/{id}
    public function update($id = null)
    {
        $model = new SoldeCongeModel();
        $data = $this->request->getJSON(true);
        if ($model->update($id, $data)) {
            return $this->respond($model->find($id));
        }
        return $this->fail('Erreur modification solde');
    }

    // DELETE /solde_conge/{id}
    public function delete($id = null)
    {
        $model = new SoldeCongeModel();
        if ($model->delete($id)) {
            return $this->respondDeleted(['id' => $id]);
        }
        return $this->fail('Erreur suppression solde');
    }
}
