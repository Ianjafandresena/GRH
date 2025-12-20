<?php
namespace App\Models\employee;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employee';
    protected $primaryKey = 'emp_code';
    protected $allowedFields = [
        'emp_nom', 'emp_prenom', 'emp_imarmp', 'emp_sexe', 
        'emp_date_embauche', 'emp_mail', 'emp_disponibilite',
        'sign_code'
    ];
    public $useTimestamps = false;
}
