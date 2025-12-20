<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class SignatureDemandeModel extends Model
{
    protected $table      = 'signature_demande';
    protected $primaryKey = ['rem_code', 'sign_code'];

    protected $allowedFields = [
        'rem_code',
        'sign_code',
        'sin_dem_code',
        'date_'
    ];

    protected $useAutoIncrement = false;
    public $returnType = 'array';
}
