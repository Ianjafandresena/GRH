<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class RangHierarchiqueModel extends Model
{
    protected $table = 'rang_hierarchique';
    protected $primaryKey = 'rhq_code';
    protected $allowedFields = ['rhq_code', 'rhq_rang'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

