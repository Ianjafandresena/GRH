<?php
namespace App\Models\permission;

use CodeIgniter\Model;

class SoldePermissionModel extends Model
{
    protected $table = 'solde_permission';
    protected $primaryKey = 'sld_prm_code';
    protected $allowedFields = [
        'sld_prm_dispo', 'sld_prm_anne', 'emp_code'
    ];
    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
