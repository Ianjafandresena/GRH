<?php
namespace App\Models\employe;

use CodeIgniter\Model;

class CompEmployeModel extends Model
{
    protected $table            = 'comp_employe';
    protected $primaryKey       = 'emp_code'; // Composite key handled manually usually, or just set one part
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['emp_code', 'comp_code', 'niveau_acquis'];

    // Dates
    protected $useTimestamps = false;

    // Validation
    protected $validationRules      = [
        'emp_code'      => 'required|integer',
        'comp_code'     => 'required|integer',
        'niveau_acquis' => 'required|integer|greater_than[0]|less_than_equal_to[5]', // Assuming level 1-5
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
}

