<?php

namespace App\Models\conge;

use CodeIgniter\Model;

class SoldeCongeModel extends Model
{
    protected $table      = 'solde_conge';
    protected $primaryKey = 'sld_code';

    protected $allowedFields = [
        'sld_initial',      // initial octroyé
        'sld_restant',      // restant après débits
        'sld_maj',          // date mise à jour
        'sld_anne',         // année/date de reliquat
        'dec_code',       
        'emp_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
