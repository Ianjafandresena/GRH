<?php
namespace App\Models\permission;

use CodeIgniter\Model;

class DebitSoldePrmModel extends Model
{
    protected $table = 'debit_solde_prm';
    protected $primaryKey = 'deb_prm_code';
    protected $allowedFields = [
        'emp_code', 'prm_code', 'sld_prm_code', 'deb_jr', 'deb_date'
    ];
    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
