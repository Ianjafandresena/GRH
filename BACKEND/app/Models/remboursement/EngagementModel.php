<?php
namespace App\Models\remboursement;

use CodeIgniter\Model;

class EngagementModel extends Model
{
    protected $table      = 'engagement';
    protected $primaryKey = 'eng_code';
    protected $allowedFields = ['eng_date', 'eta_code'];
    protected $useAutoIncrement = true;
    public $returnType = 'array';
}
