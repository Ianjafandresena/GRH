<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class DemandeRembModel extends Model
{
    protected $table      = 'demande_remb';
    protected $primaryKey = 'rem_code';

    protected $allowedFields = [
        'rem_num',
        'rem_date',
        'rem_montant',
        'rem_montant_lettre',
        'rem_status',
        'rem_is_centre',
        'fac_code',
        'obj_code',
        'cen_code',
        'emp_code',
        'pec_code',
        'eta_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
