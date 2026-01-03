<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class FactureModel extends Model
{
    protected $table      = 'facture';
    protected $primaryKey = 'fac_code';

    protected $allowedFields = [
        'fac_num',
        'fac_date'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
