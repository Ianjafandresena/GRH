<?php
namespace App\Controllers\employee;

use CodeIgniter\RESTful\ResourceController;
use App\Models\employee\EmployeeModel;

class EmployeeController extends ResourceController
{
    public function getAllEmployees()
    {
        $model = new EmployeeModel();
        $data = $model->findAll();
        return $this->respond($data, 200);
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
