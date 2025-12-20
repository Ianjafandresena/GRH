<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class SignatureModel extends Model
{
    protected $table      = 'signature';
    protected $primaryKey = 'sign_code';

    protected $allowedFields = [
        'sign_libele'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
