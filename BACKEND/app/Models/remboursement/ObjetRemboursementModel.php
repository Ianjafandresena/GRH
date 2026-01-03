<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class ObjetRemboursementModel extends Model
{
    protected $table      = 'objet_remboursement';
    protected $primaryKey = 'obj_code';

    protected $allowedFields = [
        'obj_article'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
