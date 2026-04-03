<?php
namespace App\Models\employe;

use CodeIgniter\Model;

class PosEmpModel extends Model
{
    protected $table = 'pos_emp';
    protected $primaryKey = 'emp_code';
    protected $useAutoIncrement = false;
    protected $allowedFields = [
        'emp_code',
        'pos_code',
        'date_',
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

