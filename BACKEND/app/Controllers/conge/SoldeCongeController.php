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

    // POST /solde_conge/attribuer
    public function attribuerManuellement()
    {
        $data = $this->request->getJSON(true);
        $empCode = $data['emp_code'] ?? null;
        $type = $data['type'] ?? 'conge'; // 'conge' or 'permission'
        $jours = (float)($data['jours'] ?? 0);
        $annee = (int)($data['annee'] ?? date('Y'));

        if (!$empCode) return $this->fail('emp_code requis');

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            if ($type === 'conge') {
                // Règle Décision: [seq]/ARMP/DG-[annee+1 % 100]
                // 044/ARMP/DG-22 pour l'année 2021
                $anneeSignature = ($annee + 1) % 100;
                $pattern = "/ARMP/DG-" . sprintf("%02d", $anneeSignature);
                
                // On cherche le dernier séquentiel pour cet acte
                $lastDec = $db->table('decision')
                    ->like('dec_num', $pattern, 'before')
                    ->orderBy('dec_code', 'DESC')
                    ->get()->getRowArray();
                
                $seq = 1;
                if ($lastDec) {
                    $parts = explode('/', $lastDec['dec_num']);
                    if (is_numeric($parts[0])) {
                        $seq = (int)$parts[0] + 1;
                    }
                }
                
                $decNum = sprintf("%03d/ARMP/DG-%02d", $seq, $anneeSignature);
                
                // 1. Créer la décision
                $db->table('decision')->insert(['dec_num' => $decNum]);
                $decCode = $db->insertID();
                
                // 2. Créer le solde
                $db->table('solde_conge')->insert([
                    'emp_code' => $empCode,
                    'sld_anne' => $annee,
                    'sld_initial' => $jours,
                    'sld_restant' => $jours,
                    'sld_dispo' => 1,
                    'dec_code' => $decCode,
                    'sld_maj' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Permission : on vérifie si un solde existe déjà pour cette année
                $existing = $db->table('solde_permission')
                    ->where('emp_code', $empCode)
                    ->where('sld_prm_anne', $annee)
                    ->get()->getRowArray();

                if ($existing) {
                    $db->table('solde_permission')
                        ->where('sld_prm_code', $existing['sld_prm_code'])
                        ->update(['sld_prm_dispo' => $jours]);
                } else {
                    $db->table('solde_permission')->insert([
                        'emp_code' => $empCode,
                        'sld_prm_anne' => $annee,
                        'sld_prm_dispo' => $jours
                    ]);
                }
            }

            $db->transComplete();
            
            if ($db->transStatus() === false) {
                return $this->fail('Erreur lors de la transaction');
            }

            return $this->respond(['status' => 'success', 'message' => 'Solde attribué avec succès']);

        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->fail($e->getMessage());
        }
    }
}
