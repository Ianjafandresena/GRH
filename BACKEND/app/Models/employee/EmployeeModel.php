<?php
namespace App\Models\employee;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employee';
    protected $primaryKey = 'emp_code';
    protected $allowedFields = [
        'nom', 'prenom', 'matricule', 'sexe', 'date_embauche', 'email', 'is_actif'
    ];
    public $useTimestamps = false;
}
