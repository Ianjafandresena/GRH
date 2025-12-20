<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class ConjointeModel extends Model
{
    protected $table      = 'conjointe';
    protected $primaryKey = 'conj_code';

    protected $allowedFields = [
        'conj_nom',
        'conj_sexe'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
