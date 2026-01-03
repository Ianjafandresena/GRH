<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class PrisEnChargeModel extends Model
{
    protected $table      = 'pris_en_charge';
    protected $primaryKey = 'pec_code';

    protected $allowedFields = [
        'pec_num',
        'pec_date_arrive',
        'pec_date_depart',
        'pec_creation',
        'pec_approuver',
        'cen_code',
        'enf_code',
        'conj_code',
        'emp_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
