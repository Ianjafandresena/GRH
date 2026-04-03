<?php
namespace App\Models\employee;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employe';
    protected $primaryKey = 'emp_code';
    protected $allowedFields = [
        'emp_matricule', 'emp_nom', 'emp_prenom', 'emp_titre',
        'emp_sexe', 'emp_datenaissance', 'emp_im_armp', 'emp_im_etat',
        'emp_mail', 'emp_cin', 'emp_disponibilite',
        'date_entree', 'date_sortie',
        's_type_code', 'e_type_code'
    ];
    public $useTimestamps = false;
}
