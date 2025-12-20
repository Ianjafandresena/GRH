<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class CentreSanteModel extends Model
{
    protected $table      = 'centre_sante';
    protected $primaryKey = 'cen_code';

    protected $allowedFields = [
        'cen_nom',
        'cnv_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
