<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class EtatRembModel extends Model
{
    protected $table      = 'etat_remb';
    protected $primaryKey = 'eta_code';

    protected $allowedFields = [
        'eta_date',
        'eta_total',
        'etat_num',
        'eta_libelle',
        'emp_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
