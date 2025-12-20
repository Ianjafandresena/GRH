<?php

namespace App\Models\conge;

use CodeIgniter\Model;

class InterruptionModel extends Model
{
    protected $table      = 'interruption';
    protected $primaryKey = 'interup_code';

    protected $allowedFields = [
        'interup_date',
        'interup_motif',
        'interup_restant',
        'cng_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
