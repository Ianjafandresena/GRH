<?php
namespace App\Controllers\employee;

use CodeIgniter\RESTful\ResourceController;
use App\Models\employee\EmployeeModel;

class EmployeeController extends ResourceController
{
    public function getAllEmployees()
    {
        $db = \Config\Database::connect();
        
        // Requête pour récupérer les employés avec leur dernière affectation (fonction/direction)
        // Utilisation de DISTINCT ON pour PostgreSQL pour avoir la dernière affectation
        // Approche universelle (compatible tous SGBD) :
        // 1. Récupérer tout trié par date croissante
        // 2. Écraser les doublons dans une map PHP (le dernier est le plus récent)
        $sql = "
            SELECT 
                e.*,
                p.pst_fonction,
                d.dir_nom,
                d.dir_abreviation,
                a.affec_date_debut
            FROM employee e
            LEFT JOIN affectation a ON a.emp_code = e.emp_code
            LEFT JOIN poste p ON p.pst_code = a.pst_code
            LEFT JOIN fonction_direc fd ON fd.pst_code = p.pst_code
            LEFT JOIN direction d ON d.dir_code = fd.dir_code
            ORDER BY e.emp_code, a.affec_date_debut ASC
        ";

        try {
            $query = $db->query($sql);
            $rows = $query->getResultArray();
            
            $employees = [];
            foreach ($rows as $row) {
                // La clé est emp_code. Comme on trie par date ASC, 
                // la dernière occurrence écrase les précédentes -> garde la plus récente.
                $employees[$row['emp_code']] = $row;
            }
            
            // Renvoie un tableau indexé (liste)
            return $this->respond(array_values($employees), 200);
        } catch (\Throwable $e) {
            return $this->fail('Erreur SQL: ' . $e->getMessage());
        }
    }

    public function getEmployee($emp_code)
    {
        $model = new EmployeeModel();
        $employee = $model->find($emp_code);

        if (!$employee) {
            return $this->failNotFound("Aucun employé trouvé avec le code $emp_code");
        }
        return $this->respond($employee, 200);
    }



}
