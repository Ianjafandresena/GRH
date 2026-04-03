<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class StageCarriereModel extends Model
{
    protected $table = 'stage_carriere';
    protected $primaryKey = 'stg_carriere_code';
    protected $allowedFields = [
        'emp_code',
        'pst_code',
        'stg_code'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'emp_code' => 'required|integer',
        'pst_code' => 'required|integer',
        'stg_code' => 'required|integer',
    ];
}

