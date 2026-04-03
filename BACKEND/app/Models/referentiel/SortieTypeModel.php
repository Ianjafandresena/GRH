<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class SortieTypeModel extends Model
{
    protected $table = 'sortie_type';
    protected $primaryKey = 's_type_code';
    protected $allowedFields = ['s_type_code', 's_type_motif'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

