<?php

namespace App\Models\conge;

use CodeIgniter\Model;

class ValidationCongeModel extends Model
{
    protected $table      = 'validation_cng';
    protected $primaryKey = 'val_code';

    protected $allowedFields = [
        'cng_code',
        'sign_code',
        'val_date',
        'val_status',
        'val_observation'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
