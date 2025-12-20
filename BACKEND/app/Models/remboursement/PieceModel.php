<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class PieceModel extends Model
{
    protected $table      = 'piece';
    protected $primaryKey = 'pc_code';

    protected $allowedFields = [
        'pc_piece',
        'rem_code'
    ];

    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
