<?php

namespace App\Models\conge;
use CodeIgniter\Model;

class TypeCongeModel extends Model
{
    protected $table      = 'type_conge';
    protected $primaryKey = 'typ_code';
    protected $allowedFields = [
        'typ_appelation',
        'typ_ref',
        'is_paid'
    ];
    public $returnType = 'array';
}
