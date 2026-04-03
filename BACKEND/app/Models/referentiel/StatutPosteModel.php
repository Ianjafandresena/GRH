<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class StatutPosteModel extends Model
{
    protected $table = 'statut_poste';
    protected $primaryKey = 'stpst_code';
    protected $allowedFields = ['stpst_code', 'stpst_statut'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

