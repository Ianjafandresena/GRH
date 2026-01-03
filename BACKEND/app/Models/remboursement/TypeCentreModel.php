<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class TypeCentreModel extends Model
{
    protected $table      = 'type_centre';
    protected $primaryKey = 'tp_cen_code';

    protected $allowedFields = [
        'tp_cen'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
