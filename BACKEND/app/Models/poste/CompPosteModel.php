<?php
namespace App\Models\poste;

use CodeIgniter\Model;

class CompPosteModel extends Model
{
    protected $table = 'comp_poste';
    protected $primaryKey = 'pst_code';
    protected $useAutoIncrement = false;

    protected $allowedFields = [
        'pst_code',
        'comp_code',
        'niveau_requis',
    ];

    protected $returnType = 'array';
    protected $useTimestamps = false;
}

