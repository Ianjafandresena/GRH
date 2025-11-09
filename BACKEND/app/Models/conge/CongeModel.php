<?php

namespace App\Models\conge;

use CodeIgniter\Model;

class CongeModel extends Model
{
    protected $table      = 'conge';
    protected $primaryKey = 'cng_code';

    protected $allowedFields = [
        'cng_nb_jour',
        'cng_debut',
        'cng_fin',
        'cng_demande',
        'emp_code',
        'val_code',
        'typ_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
