<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class DirectionModel extends Model
{
    protected $table = 'direction';
    protected $primaryKey = 'dir_code';
    protected $allowedFields = ['dir_code', 'dir_nom', 'dir_abbreviation'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
}

