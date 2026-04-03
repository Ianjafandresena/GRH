<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class TypeContratModel extends Model
{
    protected $table = 'type_contrat';
    protected $primaryKey = 'tcontrat_code';
    protected $allowedFields = ['tcontrat_code', 'tcontrat_nom'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

