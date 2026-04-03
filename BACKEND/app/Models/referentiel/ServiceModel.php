<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class ServiceModel extends Model
{
    protected $table = 'service';
    protected $primaryKey = 'srvc_code';
    protected $allowedFields = ['srvc_code', 'srvc_nom', 'dir_code'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

