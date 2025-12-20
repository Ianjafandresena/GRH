<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class FactureModel extends Model
{
    protected $table      = 'facture';
    protected $primaryKey = 'fac_code';

    protected $allowedFields = [
        'fac_objet',
        'fac_total',
        'cen_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
