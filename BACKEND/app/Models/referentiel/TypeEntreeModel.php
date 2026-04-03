<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class TypeEntreeModel extends Model
{
    protected $table = 'type_entree';
    protected $primaryKey = 'e_type_code';
    protected $allowedFields = ['e_type_code', 'e_type_motif'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

