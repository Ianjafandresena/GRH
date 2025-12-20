<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class PrisEnChargeModel extends Model
{
    protected $table      = 'pris_en_charge';
    protected $primaryKey = 'pec_code';

    protected $allowedFields = [
        'pec_num',
        'cen_code',
        'enf_code',
        'conj_code',
        'emp_code',
        'emp_code_1'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
