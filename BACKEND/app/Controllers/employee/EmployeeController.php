<?php
namespace App\Controllers\employee;

use CodeIgniter\RESTful\ResourceController;
use App\Models\employee\EmployeeModel;

class EmployeeController extends ResourceController
{
    public function getAllEmployees()
    {
        $db = \Config\Database::connect();

        // Détecter dynamiquement le nom de la colonne d'abréviation (certains schémas utilisent 'dir_abreviation' et d'autres 'dir_abbreviation')
        $fields = $db->getFieldNames('direction');
        $abrvCol = in_array('dir_abreviation', $fields) ? 'dir_abreviation' : 'dir_abbreviation';

        $sql = "
            SELECT
                e.*,
                a.affec_code,
                a.affec_date_debut,
                a.affec_etat,
                a.tcontrat_code,
                p.pst_fonction,
                p.pst_mission,
                s.srvc_nom,
                COALESCE(d_direct.dir_nom, d_service.dir_nom) AS dir_nom,
                COALESCE(d_direct.$abrvCol, d_service.$abrvCol) AS dir_abbreviation,
                (SELECT numero FROM contact WHERE emp_code = e.emp_code LIMIT 1) AS emp_contact
            FROM employe e
            LEFT JOIN affectation a ON a.emp_code = e.emp_code AND a.affec_etat = 'active'
            LEFT JOIN poste p ON p.pst_code = a.pst_code
            LEFT JOIN service s ON s.srvc_code = p.srvc_code
            LEFT JOIN direction d_direct ON d_direct.dir_code = p.dir_code
            LEFT JOIN direction d_service ON d_service.dir_code = s.dir_code
            ORDER BY e.emp_code, a.affec_date_debut DESC NULLS LAST
        ";

        try {
            $query = $db->query($sql);
            $rows = $query->getResultArray();

            return $this->respond($rows, 200);
        } catch (\Throwable $e) {
            return $this->fail('Erreur SQL: ' . $e->getMessage());
        }
    }

    public function getEmployee($emp_code)
    {
        $db = \Config\Database::connect();

        // Détecter dynamiquement le nom de la colonne d'abréviation
        $fields = $db->getFieldNames('direction');
        $abrvCol = in_array('dir_abreviation', $fields) ? 'dir_abreviation' : 'dir_abbreviation';

        $sql = "
            SELECT
                e.*,
                a.affec_code,
                a.affec_date_debut,
                a.affec_date_fin,
                a.affec_etat,
                a.affec_commentaire,
                a.tcontrat_code,
                a.m_aff_code,
                p.pst_code,
                p.pst_fonction,
                p.pst_mission,
                s.srvc_nom,
                COALESCE(d_direct.dir_code, d_service.dir_code) AS dir_code,
                COALESCE(d_direct.dir_nom, d_service.dir_nom) AS dir_nom,
                COALESCE(d_direct.$abrvCol, d_service.$abrvCol) AS dir_abbreviation,
                tc.tcontrat_nom,
                (SELECT numero FROM contact WHERE emp_code = e.emp_code LIMIT 1) AS emp_contact
            FROM employe e
            LEFT JOIN affectation a ON a.emp_code = e.emp_code AND a.affec_etat = 'active'
            LEFT JOIN poste p ON p.pst_code = a.pst_code
            LEFT JOIN service s ON s.srvc_code = p.srvc_code
            LEFT JOIN direction d_direct ON d_direct.dir_code = p.dir_code
            LEFT JOIN direction d_service ON d_service.dir_code = s.dir_code
            LEFT JOIN type_contrat tc ON tc.tcontrat_code = a.tcontrat_code
            WHERE e.emp_code = ?
        ";

        try {
            $query = $db->query($sql, [$emp_code]);
            $employee = $query->getRowArray();

            if (!$employee) {
                return $this->failNotFound("Aucun employé trouvé avec le code $emp_code");
            }
            return $this->respond($employee, 200);
        } catch (\Throwable $e) {
            return $this->fail('Erreur SQL: ' . $e->getMessage());
        }
    }

    public function createEmployee()
    {
        $data = $this->request->getJSON(true);
        $model = new EmployeeModel();

        if (!$model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        $empCode = $model->getInsertID();
        $employee = $model->find($empCode);

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Employé créé avec succès',
            'data' => $employee
        ]);
    }
}
