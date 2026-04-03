<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class EnfantModel extends Model
{
    protected $table      = 'enfant';
    protected $primaryKey = 'enf_code';

    protected $allowedFields = [
        'enf_nom',
        'enf_num',
        'date_naissance',
        'emp_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
