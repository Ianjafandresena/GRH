<?php
namespace App\Models\referentiel;

use CodeIgniter\Model;

class PositionModel extends Model
{
    protected $table = 'position_';
    protected $primaryKey = 'pos_code';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['pos_type'];
}

