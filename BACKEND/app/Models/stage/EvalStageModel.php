<?php
namespace App\Models\stage;

use CodeIgniter\Model;

class EvalStageModel extends Model
{
    protected $table = 'eval_stage';
    protected $primaryKey = 'evstg_code';
    protected $allowedFields = [
        'evstg_lieu',
        'evstg_note',
        'evstg_aptitude',
        'evstg_date_eval',
        'asdt_code',
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    protected $validationRules = [
        'asdt_code'       => 'required|integer',
        'evstg_lieu'      => 'permit_empty|max_length[50]',
        'evstg_note'      => 'permit_empty|integer',
        'evstg_aptitude'  => 'permit_empty|max_length[50]',
        'evstg_date_eval' => 'permit_empty|max_length[50]',
    ];
}

