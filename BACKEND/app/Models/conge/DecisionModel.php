<?php
namespace App\Models\conge;

use CodeIgniter\Model;

class DecisionModel extends Model
{
    protected $table      = 'decision';
    protected $primaryKey = 'dec_code';

    protected $allowedFields = [
        'dec_num'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
