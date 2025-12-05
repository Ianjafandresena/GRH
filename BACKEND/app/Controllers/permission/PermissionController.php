<?php

namespace App\Controllers\permission;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\permission\PermissionModel;
use App\Models\permission\SoldePermissionModel;
use App\Models\permission\DebitSoldePrmModel;

class PermissionController extends ResourceController
{
    use ResponseTrait;

    public function createPermission()
    {
        $payload = $this->request->getJSON(true);
        if (!isset($payload['emp_code'], $payload['prm_debut'], $payload['prm_fin'])) {
            return $this->fail('Données obligatoires manquantes (emp_code, prm_debut, prm_fin)');
        }
        
        $d1 = strtotime($payload['prm_debut']);
        $d2 = strtotime($payload['prm_fin']);
        
        if ($d1 === false || $d2 === false || $d2 < $d1) {
            return $this->failValidationErrors('Dates invalides ou fin avant début');
        }

        // Calcul durée en heures
        $seconds = $d2 - $d1;
        $hours = $seconds / 3600;
        $prm_duree = round($hours, 2);
        
        // Calcul débit en jours (1 jour = 8 heures)
        $debitDays = $hours / 8.0;
        
        // Format datetime pour PostgreSQL
        $prm_debut = date('Y-m-d H:i:s', $d1);
        $prm_fin = date('Y-m-d H:i:s', $d2);

        $data = [
            'emp_code' => (int)$payload['emp_code'],
            'prm_duree' => $prm_duree,
            'prm_debut' => $prm_debut,
            'prm_fin' => $prm_fin,
            'val_code' => isset($payload['val_code']) ? (int)$payload['val_code'] : null,
        ];
        
        $model = new PermissionModel();
        $db = \Config\Database::connect();
        
        // Démarrer la transaction
        $db->transStart();
        
        try {
            $id = $model->insert($data);
            if ($id === false) {
                throw new \Exception('Impossible de créer la permission');
            }
            
         
            if ($debitDays > 0) {
                $soldeModel = new SoldePermissionModel();
                $debitModel = new DebitSoldePrmModel();
                $reste = $debitDays;
                $reliquats = $soldeModel
                    ->where('emp_code', (int)$payload['emp_code'])
                    ->where('sld_prm_dispo >', 0)
                    ->orderBy('sld_prm_anne', 'ASC')
                    ->findAll();
                    
                if (empty($reliquats)) {
                    throw new \Exception('Aucun solde disponible pour cet employé');
                }
                
                foreach ($reliquats as $reliq) {
                    if ($reste <= 0) break;
                    $debit = min($reste, (float)$reliq['sld_prm_dispo']);
                    $updateResult = $soldeModel->update($reliq['sld_prm_code'], [
                        'sld_prm_dispo' => (float)$reliq['sld_prm_dispo'] - $debit
                    ]);
                    if ($updateResult === false) {
                        throw new \Exception('Erreur lors de la mise à jour du solde');
                    }
                    
                    $insertResult = $debitModel->insert([
                        'emp_code' => (int)$payload['emp_code'],
                        'prm_code' => $id,
                        'sld_prm_code' => (int)$reliq['sld_prm_code'],
                        'deb_jr' => $debit,
                        'deb_date' => date('Y-m-d H:i:s')
                    ]);
                    if ($insertResult === false) {
                        throw new \Exception('Erreur lors de l\'enregistrement du débit');
                    }
                    
                    $reste -= $debit;
                }
                
                if ($reste > 0) {
                    throw new \Exception('Solde insuffisant pour cette permission');
                }
            }
            
            // Compléter la transaction
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Erreur lors de la transaction');
            }
            
            $created = $model->find($id);
            return $this->respondCreated($created);
            
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 500);
        }
    }

    public function getAllPermissions()
    {
        $model = new PermissionModel();
        $builder = $model->select('permission.*, employee.nom AS nom_emp, employee.prenom AS prenom_emp')
            ->join('employee', 'employee.emp_code = permission.emp_code', 'left');
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;
        if ($start) $builder->where('permission.prm_debut >=', $start);
        if ($end) $builder->where('permission.prm_debut <=', $end);
        $rows = $builder->findAll();
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        return $this->respond($rows);
    }

    public function getPermission($id = null)
    {
        $model = new PermissionModel();
        $row = $model->find($id);
        if (!$row) return $this->failNotFound('Permission non trouvée');
        return $this->respond($row);
    }
}
