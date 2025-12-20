<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class ConventionModel extends Model
{
    protected $table      = 'convention';
    protected $primaryKey = 'cnv_code';

    protected $allowedFields = [
        'cnv_taux_couver',
        'cnv_date_debut',
        'cnv_date_fin'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
