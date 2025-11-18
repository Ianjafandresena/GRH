<?php

namespace App\Models\conge;
use CodeIgniter\Model;

class InterimCongeModel extends Model
{
    protected $table      = 'interim_conge';
    protected $primaryKey = 'int_code';
    protected $allowedFields = [
        'emp_code',
        'cng_code',
        'int_debut',
        'int_fin'
    ];
    protected $useAutoIncrement = true;
    public $returnType = 'array';
}


