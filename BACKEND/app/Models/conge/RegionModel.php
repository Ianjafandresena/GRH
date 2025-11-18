<?php
namespace App\Models\conge;
use CodeIgniter\Model;

class RegionModel extends Model
{
    protected $table = 'region';
    protected $primaryKey = 'reg_code';
    protected $allowedFields = ['reg_nom'];
    protected $returnType = 'array';
}
