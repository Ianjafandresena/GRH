<?php
namespace App\Models\affectation;

use CodeIgniter\Model;

class MotifAffectationModel extends Model
{
    protected $table = 'motif_affectation';
    protected $primaryKey = 'm_aff_code';
    protected $allowedFields = ['m_aff_motif', 'm_aff_type'];
    protected $returnType = 'array';
}

