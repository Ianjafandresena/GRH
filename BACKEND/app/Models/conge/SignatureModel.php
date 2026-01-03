<?php

namespace App\Models\conge;

use CodeIgniter\Model;

class SignatureModel extends Model
{
    protected $table      = 'signature';
    protected $primaryKey = 'sign_code';

    protected $allowedFields = [
        'sign_libele',
        'sign_observation',
        'emp_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
