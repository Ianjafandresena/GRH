<?php
namespace App\Models\permission;

use CodeIgniter\Model;

class PermissionModel extends Model
{
    protected $table = 'permission';
    protected $primaryKey = 'prm_code';
    protected $allowedFields = [
        'prm_duree', 'prm_date', 'prm_debut', 'prm_fin', 'prm_status', 'emp_code'
    ];
    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
